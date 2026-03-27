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

namespace WpPack\Component\Scim\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Scim\Event\GroupDeletedEvent;
use WpPack\Component\Scim\Event\GroupMembershipChangedEvent;
use WpPack\Component\Scim\Event\GroupProvisionedEvent;
use WpPack\Component\Scim\Event\GroupUpdatedEvent;
use WpPack\Component\Scim\Exception\InvalidValueException;
use WpPack\Component\Scim\Exception\ResourceConflictException;
use WpPack\Component\Scim\Exception\ResourceNotFoundException;
use WpPack\Component\Scim\Exception\ScimException;
use WpPack\Component\Scim\Patch\PatchProcessor;
use WpPack\Component\Scim\Patch\PatchRequest;
use WpPack\Component\Scim\Repository\ScimGroupRepository;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\Scim\Serialization\ErrorSerializer;
use WpPack\Component\Scim\Serialization\ListResponseSerializer;
use WpPack\Component\Scim\Serialization\ScimGroupSerializer;

#[RestRoute(namespace: 'scim/v2', route: '/Groups')]
#[IsGranted(ScimConstants::CAPABILITY_PROVISION)]
final class GroupController extends AbstractRestController
{
    use ScimBodyDecoderTrait;

    public function __construct(
        private readonly ScimGroupRepository $groupRepository,
        private readonly ScimGroupSerializer $serializer,
        private readonly PatchProcessor $patchProcessor,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly int $maxResults = 100,
        private readonly string $baseUrl = '',
        private readonly bool $allowGroupManagement = true,
    ) {}

