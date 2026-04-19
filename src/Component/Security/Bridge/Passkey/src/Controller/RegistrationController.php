<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Bridge\Passkey\Controller;

use Psr\Log\LoggerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\Permission;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Storage\AaguidResolver;
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WPPack\Component\Site\BlogContextInterface;

/**
 * REST endpoints for passkey registration (attestation ceremony).
 *
 * Requires a logged-in user: passkeys are registered against an existing account.
 */
#[RestRoute(namespace: 'wppack/v1/passkey')]
#[Permission(callback: 'isLoggedIn')]
final class RegistrationController extends AbstractRestController
{
    public function __construct(
        private readonly CeremonyManager $ceremony,
        private readonly CredentialRepositoryInterface $repository,
        private readonly PasskeyConfiguration $config,
        private readonly AuthenticationSession $authenticationSession,
        private readonly LoggerInterface $logger,
        private readonly ?BlogContextInterface $blogContext = null,
    ) {}

    /**
     * Generate registration options (challenge) for the current user.
     */
    #[RestRoute(route: '/register/options', methods: HttpMethod::POST)]
    public function options(\WP_REST_Request $request): JsonResponse
    {
        $user = $this->authenticationSession->getCurrentUser();

        $existing = $this->repository->findByUserId($user->ID);
        if (\count($existing) >= $this->config->maxCredentialsPerUser) {
            return $this->json(['error' => 'Maximum number of passkeys reached.'], 400);
        }

        $result = $this->ceremony->createRegistrationOptions($user);

        $serializer = $this->createSerializer();
        $json = $serializer->serialize($result['options'], 'json');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $decoded['challengeKey'] = $result['challengeKey'];

        return $this->json($decoded);
    }

    /**
     * Verify attestation response and save the new credential.
     */
    #[RestRoute(route: '/register/verify', methods: HttpMethod::POST)]
    public function verify(\WP_REST_Request $request): JsonResponse
    {
        $user = $this->authenticationSession->getCurrentUser();
        $params = $request->get_json_params();

        $challengeKey = $params['challengeKey'] ?? '';
        $challengeData = $this->ceremony->consumeChallenge($challengeKey);
        if ($challengeData === null || $challengeData['type'] !== 'registration') {
            return $this->json(['error' => 'Invalid or expired challenge.'], 400);
        }

        /** @var \Webauthn\PublicKeyCredentialCreationOptions $creationOptions */
        $creationOptions = $this->ceremony->deserializeOptions(
            $challengeData['options'],
            $challengeData['optionsClass'],
        );

        try {
            $serializer = $this->createSerializer();
            $credential = $serializer->deserialize(
                json_encode($params, \JSON_THROW_ON_ERROR),
                PublicKeyCredential::class,
                'json',
            );

            $response = $credential->response;
            if (!$response instanceof AuthenticatorAttestationResponse) {
                return $this->json(['error' => 'Invalid attestation response.'], 400);
            }

            $rpId = $this->resolveRpId();

            $ceFactory = new CeremonyStepManagerFactory();
            $ceFactory->setSecuredRelyingPartyId([$rpId]);

            $validator = AuthenticatorAttestationResponseValidator::create($ceFactory->creationCeremony());
            $source = $validator->check(
                authenticatorAttestationResponse: $response,
                publicKeyCredentialCreationOptions: $creationOptions,
                host: $rpId,
            );

            $credentialId = rtrim(strtr(base64_encode($source->publicKeyCredentialId), '+/', '-_'), '=');

            // Prevent duplicate registration of the same credential
            if ($this->repository->findByCredentialId($credentialId) !== null) {
                return $this->json(['error' => 'This credential is already registered.'], 409);
            }

            $aaguid = $source->aaguid->toString();
            $backupEligible = $response->attestationObject->authData->isBackupEligible();
            $rawDeviceName = trim((string) ($params['deviceName'] ?? ''));
            $deviceName = $rawDeviceName !== '' ? mb_substr($rawDeviceName, 0, 255) : AaguidResolver::resolve($aaguid);

            $passkeyCredential = new PasskeyCredential(
                id: 0,
                userId: $user->ID,
                credentialId: $credentialId,
                publicKey: base64_encode($source->credentialPublicKey),
                counter: $source->counter,
                transports: $source->transports,
                deviceName: $deviceName,
                aaguid: $aaguid,
                backupEligible: $backupEligible,
                createdAt: new \DateTimeImmutable(),
                lastUsedAt: null,
            );

            $this->repository->save($passkeyCredential);

            return $this->created([
                'success' => true,
                'credentialId' => $credentialId,
                'deviceName' => $deviceName,
                'backupEligible' => $backupEligible,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Passkey registration failed.', [
                'userId' => $user->ID,
                'exception' => $e,
            ]);

            return $this->json(['error' => 'Passkey registration failed.'], 400);
        }
    }

    public function isLoggedIn(\WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    private function resolveRpId(): string
    {
        if ($this->config->rpId !== '') {
            return $this->config->rpId;
        }

        $blogId = ($this->blogContext !== null && $this->blogContext->isMultisite())
            ? $this->blogContext->getMainSiteId()
            : null;

        return parse_url(get_home_url($blogId), \PHP_URL_HOST) ?: 'localhost';
    }

    private function createSerializer(): \Symfony\Component\Serializer\SerializerInterface
    {
        $factory = new WebauthnSerializerFactory(AttestationStatementSupportManager::create());

        return $factory->create();
    }
}
