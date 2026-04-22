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

namespace WPPack\Plugin\PasskeyLoginPlugin\Activation;

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
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Storage\AaguidResolver;
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WPPack\Component\Site\BlogContextInterface;

/**
 * Public REST endpoints for passkey registration during account activation.
 *
 * Authenticated by a one-time activation token instead of login session.
 */
#[RestRoute(namespace: 'wppack/v1/passkey')]
#[Permission(public: true)]
final class PasskeyActivationController extends AbstractRestController
{
    public function __construct(
        private readonly CeremonyManager $ceremony,
        private readonly CredentialRepositoryInterface $repository,
        private readonly PasskeyConfiguration $config,
        private readonly PasskeyActivationPrompt $activationPrompt,
        private readonly LoggerInterface $logger,
        private readonly ?BlogContextInterface $blogContext = null,
    ) {}

    #[RestRoute(route: '/activate/options', methods: HttpMethod::POST)]
    public function options(\WP_REST_Request $request): JsonResponse
    {
        $params = $request->get_json_params();
        $token = $params['activationToken'] ?? '';
        $userId = $this->activationPrompt->validateToken($token);

        if ($userId === null) {
            return $this->json(['error' => 'Invalid or expired activation token.'], 400);
        }

        $user = get_user_by('ID', $userId);
        if (!$user instanceof \WP_User) {
            return $this->json(['error' => 'User not found.'], 400);
        }

        $result = $this->ceremony->createRegistrationOptions($user);

        $serializer = $this->createSerializer();
        $json = $serializer->serialize($result['options'], 'json');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $decoded['challengeKey'] = $result['challengeKey'];

        return $this->json($decoded);
    }

    #[RestRoute(route: '/activate/verify', methods: HttpMethod::POST)]
    public function verify(\WP_REST_Request $request): JsonResponse
    {
        $params = $request->get_json_params();

        // Consume activation token (one-time use)
        $token = $params['activationToken'] ?? '';
        $tokenUserId = $this->activationPrompt->consumeToken($token);
        if ($tokenUserId === null) {
            return $this->json(['error' => 'Invalid or expired activation token.'], 400);
        }

        $challengeKey = $params['challengeKey'] ?? '';
        $challengeData = $this->ceremony->consumeChallenge($challengeKey);
        if ($challengeData === null || $challengeData['type'] !== 'registration') {
            return $this->json(['error' => 'Invalid or expired challenge.'], 400);
        }

        $userId = $challengeData['userId'] ?? null;
        if ($userId === null || $userId !== $tokenUserId) {
            return $this->json(['error' => 'Token and challenge user mismatch.'], 400);
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

            if ($this->repository->findByCredentialId($credentialId) !== null) {
                return $this->json(['error' => 'This credential is already registered.'], 409);
            }

            $aaguid = $source->aaguid->toString();
            $backupEligible = $response->attestationObject->authData->isBackupEligible();
            $deviceName = AaguidResolver::resolve($aaguid);

            $passkeyCredential = new PasskeyCredential(
                id: 0,
                userId: $userId,
                credentialId: $credentialId,
                publicKey: base64_encode($source->credentialPublicKey),
                counter: $source->counter,
                transports: array_values($source->transports),
                deviceName: $deviceName,
                aaguid: $aaguid,
                backupEligible: $backupEligible,
                createdAt: new \DateTimeImmutable(),
                lastUsedAt: null,
            );

            $this->repository->save($passkeyCredential);

            return $this->created(['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Passkey activation registration failed.', [
                'userId' => $userId,
                'exception' => $e,
            ]);

            return $this->json(['error' => 'Passkey registration failed.'], 400);
        }
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
        return (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))->create();
    }
}
