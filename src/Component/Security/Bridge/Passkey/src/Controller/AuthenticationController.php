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

namespace WpPack\Component\Security\Bridge\Passkey\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WpPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WpPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WpPack\Component\Site\BlogContextInterface;

/**
 * REST endpoints for passkey authentication (assertion ceremony).
 *
 * All endpoints are public: unauthenticated users need these to log in.
 */
#[RestRoute(namespace: 'wppack/v1/passkey')]
#[Permission(public: true)]
final class AuthenticationController extends AbstractRestController
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
     * Generate authentication options (challenge).
     */
    #[RestRoute(route: '/authenticate/options', methods: HttpMethod::POST)]
    public function options(\WP_REST_Request $request): JsonResponse
    {
        $result = $this->ceremony->createAuthenticationOptions();

        $serializer = $this->createSerializer();
        $json = $serializer->serialize($result['options'], 'json');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $decoded['challengeKey'] = $result['challengeKey'];

        return $this->json($decoded);
    }

    /**
     * Verify assertion response and establish a WordPress session.
     */
    #[RestRoute(route: '/authenticate/verify', methods: HttpMethod::POST)]
    public function verify(\WP_REST_Request $request): JsonResponse
    {
        $params = $request->get_json_params();

        $challengeKey = $params['challengeKey'] ?? '';
        $challengeData = $this->ceremony->consumeChallenge($challengeKey);
        if ($challengeData === null || $challengeData['type'] !== 'authentication') {
            return $this->json(['error' => 'Invalid or expired challenge.'], 400);
        }

        /** @var \Webauthn\PublicKeyCredentialRequestOptions $requestOptions */
        $requestOptions = $this->ceremony->deserializeOptions(
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
            if (!$response instanceof AuthenticatorAssertionResponse) {
                return $this->json(['error' => 'Invalid assertion response.'], 400);
            }

            $rawId = $credential->rawId;
            $credentialIdB64 = rtrim(strtr(base64_encode($rawId), '+/', '-_'), '=');
            $stored = $this->repository->findByCredentialId($credentialIdB64);

            if ($stored === null) {
                return $this->json(['error' => 'Unknown credential.'], 400);
            }

            $source = PublicKeyCredentialSource::create(
                publicKeyCredentialId: $rawId,
                type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                transports: $stored->transports,
                attestationType: 'none',
                trustPath: EmptyTrustPath::create(),
                aaguid: Uuid::fromString($stored->aaguid),
                credentialPublicKey: base64_decode($stored->publicKey),
                userHandle: (string) $stored->userId,
                counter: $stored->counter,
            );

            $rpId = $this->resolveRpId();

            $ceFactory = new CeremonyStepManagerFactory();
            $ceFactory->setSecuredRelyingPartyId([$rpId]);

            $validator = AuthenticatorAssertionResponseValidator::create($ceFactory->requestCeremony());
            $updatedSource = $validator->check(
                publicKeyCredentialSource: $source,
                authenticatorAssertionResponse: $response,
                publicKeyCredentialRequestOptions: $requestOptions,
                host: $rpId,
                userHandle: null,
            );

            $this->repository->updateCounter($stored->id, $updatedSource->counter);
            $this->repository->updateLastUsed($stored->id);

            $user = get_user_by('ID', $stored->userId);
            if (!$user instanceof \WP_User) {
                return $this->json(['error' => 'User not found.'], 400);
            }

            // Multisite: ensure the user is a member of the current blog
            if ($this->blogContext !== null && $this->blogContext->isMultisite()) {
                if (!is_user_member_of_blog($user->ID)) {
                    add_user_to_blog(
                        $this->blogContext->getCurrentBlogId(),
                        $user->ID,
                        get_option('default_role', 'subscriber'),
                    );
                }
            }

            $this->authenticationSession->login($user->ID, secure: is_ssl());

            return $this->json([
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Passkey authentication failed.', [
                'exception' => $e,
            ]);

            return $this->json(['error' => 'Passkey authentication failed.'], 400);
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
        $factory = new WebauthnSerializerFactory(AttestationStatementSupportManager::create());

        return $factory->create();
    }
}