    #[RestRoute(methods: [HttpMethod::GET])]
    public function list(Request $request): JsonResponse
    {
        try {
            $startIndex = max(1, $request->query->getInt('startIndex', 1));
            $count = min($this->maxResults, max(1, $request->query->getInt('count', $this->maxResults)));

            $result = $this->groupRepository->findAll($startIndex, $count);

            $resources = [];
            foreach ($result['groups'] as $group) {
                $resources[] = $this->serializer->serialize(
                    $group['roleName'],
                    $group['role'],
                    $group['members'],
                    $this->baseUrl,
                );
            }

            return $this->json(
                ListResponseSerializer::serialize($resources, $result['totalResults'], $startIndex, $count),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::GET])]
    public function get(string $id): JsonResponse
    {
        try {
            $role = $this->groupRepository->findByName($id);

            if ($role === null) {
                throw new ResourceNotFoundException(sprintf('Group "%s" not found.', $id));
            }

            $members = $this->groupRepository->getMembersOfRole($id);

            return $this->json(
                $this->serializer->serialize($id, $role, $members, $this->baseUrl),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(methods: [HttpMethod::POST])]
    public function create(Request $request): JsonResponse
    {
        try {
            $this->ensureGroupManagementAllowed();
            $body = $this->decodeBody($request);

            if (!isset($body['displayName'])) {
                throw new InvalidValueException('displayName is required.');
            }

            $roleName = sanitize_key($body['displayName']);

            if ($roleName === '') {
                throw new InvalidValueException('displayName must produce a valid role name.');
            }

            if ($this->groupRepository->findByName($roleName) !== null) {
                throw new ResourceConflictException(sprintf('Group "%s" already exists.', $roleName));
            }

            $this->groupRepository->create($roleName, $body['displayName']);

            // Handle initial members
            if (isset($body['members']) && \is_array($body['members'])) {
                $memberIds = self::extractMemberIds($body['members']);
                $this->groupRepository->setMembers($roleName, $memberIds);

                $this->dispatcher->dispatch(new GroupMembershipChangedEvent($roleName, $memberIds, []));
            }

            $this->dispatcher->dispatch(new GroupProvisionedEvent($roleName, $body['displayName']));

            $role = $this->groupRepository->findByName($roleName);
            $members = $this->groupRepository->getMembersOfRole($roleName);

            return $this->json(
                $this->serializer->serialize($roleName, $role ?? ['name' => $body['displayName'], 'capabilities' => []], $members, $this->baseUrl),
                201,
                ['Content-Type' => ScimConstants::CONTENT_TYPE, 'Location' => $this->baseUrl . '/scim/v2/Groups/' . $roleName],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::PUT])]
    public function replace(string $id, Request $request): JsonResponse
    {
        try {
            $this->ensureGroupManagementAllowed();
            $role = $this->groupRepository->findByName($id);

            if ($role === null) {
                throw new ResourceNotFoundException(sprintf('Group "%s" not found.', $id));
            }

            $body = $this->decodeBody($request);

            if (isset($body['displayName'])) {
                $this->groupRepository->update($id, $body['displayName']);
            }

            // Replace members
            if (isset($body['members']) && \is_array($body['members'])) {
                $currentMembers = $this->groupRepository->getMembersOfRole($id);
                $currentIds = array_map(static fn(\WP_User $u): int => $u->ID, $currentMembers);

                $newMemberIds = self::extractMemberIds($body['members']);
                $this->groupRepository->setMembers($id, $newMemberIds);

                $added = array_values(array_diff($newMemberIds, $currentIds));
                $removed = array_values(array_diff($currentIds, $newMemberIds));

                if ($added !== [] || $removed !== []) {
                    $this->dispatcher->dispatch(new GroupMembershipChangedEvent($id, $added, $removed));
                }
            }

            $this->dispatcher->dispatch(new GroupUpdatedEvent($id, $body));

            $updatedRole = $this->groupRepository->findByName($id);
            $members = $this->groupRepository->getMembersOfRole($id);

            return $this->json(
                $this->serializer->serialize($id, $updatedRole ?? $role, $members, $this->baseUrl),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::PATCH])]
    public function patch(string $id, Request $request): JsonResponse
    {
        try {
            $this->ensureGroupManagementAllowed();
            $role = $this->groupRepository->findByName($id);

            if ($role === null) {
                throw new ResourceNotFoundException(sprintf('Group "%s" not found.', $id));
            }

            $body = $this->decodeBody($request);
            $patchRequest = PatchRequest::fromArray($body);

            $currentMembers = $this->groupRepository->getMembersOfRole($id);
            $currentMemberIds = array_map(static fn(\WP_User $u): int => $u->ID, $currentMembers);

            // Build current SCIM representation and apply patches
            $currentScim = [
                'id' => $id,
                'displayName' => $role['name'],
                'members' => array_map(
                    static fn(\WP_User $u): array => ['value' => (string) $u->ID, 'display' => $u->display_name],
                    $currentMembers,
                ),
            ];

            $patched = $this->patchProcessor->apply($currentScim, $patchRequest);

            // Update displayName if changed
            if (isset($patched['displayName']) && $patched['displayName'] !== $role['name']) {
                $this->groupRepository->update($id, $patched['displayName']);
            }

            // Update members if changed
            if (isset($patched['members']) && \is_array($patched['members'])) {
                $newMemberIds = self::extractMemberIds($patched['members']);
                $this->groupRepository->setMembers($id, $newMemberIds);

                $added = array_values(array_diff($newMemberIds, $currentMemberIds));
                $removed = array_values(array_diff($currentMemberIds, $newMemberIds));

                if ($added !== [] || $removed !== []) {
                    $this->dispatcher->dispatch(new GroupMembershipChangedEvent($id, $added, $removed));
                }
            }

            $this->dispatcher->dispatch(new GroupUpdatedEvent($id, $patched));

            $updatedRole = $this->groupRepository->findByName($id);
            $members = $this->groupRepository->getMembersOfRole($id);

            return $this->json(
                $this->serializer->serialize($id, $updatedRole ?? $role, $members, $this->baseUrl),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::DELETE])]
    public function delete(string $id): Response
    {
        try {
            $this->ensureGroupManagementAllowed();
            $role = $this->groupRepository->findByName($id);

            if ($role === null) {
                throw new ResourceNotFoundException(sprintf('Group "%s" not found.', $id));
            }

            $this->groupRepository->delete($id);

            $this->dispatcher->dispatch(new GroupDeletedEvent($id));

            return $this->noContent();
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    /**
     * @param list<array<string, mixed>> $members
     *
     * @return list<int>
     */
    private static function extractMemberIds(array $members): array
    {
        $ids = [];
        foreach ($members as $member) {
            if (!isset($member['value']) || $member['value'] === '' || !ctype_digit((string) $member['value'])) {
                throw new InvalidValueException('Each member must have a numeric "value" attribute.');
            }
            $ids[] = (int) $member['value'];
        }

        return $ids;
    }

    private function ensureGroupManagementAllowed(): void
    {
        if (!$this->allowGroupManagement) {
            throw new ScimException('Group management is disabled.', 403);
        }
    }
}
