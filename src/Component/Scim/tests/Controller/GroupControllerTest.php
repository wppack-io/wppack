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
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Scim\Controller\GroupController;
use WPPack\Component\Scim\Event\GroupDeletedEvent;
use WPPack\Component\Scim\Event\GroupMembershipChangedEvent;
use WPPack\Component\Scim\Event\GroupProvisionedEvent;
use WPPack\Component\Scim\Event\GroupUpdatedEvent;
use WPPack\Component\Scim\Mapping\GroupMapper;
use WPPack\Component\Scim\Patch\PatchProcessor;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Serialization\ScimGroupSerializer;
use WPPack\Component\User\UserRepository;

#[CoversClass(GroupController::class)]
final class GroupControllerTest extends TestCase
{
    private UserRepository $users;
    private ScimGroupRepository $groups;
    private ScimGroupSerializer $serializer;
    private CapturingDispatcher $dispatcher;

    /** @var list<string> */
    private array $createdRoles = [];

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        $this->users = new UserRepository();
        $this->groups = new ScimGroupRepository($this->users, new RoleProvider());
        $this->serializer = new ScimGroupSerializer(new GroupMapper());
        $this->dispatcher = new CapturingDispatcher();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdRoles as $role) {
            \remove_role($role);
        }
        foreach ($this->createdUserIds as $id) {
            $this->users->delete($id);
        }
        $this->createdRoles = [];
        $this->createdUserIds = [];
    }

    private function controller(bool $allowManagement = true): GroupController
    {
        return new GroupController(
            $this->groups,
            $this->serializer,
            new PatchProcessor(),
            $this->dispatcher,
            new Sanitizer(),
            maxResults: 100,
            baseUrl: 'https://example.test',
            allowGroupManagement: $allowManagement,
        );
    }

    private function insertUser(string $prefix): int
    {
        $id = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);
        $this->createdUserIds[] = $id;

        return $id;
    }

    private function registerRole(string $name): string
    {
        $this->createdRoles[] = $name;

        return $name;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function request(array $query = [], string $body = '', string $method = 'GET'): Request
    {
        return Request::create(
            'https://example.test/scim/v2/Groups',
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
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    // ── list / get ──────────────────────────────────────────────────

    #[Test]
    public function listReturnsListResponse(): void
    {
        $response = $this->controller()->list($this->request(['startIndex' => 1, 'count' => 5]));

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertSame([ScimConstants::LIST_RESPONSE_SCHEMA], $body['schemas']);
        self::assertGreaterThan(0, $body['totalResults']);
    }

    #[Test]
    public function getReturnsExistingRole(): void
    {
        $name = $this->registerRole('scim_group_get_' . \uniqid());
        \add_role($name, 'Get Role');

        $response = $this->controller()->get($name);

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertSame($name, $body['id']);
    }

    #[Test]
    public function getUnknownRoleReturns404(): void
    {
        $response = $this->controller()->get('definitely_not_a_role_' . \uniqid());

        self::assertSame(404, $response->statusCode);
    }

    // ── create ──────────────────────────────────────────────────────

    #[Test]
    public function createAddsGroupAndDispatchesProvisionedEvent(): void
    {
        $display = 'SCIM Group ' . \uniqid();

        $response = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));

        self::assertSame(201, $response->statusCode);
        $body = $this->decode($response);
        $this->registerRole($body['id']);

        self::assertSame($display, $body['displayName']);
        self::assertCount(1, $this->dispatcher->eventsOf(GroupProvisionedEvent::class));
        self::assertStringStartsWith(
            'https://example.test/scim/v2/Groups/',
            $response->headers['Location'],
        );
    }

    #[Test]
    public function createWithInitialMembersDispatchesMembershipEvent(): void
    {
        $alice = $this->insertUser('alice');
        $display = 'SCIM Group With Members ' . \uniqid();

        $response = $this->controller()->create($this->request(
            body: $this->jsonBody([
                'displayName' => $display,
                'members' => [['value' => (string) $alice]],
            ]),
            method: 'POST',
        ));

        self::assertSame(201, $response->statusCode);
        $this->registerRole($this->decode($response)['id']);

        self::assertCount(1, $this->dispatcher->eventsOf(GroupMembershipChangedEvent::class));
    }

    #[Test]
    public function createMissingDisplayNameReturns400(): void
    {
        $response = $this->controller()->create($this->request(body: '{}', method: 'POST'));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function createWithDuplicateDisplayNameReturns409(): void
    {
        $display = 'Dup Role ' . \uniqid();
        $first = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));
        $this->registerRole($this->decode($first)['id']);

        $response = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));

        self::assertSame(409, $response->statusCode);
    }

    #[Test]
    public function createMemberMissingNumericValueReturns400(): void
    {
        $display = 'Role Bad Member ' . \uniqid();
        $response = $this->controller()->create($this->request(
            body: $this->jsonBody([
                'displayName' => $display,
                'members' => [['display' => 'alice']], // no numeric 'value'
            ]),
            method: 'POST',
        ));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function createRejectedWhenGroupManagementDisabled(): void
    {
        $response = $this->controller(allowManagement: false)->create($this->request(
            body: $this->jsonBody(['displayName' => 'X']),
            method: 'POST',
        ));

        self::assertSame(403, $response->statusCode);
    }

    // ── replace ─────────────────────────────────────────────────────

    #[Test]
    public function replaceUpdatesDisplayNameAndMembershipEvent(): void
    {
        $display = 'Replace Role ' . \uniqid();
        $create = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));
        $roleName = $this->registerRole($this->decode($create)['id']);
        $alice = $this->insertUser('alice_replace');

        $response = $this->controller()->replace($roleName, $this->request(
            body: $this->jsonBody([
                'displayName' => $display . ' Updated',
                'members' => [['value' => (string) $alice]],
            ]),
            method: 'PUT',
        ));

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertSame($display . ' Updated', $body['displayName']);
        self::assertCount(1, $this->dispatcher->eventsOf(GroupMembershipChangedEvent::class));
        self::assertCount(1, $this->dispatcher->eventsOf(GroupUpdatedEvent::class));
    }

    #[Test]
    public function replaceUnknownRoleReturns404(): void
    {
        $response = $this->controller()->replace('not-real', $this->request(
            body: $this->jsonBody(['displayName' => 'any']),
            method: 'PUT',
        ));

        self::assertSame(404, $response->statusCode);
    }

    // ── patch ───────────────────────────────────────────────────────

    #[Test]
    public function patchReplacesDisplayName(): void
    {
        $display = 'Patch Role ' . \uniqid();
        $create = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));
        $roleName = $this->registerRole($this->decode($create)['id']);

        $response = $this->controller()->patch($roleName, $this->request(
            body: $this->jsonBody([
                'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
                'Operations' => [['op' => 'replace', 'path' => 'displayName', 'value' => 'Renamed']],
            ]),
            method: 'PATCH',
        ));

        self::assertSame(200, $response->statusCode);
        self::assertSame('Renamed', $this->decode($response)['displayName']);
    }

    #[Test]
    public function patchAddMembersDispatchesMembershipEvent(): void
    {
        $display = 'Patch Members Role ' . \uniqid();
        $create = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));
        $roleName = $this->registerRole($this->decode($create)['id']);
        $bob = $this->insertUser('bob_patch');

        $response = $this->controller()->patch($roleName, $this->request(
            body: $this->jsonBody([
                'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
                'Operations' => [[
                    'op' => 'add',
                    'path' => 'members',
                    'value' => [['value' => (string) $bob]],
                ]],
            ]),
            method: 'PATCH',
        ));

        self::assertSame(200, $response->statusCode);
        self::assertCount(1, $this->dispatcher->eventsOf(GroupMembershipChangedEvent::class));
    }

    #[Test]
    public function patchUnknownRoleReturns404(): void
    {
        $response = $this->controller()->patch('missing', $this->request(
            body: $this->jsonBody([
                'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
                'Operations' => [['op' => 'replace', 'path' => 'displayName', 'value' => 'x']],
            ]),
            method: 'PATCH',
        ));

        self::assertSame(404, $response->statusCode);
    }

    // ── delete ──────────────────────────────────────────────────────

    #[Test]
    public function deleteRemovesRoleAndDispatchesDeletedEvent(): void
    {
        $display = 'Delete Role ' . \uniqid();
        $create = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));
        $roleName = $this->decode($create)['id'];

        $response = $this->controller()->delete($roleName);

        self::assertSame(204, $response->statusCode);
        self::assertNull($this->groups->findByName($roleName));
        self::assertCount(1, $this->dispatcher->eventsOf(GroupDeletedEvent::class));
    }

    #[Test]
    public function deleteUnknownRoleReturns404(): void
    {
        $response = $this->controller()->delete('not-a-real-role');

        self::assertSame(404, $response->statusCode);
    }

    #[Test]
    public function deleteRejectedWhenManagementDisabled(): void
    {
        $display = 'Stubborn Role ' . \uniqid();
        $create = $this->controller()->create($this->request(
            body: $this->jsonBody(['displayName' => $display]),
            method: 'POST',
        ));
        $roleName = $this->registerRole($this->decode($create)['id']);

        $response = $this->controller(allowManagement: false)->delete($roleName);

        self::assertSame(403, $response->statusCode);
    }
}
