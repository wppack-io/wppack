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
use WpPack\Component\Scim\Event\UserDeactivatedEvent;
use WpPack\Component\Scim\Event\UserDeletedEvent;
use WpPack\Component\Scim\Event\UserProvisionedEvent;
use WpPack\Component\Scim\Event\UserReactivatedEvent;
use WpPack\Component\Scim\Event\UserUpdatedEvent;
use WpPack\Component\Scim\Exception\InvalidValueException;
use WpPack\Component\Scim\Exception\MutabilityException;
use WpPack\Component\Scim\Exception\ResourceConflictException;
use WpPack\Component\Scim\Exception\ResourceNotFoundException;
use WpPack\Component\Scim\Exception\ScimException;
use WpPack\Component\Scim\Filter\FilterParser;
use WpPack\Component\Scim\Mapping\UserAttributeMapperInterface;
use WpPack\Component\Scim\Patch\PatchProcessor;
use WpPack\Component\Scim\Patch\PatchRequest;
use WpPack\Component\Scim\Repository\ScimUserRepository;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\Scim\Serialization\ErrorSerializer;
use WpPack\Component\Scim\Serialization\ListResponseSerializer;
use WpPack\Component\Scim\Serialization\ScimUserSerializer;

#[RestRoute(namespace: 'scim/v2', route: '/Users')]
#[IsGranted(ScimConstants::CAPABILITY_PROVISION)]
final class UserController extends AbstractRestController
{
    use ScimBodyDecoderTrait;

    public function __construct(
        private readonly ScimUserRepository $userRepository,
        private readonly UserAttributeMapperInterface $mapper,
        private readonly ScimUserSerializer $serializer,
        private readonly PatchProcessor $patchProcessor,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly FilterParser $filterParser,
        private readonly int $maxResults = 100,
        private readonly string $baseUrl = '',
        private readonly string $defaultRole = 'subscriber',
        private readonly bool $allowUserDeletion = false,
        private readonly bool $autoProvision = true,
    ) {}

