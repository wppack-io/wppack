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

namespace WPPack\Component\Scim\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\EventDispatcher\Event;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Scim\Controller\UserController;
use WPPack\Component\Scim\Event\UserDeactivatedEvent;
use WPPack\Component\Scim\Event\UserDeletedEvent;
use WPPack\Component\Scim\Event\UserProvisionedEvent;
use WPPack\Component\Scim\Event\UserReactivatedEvent;
use WPPack\Component\Scim\Event\UserUpdatedEvent;
use WPPack\Component\Scim\Filter\FilterParser;
use WPPack\Component\Scim\Filter\WpUserQueryAdapter;
use WPPack\Component\Scim\Mapping\UserAttributeMapper;
use WPPack\Component\Scim\Patch\PatchProcessor;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Repository\ScimUserRepository;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Serialization\ScimUserSerializer;
use WPPack\Component\User\UserRepository;

#[CoversClass(UserController::class)]
final class UserControllerTest extends TestCase
{
    private UserRepository $users;
    private ScimUserRepository $scimUsers;
    private UserAttributeMapper $mapper;
    private ScimUserSerializer $serializer;
    private CapturingDispatcher $dispatcher;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        $eventDispatcher = new EventDispatcher();
        $this->users = new UserRepository();
        $this->scimUsers = new ScimUserRepository(
            $this->users,
            new WpUserQueryAdapter($this->users),
        );
        $this->mapper = new UserAttributeMapper($this->users, new Sanitizer(), $eventDispatcher, []);
        $this->serializer = new ScimUserSerializer(
            $this->mapper,
            new RoleProvider(),
            new ScimGroupRepository($this->users, new RoleProvider()),
        );
        $this->dispatcher = new CapturingDispatcher();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $id) {
            $this->users->delete($id);
        }
        $this->createdUserIds = [];
    }

    private function controller(bool $allowDelete = false, bool $autoProvision = true): UserController
    {
        return new UserController(
            $this->scimUsers,
            $this->mapper,
            $this->serializer,
            new PatchProcessor(),
            $this->dispatcher,
            new FilterParser(),
            maxResults: 50,
            baseUrl: 'https://example.test',
            defaultRole: 'subscriber',
            allowUserDeletion: $allowDelete,
            autoProvision: $autoProvision,
        );
    }

    private function registerUserId(int $id): int
    {
        $this->createdUserIds[] = $id;

        return $id;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function makeRequest(array $query = [], string $body = '', string $method = 'GET'): Request
    {
        return Request::create(
            'https://example.test/scim/v2/Users',
            method: $method,
            parameters: $query,
            server: ['CONTENT_TYPE' => 'application/scim+json'],
            content: $body,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonBody(array $payload): string
    {
        return json_encode($payload, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(\WPPack\Component\HttpFoundation\Response $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    // ── list ─────────────────────────────────────────────────────────

    #[Test]
    public function listReturnsUsersWithListResponseSchema(): void
    {
        $this->registerUserId((int) \wp_insert_user([
            'user_login' => 'scim_list_' . \uniqid(),
            'user_email' => 'scim_list_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        $response = $this->controller()->list($this->makeRequest(['startIndex' => 1, 'count' => 5]));

        self::assertSame(200, $response->statusCode);
        $body = $this->decodeResponse($response);
        self::assertSame([ScimConstants::LIST_RESPONSE_SCHEMA], $body['schemas']);
        self::assertGreaterThanOrEqual(1, $body['totalResults']);
    }

    #[Test]
    public function listClampsCountToMaxResults(): void
    {
        $response = $this->controller()->list($this->makeRequest(['count' => 9999]));
        $body = $this->decodeResponse($response);

        self::assertLessThanOrEqual(50, $body['itemsPerPage']);
    }

    #[Test]
    public function listRejectsInvalidFilterWith400ErrorShape(): void
    {
        $response = $this->controller()->list($this->makeRequest(['filter' => 'userName notARealOp "alice"']));

        self::assertSame(400, $response->statusCode);
        $body = $this->decodeResponse($response);
        self::assertSame('invalidFilter', $body['scimType']);
    }

    // ── get ─────────────────────────────────────────────────────────

    #[Test]
    public function getReturnsResource(): void
    {
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => 'scim_get_' . \uniqid(),
            'user_email' => 'scim_get_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        $response = $this->controller()->get($userId);

        self::assertSame(200, $response->statusCode);
        self::assertSame((string) $userId, $this->decodeResponse($response)['id']);
    }

    #[Test]
    public function getUnknownUserReturns404(): void
    {
        $response = $this->controller()->get(999_999_999);

        self::assertSame(404, $response->statusCode);
    }

    // ── create ──────────────────────────────────────────────────────

    #[Test]
    public function createProvisionsUserAndDispatchesEvent(): void
    {
        $login = 'scim_create_' . \uniqid();
        $response = $this->controller()->create($this->makeRequest(body: $this->jsonBody([
            'userName' => $login,
            'emails' => [['value' => $login . '@example.com', 'primary' => true, 'type' => 'work']],
        ])));

        self::assertSame(201, $response->statusCode);
        $body = $this->decodeResponse($response);
        self::assertSame($login, $body['userName']);
        self::assertSame(
            'https://example.test/scim/v2/Users/' . $body['id'],
            $response->headers['Location'],
        );

        $this->registerUserId((int) $body['id']);

        self::assertCount(1, $this->dispatcher->eventsOf(UserProvisionedEvent::class));
    }

    #[Test]
    public function createMissingUserNameReturns400(): void
    {
        $response = $this->controller()->create($this->makeRequest(body: $this->jsonBody([
            'emails' => [['value' => 'x@example.com']],
        ])));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function createMissingEmailsReturns400(): void
    {
        $response = $this->controller()->create($this->makeRequest(body: $this->jsonBody([
            'userName' => 'x',
        ])));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function createWithDuplicateUserNameReturns409(): void
    {
        $login = 'scim_dup_' . \uniqid();
        $this->registerUserId((int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        $response = $this->controller()->create($this->makeRequest(body: $this->jsonBody([
            'userName' => $login,
            'emails' => [['value' => $login . '@example.com']],
        ])));

        self::assertSame(409, $response->statusCode);
        self::assertSame('uniqueness', $this->decodeResponse($response)['scimType']);
    }

    #[Test]
    public function createWithDuplicateExternalIdReturns409(): void
    {
        $ext = 'okta-dup-' . \uniqid();
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => 'scim_extid_' . \uniqid(),
            'user_email' => 'scim_extid_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));
        $this->users->updateMeta($userId, ScimConstants::META_EXTERNAL_ID, $ext);

        $login = 'scim_newuser_' . \uniqid();
        $response = $this->controller()->create($this->makeRequest(body: $this->jsonBody([
            'userName' => $login,
            'emails' => [['value' => $login . '@example.com']],
            'externalId' => $ext,
        ])));

        self::assertSame(409, $response->statusCode);
    }

    #[Test]
    public function createReturns403WhenAutoProvisionDisabled(): void
    {
        $response = $this->controller(autoProvision: false)->create($this->makeRequest(body: $this->jsonBody([
            'userName' => 'any',
            'emails' => [['value' => 'any@example.com']],
        ])));

        self::assertSame(403, $response->statusCode);
    }

    #[Test]
    public function createWithActiveFalseDeactivatesNewUser(): void
    {
        $login = 'scim_inactive_' . \uniqid();
        $response = $this->controller()->create($this->makeRequest(body: $this->jsonBody([
            'userName' => $login,
            'emails' => [['value' => $login . '@example.com']],
            'active' => false,
        ])));

        self::assertSame(201, $response->statusCode);
        $userId = (int) $this->decodeResponse($response)['id'];
        $this->registerUserId($userId);

        self::assertFalse($this->scimUsers->isActive($userId));
        self::assertCount(1, $this->dispatcher->eventsOf(UserProvisionedEvent::class));
        self::assertCount(1, $this->dispatcher->eventsOf(UserDeactivatedEvent::class));
    }

    // ── replace ─────────────────────────────────────────────────────

    #[Test]
    public function replaceUpdatesUserAndDispatchesUpdatedEvent(): void
    {
        $login = 'scim_replace_' . \uniqid();
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        $response = $this->controller()->replace($userId, $this->makeRequest(
            body: $this->jsonBody([
                'userName' => $login,
                'emails' => [['value' => $login . '@example.com']],
                'displayName' => 'Updated Name',
            ]),
            method: 'PUT',
        ));

        self::assertSame(200, $response->statusCode);
        self::assertSame('Updated Name', $this->decodeResponse($response)['displayName']);
        self::assertCount(1, $this->dispatcher->eventsOf(UserUpdatedEvent::class));
    }

    #[Test]
    public function replaceRejectsUserNameChangeWith400Mutability(): void
    {
        $login = 'scim_mutable_' . \uniqid();
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        $response = $this->controller()->replace($userId, $this->makeRequest(
            body: $this->jsonBody(['userName' => 'different']),
            method: 'PUT',
        ));

        self::assertSame(400, $response->statusCode);
        self::assertSame('mutability', $this->decodeResponse($response)['scimType']);
    }

    #[Test]
    public function replaceToggleActiveFlagDispatchesDeactivatedThenReactivated(): void
    {
        $login = 'scim_toggle_' . \uniqid();
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        // Deactivate
        $this->controller()->replace($userId, $this->makeRequest(
            body: $this->jsonBody(['userName' => $login, 'emails' => [['value' => $login . '@example.com']], 'active' => false]),
            method: 'PUT',
        ));
        self::assertCount(1, $this->dispatcher->eventsOf(UserDeactivatedEvent::class));

        // Reactivate
        $this->controller()->replace($userId, $this->makeRequest(
            body: $this->jsonBody(['userName' => $login, 'emails' => [['value' => $login . '@example.com']], 'active' => true]),
            method: 'PUT',
        ));
        self::assertCount(1, $this->dispatcher->eventsOf(UserReactivatedEvent::class));
    }

    #[Test]
    public function replaceUnknownUserReturns404(): void
    {
        $response = $this->controller()->replace(999_999_999, $this->makeRequest(
            body: $this->jsonBody(['userName' => 'x']),
            method: 'PUT',
        ));

        self::assertSame(404, $response->statusCode);
    }

    // ── patch ───────────────────────────────────────────────────────

    #[Test]
    public function patchAppliesReplaceOperation(): void
    {
        $login = 'scim_patch_' . \uniqid();
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
            'display_name' => 'Old',
        ]));

        $response = $this->controller()->patch($userId, $this->makeRequest(
            body: $this->jsonBody([
                'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
                'Operations' => [['op' => 'replace', 'path' => 'displayName', 'value' => 'New']],
            ]),
            method: 'PATCH',
        ));

        self::assertSame(200, $response->statusCode);
        self::assertSame('New', $this->decodeResponse($response)['displayName']);
    }

    #[Test]
    public function patchUnknownUserReturns404(): void
    {
        $response = $this->controller()->patch(999_999_999, $this->makeRequest(
            body: $this->jsonBody(['schemas' => [ScimConstants::PATCH_OP_SCHEMA], 'Operations' => [['op' => 'add', 'value' => ['nickName' => 'x']]]]),
            method: 'PATCH',
        ));

        self::assertSame(404, $response->statusCode);
    }

    // ── delete ──────────────────────────────────────────────────────

    #[Test]
    public function deleteDeactivatesWhenDeletionNotAllowed(): void
    {
        $login = 'scim_softdel_' . \uniqid();
        $userId = $this->registerUserId((int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]));

        $response = $this->controller(allowDelete: false)->delete($userId);

        self::assertSame(204, $response->statusCode);
        self::assertFalse($this->scimUsers->isActive($userId));
        self::assertCount(1, $this->dispatcher->eventsOf(UserDeactivatedEvent::class));
        self::assertCount(0, $this->dispatcher->eventsOf(UserDeletedEvent::class));
    }

    #[Test]
    public function deleteHardDeletesWhenAllowed(): void
    {
        $login = 'scim_harddel_' . \uniqid();
        $userId = (int) \wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);

        $response = $this->controller(allowDelete: true)->delete($userId);

        self::assertSame(204, $response->statusCode);
        self::assertNull($this->scimUsers->find($userId));
        self::assertCount(1, $this->dispatcher->eventsOf(UserDeletedEvent::class));
    }

    #[Test]
    public function deleteUnknownUserReturns404(): void
    {
        $response = $this->controller()->delete(999_999_999);

        self::assertSame(404, $response->statusCode);
    }
}

/**
 * In-memory event dispatcher that records dispatched events for
 * later assertion. Matches Psr\EventDispatcher\EventDispatcherInterface.
 */
final class CapturingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<object>
     */
    public function eventsOf(string $class): array
    {
        return array_values(array_filter(
            $this->events,
            static fn(object $event): bool => $event instanceof $class,
        ));
    }
}
