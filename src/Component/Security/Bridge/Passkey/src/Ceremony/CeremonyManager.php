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

use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WpPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WpPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WpPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WpPack\Component\Transient\TransientManager;

final class CeremonyManager
{
    private const CHALLENGE_PREFIX = 'wppack_passkey_challenge_';
    private const CHALLENGE_TTL = 300;

    public function __construct(
        private readonly PasskeyConfiguration $config,
        private readonly CredentialRepositoryInterface $repository,
        private readonly TransientManager $transients,
    ) {}

    /**
     * Create registration options for a logged-in user.
     */
    public function createRegistrationOptions(\WP_User $user): PublicKeyCredentialCreationOptions
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
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            userVerification: $this->config->userVerification,
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rpEntity,
            user: $userEntity,
            challenge: random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create(
                    type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    alg: -7,
                ),
                PublicKeyCredentialParameters::create(
                    type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    alg: -257,
                ),
            ],
            authenticatorSelection: $authenticatorSelection,
            attestation: $this->config->attestation,
            excludeCredentials: $excludeCredentials,
            timeout: $this->config->timeout,
        );

        $challengeKey = self::CHALLENGE_PREFIX . bin2hex(random_bytes(16));
        $this->transients->set($challengeKey, [
            'options' => serialize($options),
            'userId' => $user->ID,
            'type' => 'registration',
        ], self::CHALLENGE_TTL);

        $this->setChallengeKey($challengeKey);

        return $options;
    }

    /**
     * Create authentication options (for login).
     */
    public function createAuthenticationOptions(): PublicKeyCredentialRequestOptions
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->config->rpId ?: $this->extractDomain(),
            userVerification: $this->config->userVerification,
            timeout: $this->config->timeout,
        );

        $challengeKey = self::CHALLENGE_PREFIX . bin2hex(random_bytes(16));
        $this->transients->set($challengeKey, [
            'options' => serialize($options),
            'type' => 'authentication',
        ], self::CHALLENGE_TTL);

        $this->setChallengeKey($challengeKey);

        return $options;
    }

    /**
     * Retrieve and consume stored ceremony data.
     *
     * @return array{options: string, userId?: int, type: string}|null
     */
    public function consumeChallenge(): ?array
    {
        $challengeKey = $this->getChallengeKey();
        if ($challengeKey === null) {
            return null;
        }

        $data = $this->transients->get($challengeKey);
        if (!\is_array($data)) {
            return null;
        }

        $this->transients->delete($challengeKey);
        $this->clearChallengeKey();

        return $data;
    }

    private function extractDomain(): string
    {
        return parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
    }

    private function setChallengeKey(string $key): void
    {
        if (!headers_sent()) {
            setcookie('wppack_passkey_ck', $key, [
                'expires' => time() + self::CHALLENGE_TTL,
                'path' => '/',
                'httponly' => true,
                'secure' => is_ssl(),
                'samesite' => 'Strict',
            ]);
            $_COOKIE['wppack_passkey_ck'] = $key;
        }
    }

    private function getChallengeKey(): ?string
    {
        return $_COOKIE['wppack_passkey_ck'] ?? null;
    }

    private function clearChallengeKey(): void
    {
        setcookie('wppack_passkey_ck', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => is_ssl(),
            'samesite' => 'Strict',
        ]);
        unset($_COOKIE['wppack_passkey_ck']);
    }
}