    #[RestRoute(methods: [HttpMethod::GET])]
    public function list(Request $request): JsonResponse
    {
        try {
            $filter = $request->query->get('filter');
            $startIndex = max(1, $request->query->getInt('startIndex', 1));
            $count = min($this->maxResults, max(1, $request->query->getInt('count', $this->maxResults)));

            $filterNode = null;
            if ($filter !== null && $filter !== '') {
                $filterNode = $this->filterParser->parse($filter);
            }

            $result = $this->userRepository->findFiltered($filterNode, $startIndex, $count);

            $resources = [];
            foreach ($result['users'] as $user) {
                $resources[] = $this->serializer->serialize($user, $this->baseUrl);
            }

            return $this->json(
                ListResponseSerializer::serialize($resources, $result['totalResults'], $startIndex, $count),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::GET], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        try {
            $user = $this->userRepository->find($id);

            if ($user === null) {
                throw new ResourceNotFoundException(sprintf('User "%d" not found.', $id));
            }

            return $this->json(
                $this->serializer->serialize($user, $this->baseUrl),
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
            if (!$this->autoProvision) {
                throw new ScimException('User provisioning is disabled.', 403);
            }

            $body = $this->decodeBody($request);

            if (!isset($body['userName'])) {
                throw new InvalidValueException('userName is required.');
            }

            $mapped = $this->mapper->toWordPress($body);
            $this->validateMappedEmail($mapped);

            if (isset($body['externalId'])) {
                $byExtId = $this->userRepository->findByExternalId($body['externalId']);
                if ($byExtId !== null) {
                    throw new ResourceConflictException(sprintf('User with externalId "%s" already exists.', $body['externalId']));
                }
            }

            // Check if user_login already exists
            $existingUser = get_user_by('login', $mapped['data']['user_login'] ?? '');
            if ($existingUser !== false) {
                throw new ResourceConflictException(sprintf('User with userName "%s" already exists.', $body['userName']));
            }

            // Assign default role
            if (!isset($mapped['data']['role'])) {
                $mapped['data']['role'] = $this->defaultRole;
            }

            $userId = $this->userRepository->create($mapped['data'], $mapped['meta']);
            $user = $this->userRepository->find($userId);

            if ($user === null) {
                throw new ScimException('Failed to create user.', 500);
            }

            $this->dispatcher->dispatch(new UserProvisionedEvent($user, $body));

            // Deactivate if provisioned with active=false
            if (isset($body['active']) && $body['active'] === false) {
                $this->userRepository->deactivate($userId);
                $user = $this->userRepository->find($userId);
                if ($user === null) {
                    throw new ScimException('Failed to retrieve user after deactivation.', 500);
                }
                $this->dispatcher->dispatch(new UserDeactivatedEvent($user));
            }

            return $this->json(
                $this->serializer->serialize($user, $this->baseUrl),
                201,
                ['Content-Type' => ScimConstants::CONTENT_TYPE, 'Location' => $this->baseUrl . '/scim/v2/Users/' . $userId],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::PUT], requirements: ['id' => '\d+'])]
    public function replace(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->userRepository->find($id);

            if ($user === null) {
                throw new ResourceNotFoundException(sprintf('User "%d" not found.', $id));
            }

            $body = $this->decodeBody($request);

            // userName immutability check
            if (isset($body['userName']) && $body['userName'] !== $user->user_login) {
                throw new MutabilityException('userName is immutable after creation.');
            }

            // externalId uniqueness check
            if (isset($body['externalId'])) {
                $byExtId = $this->userRepository->findByExternalId($body['externalId']);
                if ($byExtId !== null && $byExtId->ID !== $id) {
                    throw new ResourceConflictException(sprintf('User with externalId "%s" already exists.', $body['externalId']));
                }
            }

            $wasActive = $this->isActive($user);
            $mapped = $this->mapper->toWordPress($body);
            $this->validateMappedEmail($mapped);
            $this->userRepository->update($id, $mapped['data'], $mapped['meta']);

            $this->handleActiveFlag($id, $body, $wasActive);

            $updatedUser = $this->userRepository->find($id);
            if ($updatedUser === null) {
                throw new ScimException('Failed to retrieve updated user.', 500);
            }

            $this->dispatcher->dispatch(new UserUpdatedEvent($updatedUser, $body));

            return $this->json(
                $this->serializer->serialize($updatedUser, $this->baseUrl),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::PATCH], requirements: ['id' => '\d+'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->userRepository->find($id);

            if ($user === null) {
                throw new ResourceNotFoundException(sprintf('User "%d" not found.', $id));
            }

            $body = $this->decodeBody($request);
            $patchRequest = PatchRequest::fromArray($body);

            // Get current SCIM representation, apply patches, then map back
            $currentScim = $this->mapper->toScim($user);
            $patched = $this->patchProcessor->apply($currentScim, $patchRequest);

            $wasActive = $this->isActive($user);
            $mapped = $this->mapper->toWordPress($patched);
            $this->validateMappedEmail($mapped);
            $this->userRepository->update($id, $mapped['data'], $mapped['meta']);

            $this->handleActiveFlag($id, $patched, $wasActive);

            $updatedUser = $this->userRepository->find($id);
            if ($updatedUser === null) {
                throw new ScimException('Failed to retrieve updated user.', 500);
            }

            $this->dispatcher->dispatch(new UserUpdatedEvent($updatedUser, $patched));

            return $this->json(
                $this->serializer->serialize($updatedUser, $this->baseUrl),
                headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::DELETE], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        try {
            $user = $this->userRepository->find($id);

            if ($user === null) {
                throw new ResourceNotFoundException(sprintf('User "%d" not found.', $id));
            }

            if ($this->allowUserDeletion) {
                $userLogin = $user->user_login;
                $this->userRepository->delete($id);
                $this->dispatcher->dispatch(new UserDeletedEvent($id, $userLogin));
            } else {
                $this->userRepository->deactivate($id);
                $this->dispatcher->dispatch(new UserDeactivatedEvent($user));
            }

            return $this->noContent();
        } catch (ScimException $e) {
            return $this->json(ErrorSerializer::fromException($e), $e->getHttpStatus(), ['Content-Type' => ScimConstants::CONTENT_TYPE]);
        }
    }

    /**
     * @param array{data: array<string, mixed>, meta: array<string, mixed>} $mapped
     */
    private function validateMappedEmail(array $mapped): void
    {
        $email = $mapped['data']['user_email'] ?? '';
        if ($email !== '' && !is_email($email)) {
            throw new InvalidValueException('A valid email address is required.');
        }
    }

    private function isActive(\WP_User $user): bool
    {
        $meta = get_user_meta($user->ID, ScimConstants::META_ACTIVE, true);

        return $meta !== '0';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function handleActiveFlag(int $id, array $data, bool $wasActive): void
    {
        if (!isset($data['active'])) {
            return;
        }

        if ($data['active'] === false && $wasActive) {
            $this->userRepository->deactivate($id);
            $user = $this->userRepository->find($id);
            if ($user !== null) {
                $this->dispatcher->dispatch(new UserDeactivatedEvent($user));
            }
        } elseif ($data['active'] === true && !$wasActive) {
            $this->userRepository->reactivate($id, $this->defaultRole);
            $user = $this->userRepository->find($id);
            if ($user !== null) {
                $this->dispatcher->dispatch(new UserReactivatedEvent($user));
            }
        }
    }
}
