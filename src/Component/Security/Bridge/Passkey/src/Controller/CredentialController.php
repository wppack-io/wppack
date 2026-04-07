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

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WpPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;

/**
 * REST endpoints for managing the current user's passkey credentials.
 */

#[RestRoute(namespace: 'wppack/v1/passkey')]
#[Permission(callback: 'isLoggedIn')]
final class CredentialController extends AbstractRestController
{
    public function __construct(
        private readonly CredentialRepositoryInterface $repository,
        private readonly AuthenticationSession $authenticationSession,
    ) {}

    public function isLoggedIn(\WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    /**
     * List all passkey credentials for the current user.
     */
    #[RestRoute(route: '/credentials', methods: HttpMethod::GET)]
    public function list(\WP_REST_Request $request): JsonResponse
    {
        $userId = $this->authenticationSession->getCurrentUserId();
        $credentials = $this->repository->findByUserId($userId);

        return $this->json(array_map($this->serialize(...), $credentials));
    }

    /**
     * Update a passkey credential (e.g. rename device).
     */
    #[RestRoute(route: '/credentials/{id}', methods: HttpMethod::PUT, requirements: ['id' => '\d+'])]
    public function update(int $id, \WP_REST_Request $request): JsonResponse
    {
        $userId = $this->authenticationSession->getCurrentUserId();
        $credential = $this->findOwnedCredential($id, $userId);

        if ($credential === null) {
            return $this->json(['error' => 'Credential not found.'], 404);
        }

        $params = $request->get_json_params();
        $rawName = $params['deviceName'] ?? null;

        if (\is_string($rawName)) {
            $deviceName = trim($rawName);
            if ($deviceName !== '') {
                $this->repository->updateDeviceName($id, mb_substr($deviceName, 0, 255));
            }
        }

        return $this->json(['success' => true]);
    }

    /**
     * Delete a passkey credential.
     */
    #[RestRoute(route: '/credentials/{id}', methods: HttpMethod::DELETE, requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $userId = $this->authenticationSession->getCurrentUserId();
        $credential = $this->findOwnedCredential($id, $userId);

        if ($credential === null) {
            return $this->json(['error' => 'Credential not found.'], 404);
        }

        $this->repository->delete($id);

        return $this->json(['success' => true]);
    }

    /**
     * Find a credential that belongs to the given user.
     */
    private function findOwnedCredential(int $id, int $userId): ?PasskeyCredential
    {
        $credentials = $this->repository->findByUserId($userId);

        foreach ($credentials as $credential) {
            if ($credential->id === $id) {
                return $credential;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PasskeyCredential $credential): array
    {
        return [
            'id' => $credential->id,
            'credentialId' => $credential->credentialId,
            'deviceName' => $credential->deviceName,
            'aaguid' => $credential->aaguid,
            'backupEligible' => $credential->backupEligible,
            'transports' => $credential->transports,
            'createdAt' => $credential->createdAt->format(\DateTimeInterface::ATOM),
            'lastUsedAt' => $credential->lastUsedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
