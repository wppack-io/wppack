<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\Passkey\Ceremony;

use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WpPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WpPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WpPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Transient\TransientManager;

final class CeremonyManager
{
    private const CHALLENGE_PREFIX = 'wppack_passkey_challenge_';
    private const CHALLENGE_TTL = 300;

    public function __construct(
        private readonly PasskeyConfiguration $config,
        private readonly CredentialRepositoryInterface $repository,
        private readonly TransientManager $transients,
        private readonly ?BlogContextInterface $blogContext = null,
    ) {}

    /**
     * Create registration options for a logged-in user.
     *
     * @return array{options: PublicKeyCredentialCreationOptions, challengeKey: string}
     */
    public function createRegistrationOptions(\WP_User $user): array
    {
        $rpEntity = PublicKeyCredentialRpEntity::create(
            name: $this->config->rpName ?: get_bloginfo('name'),
            id: $this->config->rpId ?: $this->extractDomain(),
        );

        $userEntity = PublicKeyCredentialUserEntity::create(
            name: $user->user_login,
            id: (string) $user->ID,
            displayName: $user->display_name,
        );

        $existingCredentials = $this->repository->findByUserId($user->ID);
        $excludeCredentials = array_map(
            static fn(PasskeyCredential $c): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                id: base64_decode(strtr($c->credentialId, '-_', '+/')),
                transports: $c->transports,
            ),
            $existingCredentials,
        );

        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            authenticatorAttachment: $this->config->authenticatorAttachment !== '' ? $this->config->authenticatorAttachment : null,
            residentKey: $this->config->residentKey,
            userVerification: $this->config->userVerification,
        );

        $pubKeyCredParams = array_map(
            static fn(int $alg): PublicKeyCredentialParameters => PublicKeyCredentialParameters::create(
                type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                alg: $alg,
            ),
            $this->config->algorithms ?: [-7, -257],
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rpEntity,
            user: $userEntity,
            challenge: random_bytes(32),
            pubKeyCredParams: $pubKeyCredParams,
            authenticatorSelection: $authenticatorSelection,
            attestation: $this->config->attestation,
            excludeCredentials: $excludeCredentials,
            timeout: $this->config->timeout,
        );

        $serializer = $this->createSerializer();
        $challengeKey = self::CHALLENGE_PREFIX . bin2hex(random_bytes(16));
        $this->transients->set($challengeKey, [
            'options' => $serializer->serialize($options, 'json'),
            'optionsClass' => PublicKeyCredentialCreationOptions::class,
            'userId' => $user->ID,
            'type' => 'registration',
        ], self::CHALLENGE_TTL);

        return ['options' => $options, 'challengeKey' => $challengeKey];
    }

    /**
     * Create authentication options (for login).
     *
     * @return array{options: PublicKeyCredentialRequestOptions, challengeKey: string}
     */
    public function createAuthenticationOptions(): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->config->rpId ?: $this->extractDomain(),
            userVerification: $this->config->userVerification,
            timeout: $this->config->timeout,
        );

        $serializer = $this->createSerializer();
        $challengeKey = self::CHALLENGE_PREFIX . bin2hex(random_bytes(16));
        $this->transients->set($challengeKey, [
            'options' => $serializer->serialize($options, 'json'),
            'optionsClass' => PublicKeyCredentialRequestOptions::class,
            'type' => 'authentication',
        ], self::CHALLENGE_TTL);

        return ['options' => $options, 'challengeKey' => $challengeKey];
    }

    /**
     * Retrieve and consume stored ceremony data by key.
     *
     * @return array{options: string, optionsClass: class-string, userId?: int, type: string}|null
     */
    public function consumeChallenge(string $challengeKey): ?array
    {
        $data = $this->transients->get($challengeKey);
        if (!\is_array($data)) {
            return null;
        }

        $this->transients->delete($challengeKey);

        return $data;
    }

    /**
     * Deserialize stored ceremony options from JSON.
     */
    public function deserializeOptions(string $json, string $class): object
    {
        return $this->createSerializer()->deserialize($json, $class, 'json');
    }

    private function createSerializer(): \Symfony\Component\Serializer\SerializerInterface
    {
        return (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))->create();
    }

    private function extractDomain(): string
    {
        $blogId = ($this->blogContext !== null && $this->blogContext->isMultisite())
            ? $this->blogContext->getMainSiteId()
            : null;

        return parse_url(get_home_url($blogId), PHP_URL_HOST) ?: 'localhost';
    }

}
