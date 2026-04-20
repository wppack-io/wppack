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

namespace WPPack\Component\Scim\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Scim\Exception\InvalidValueException;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\User\UserRepository;

#[CoversClass(ScimGroupRepository::class)]
final class ScimGroupRepositoryTest extends TestCase
{
    private ScimGroupRepository $repository;
    private UserRepository $users;
    private RoleProvider $roles;

    /** @var list<string> */
    private array $createdRoles = [];

    protected function setUp(): void
    {
        $this->users = new UserRepository();
        $this->roles = new RoleProvider();
        $this->repository = new ScimGroupRepository($this->users, $this->roles);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdRoles as $roleName) {
            \remove_role($roleName);
        }
        $this->createdRoles = [];
    }

    private function createRole(string $suffix, string $label): string
    {
        $name = 'scim_test_' . $suffix . '_' . \uniqid();
        $this->roles->add($name, $label, ['read' => true]);
        $this->createdRoles[] = $name;

        return $name;
    }

    private function insertUser(string $prefix): int
    {
        return (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);
    }

    // ── find / findAll ──────────────────────────────────────────────

    #[Test]
    public function findByNameReturnsRoleShape(): void
    {
        $name = $this->createRole('find', 'Find Role');

        $role = $this->repository->findByName($name);

        self::assertNotNull($role);
        self::assertSame('Find Role', $role['name']);
        self::assertArrayHasKey('capabilities', $role);
    }

    #[Test]
    public function findByNameReturnsNullForUnknown(): void
    {
        self::assertNull($this->repository->findByName('definitely-not-a-role-' . \uniqid()));
    }

    #[Test]
    public function findAllPaginatesRoles(): void
    {
        $this->createRole('a', 'Role A');
        $this->createRole('b', 'Role B');

        $page = $this->repository->findAll(startIndex: 1, count: 100);

        self::assertArrayHasKey('groups', $page);
        self::assertArrayHasKey('totalResults', $page);
        self::assertGreaterThanOrEqual(2, $page['totalResults']);
    }

    #[Test]
    public function findAllStartIndexSkipsLeadingEntries(): void
    {
        $all = $this->repository->findAll(1, 100);
        $total = $all['totalResults'];

        if ($total < 2) {
            self::markTestSkipped('Need at least 2 roles to test start-index offset.');
        }

        $offset = $this->repository->findAll(startIndex: 2, count: 100);

        self::assertCount($total - 1, $offset['groups']);
    }

    // ── create / update / delete ────────────────────────────────────

    #[Test]
    public function createAddsRole(): void
    {
        $name = 'scim_create_role_' . \uniqid();
        $this->createdRoles[] = $name;

        $this->repository->create($name, 'Created Role', ['read' => true]);

        $role = $this->repository->findByName($name);
        self::assertNotNull($role);
        self::assertSame('Created Role', $role['name']);
    }

    #[Test]
    public function updateChangesLabel(): void
    {
        $name = $this->createRole('update', 'Original Label');

        $this->repository->update($name, 'Updated Label');

        $role = $this->repository->findByName($name);
        self::assertSame('Updated Label', $role['name']);
    }

    #[Test]
    public function deleteRemovesRoleAndClearsMemberMeta(): void
    {
        $name = $this->createRole('delete', 'Delete Role');
        $userId = $this->insertUser('scim_del_member');
        $this->repository->addMember($name, $userId);

        $this->repository->delete($name);

        self::assertNull($this->repository->findByName($name));
        self::assertSame('', $this->users->getMeta($userId, ScimConstants::META_GROUP_PREFIX . $name, true));
    }

    // ── addMember / removeMember ────────────────────────────────────

    #[Test]
    public function addMemberPersistsGroupMeta(): void
    {
        $name = $this->createRole('add_member', 'Role');
        $userId = $this->insertUser('scim_add_member');

        $this->repository->addMember($name, $userId);

        self::assertSame(
            '1',
            $this->users->getMeta($userId, ScimConstants::META_GROUP_PREFIX . $name, true),
        );
    }

    #[Test]
    public function addMemberRejectsUnknownUser(): void
    {
        $name = $this->createRole('add_unknown', 'Role');

        $this->expectException(InvalidValueException::class);
        $this->repository->addMember($name, 999_999_999);
    }

    #[Test]
    public function removeMemberClearsGroupMeta(): void
    {
        $name = $this->createRole('remove', 'Role');
        $userId = $this->insertUser('scim_remove_member');
        $this->repository->addMember($name, $userId);

        $this->repository->removeMember($name, $userId);

        self::assertSame('', $this->users->getMeta($userId, ScimConstants::META_GROUP_PREFIX . $name, true));
    }

    #[Test]
    public function removeMemberRejectsUnknownUser(): void
    {
        $name = $this->createRole('remove_unknown', 'Role');

        $this->expectException(InvalidValueException::class);
        $this->repository->removeMember($name, 999_999_999);
    }

    // ── setMembers ──────────────────────────────────────────────────

    #[Test]
    public function setMembersAddsAndRemovesToMatchList(): void
    {
        $name = $this->createRole('set_members', 'Role');
        $alice = $this->insertUser('alice');
        $bob = $this->insertUser('bob');
        $carol = $this->insertUser('carol');

        $this->repository->setMembers($name, [$alice, $bob]);
        self::assertSame('1', $this->users->getMeta($alice, ScimConstants::META_GROUP_PREFIX . $name, true));
        self::assertSame('1', $this->users->getMeta($bob, ScimConstants::META_GROUP_PREFIX . $name, true));

        // Replace membership: carol in, alice out.
        $this->repository->setMembers($name, [$carol, $bob]);
        self::assertSame('', $this->users->getMeta($alice, ScimConstants::META_GROUP_PREFIX . $name, true));
        self::assertSame('1', $this->users->getMeta($bob, ScimConstants::META_GROUP_PREFIX . $name, true));
        self::assertSame('1', $this->users->getMeta($carol, ScimConstants::META_GROUP_PREFIX . $name, true));
    }

    #[Test]
    public function setMembersRejectsListContainingUnknownUser(): void
    {
        $name = $this->createRole('set_rejects', 'Role');
        $alice = $this->insertUser('alice');

        $this->expectException(InvalidValueException::class);
        $this->repository->setMembers($name, [$alice, 999_999_999]);
    }

    #[Test]
    public function setMembersValidatesBeforeMutating(): void
    {
        // Partial membership changes are forbidden — if any user is bad,
        // nothing is changed.
        $name = $this->createRole('set_atomic', 'Role');
        $alice = $this->insertUser('alice_atomic');

        try {
            $this->repository->setMembers($name, [$alice, 999_999_999]);
            self::fail('expected InvalidValueException');
        } catch (InvalidValueException) {
            // alice should not have been added because validation failed first.
            self::assertSame(
                '',
                $this->users->getMeta($alice, ScimConstants::META_GROUP_PREFIX . $name, true),
            );
        }
    }

    // ── membership queries ──────────────────────────────────────────

    #[Test]
    public function getMembersOfRoleReturnsUsersWithGroupMeta(): void
    {
        $name = $this->createRole('members', 'Role');
        $alice = $this->insertUser('alice');
        $bob = $this->insertUser('bob');
        $this->repository->addMember($name, $alice);
        $this->repository->addMember($name, $bob);

        $members = $this->repository->getMembersOfRole($name);

        $ids = array_map(static fn(\WP_User $u): int => $u->ID, $members);
        sort($ids);
        $expected = [$alice, $bob];
        sort($expected);
        self::assertSame($expected, $ids);
    }

    #[Test]
    public function getGroupNamesForUserReturnsSyncedRolesOnly(): void
    {
        $nameA = $this->createRole('gfu_a', 'A');
        $nameB = $this->createRole('gfu_b', 'B');
        $userId = $this->insertUser('group_member');

        $this->repository->addMember($nameA, $userId);
        $this->repository->addMember($nameB, $userId);

        $groups = $this->repository->getGroupNamesForUser($userId);

        sort($groups);
        $expected = [$nameA, $nameB];
        sort($expected);
        self::assertSame($expected, $groups);
    }

    #[Test]
    public function getGroupNamesForUserIgnoresOtherMeta(): void
    {
        $name = $this->createRole('isolation', 'Role');
        $userId = $this->insertUser('isolation');
        $this->users->updateMeta($userId, '_wppack_scim_title', 'Engineer');
        $this->users->updateMeta($userId, 'other_random_meta', 'value');
        $this->repository->addMember($name, $userId);

        $groups = $this->repository->getGroupNamesForUser($userId);

        self::assertSame([$name], $groups);
    }

    #[Test]
    public function getGroupNamesForUserReturnsEmptyWhenNoMemberships(): void
    {
        $userId = $this->insertUser('lonely');

        self::assertSame([], $this->repository->getGroupNamesForUser($userId));
    }
}
