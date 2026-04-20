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
use WPPack\Component\Scim\Filter\FilterParser;
use WPPack\Component\Scim\Filter\WpUserQueryAdapter;
use WPPack\Component\Scim\Repository\ScimUserRepository;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\User\UserRepository;

#[CoversClass(ScimUserRepository::class)]
final class ScimUserRepositoryTest extends TestCase
{
    private ScimUserRepository $repository;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->users = new UserRepository();
        $this->repository = new ScimUserRepository(
            $this->users,
            new WpUserQueryAdapter($this->users),
        );
    }

    private function insertUser(string $prefix, string $externalId = ''): int
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
            'display_name' => ucfirst($prefix),
        ]);

        if ($externalId !== '') {
            $this->users->updateMeta($userId, ScimConstants::META_EXTERNAL_ID, $externalId);
        }

        return $userId;
    }

    // ── find / findByLogin / findByExternalId ───────────────────────

    #[Test]
    public function findDelegatesToUserRepository(): void
    {
        $userId = $this->insertUser('scim_find');

        $user = $this->repository->find($userId);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function findReturnsNullForUnknownId(): void
    {
        self::assertNull($this->repository->find(999_999_999));
    }

    #[Test]
    public function findByLoginResolvesExistingUser(): void
    {
        $userId = $this->insertUser('scim_login');
        $login = (new \WP_User($userId))->user_login;

        $user = $this->repository->findByLogin($login);

        self::assertNotNull($user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function findByExternalIdReturnsMatchingUser(): void
    {
        $ext = 'okta-' . \uniqid();
        $userId = $this->insertUser('scim_ext', $ext);

        $user = $this->repository->findByExternalId($ext);

        self::assertNotNull($user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function findByExternalIdReturnsNullWhenAbsent(): void
    {
        self::assertNull($this->repository->findByExternalId('no-such-external-id-' . \uniqid()));
    }

    // ── create / update ─────────────────────────────────────────────

    #[Test]
    public function createInsertsUserAndMetaAndDefaultsActive(): void
    {
        $login = 'scim_create_' . \uniqid();
        $userId = $this->repository->create(
            ['user_login' => $login, 'user_email' => $login . '@example.com'],
            ['_wppack_scim_title' => 'Dev'],
        );

        self::assertIsInt($userId);
        self::assertGreaterThan(0, $userId);
        self::assertSame('Dev', $this->users->getMeta($userId, '_wppack_scim_title', true));
        self::assertSame('1', $this->users->getMeta($userId, ScimConstants::META_ACTIVE, true));
    }

    #[Test]
    public function createAllowsExplicitActiveOverride(): void
    {
        $login = 'scim_create_inactive_' . \uniqid();
        $userId = $this->repository->create(
            ['user_login' => $login, 'user_email' => $login . '@example.com'],
            [ScimConstants::META_ACTIVE => '0'],
        );

        self::assertSame('0', $this->users->getMeta($userId, ScimConstants::META_ACTIVE, true));
    }

    #[Test]
    public function createGeneratesPasswordWhenOmitted(): void
    {
        $login = 'scim_create_pw_' . \uniqid();
        $userId = $this->repository->create(
            ['user_login' => $login, 'user_email' => $login . '@example.com'],
            [],
        );

        self::assertIsInt($userId);
        self::assertGreaterThan(0, $userId);
    }

    #[Test]
    public function updateAppliesDataMetaAndStampsLastModified(): void
    {
        $userId = $this->insertUser('scim_update');

        $this->repository->update(
            $userId,
            ['display_name' => 'Updated Name'],
            ['_wppack_scim_title' => 'Senior Engineer'],
        );

        $user = new \WP_User($userId);
        self::assertSame('Updated Name', $user->display_name);
        self::assertSame('Senior Engineer', $this->users->getMeta($userId, '_wppack_scim_title', true));
        $lastModified = (string) $this->users->getMeta($userId, ScimConstants::META_LAST_MODIFIED, true);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $lastModified);
    }

    #[Test]
    public function updateWithEmptyDataOnlyTouchesMeta(): void
    {
        $userId = $this->insertUser('scim_update_meta_only');
        $original = (new \WP_User($userId))->display_name;

        $this->repository->update($userId, [], ['_wppack_scim_title' => 'Lead']);

        $user = new \WP_User($userId);
        self::assertSame($original, $user->display_name);
        self::assertSame('Lead', $this->users->getMeta($userId, '_wppack_scim_title', true));
    }

    // ── isActive / deactivate / reactivate ──────────────────────────

    #[Test]
    public function isActiveDefaultsTrueWithoutMeta(): void
    {
        $userId = $this->insertUser('scim_active_default');

        self::assertTrue($this->repository->isActive($userId));
    }

    #[Test]
    public function deactivateSetsMetaAndClearsRolesSingleSite(): void
    {
        $userId = $this->insertUser('scim_deactivate');
        $user = new \WP_User($userId);
        $user->set_role('editor');

        $this->repository->deactivate($userId);

        self::assertSame('0', $this->users->getMeta($userId, ScimConstants::META_ACTIVE, true));
        self::assertFalse($this->repository->isActive($userId));
        $reloaded = new \WP_User($userId);
        self::assertSame([], $reloaded->roles);
    }

    #[Test]
    public function reactivateRestoresActiveMetaAndAssignsDefaultRole(): void
    {
        $userId = $this->insertUser('scim_reactivate');
        $this->repository->deactivate($userId);

        $this->repository->reactivate($userId, 'subscriber');

        self::assertSame('1', $this->users->getMeta($userId, ScimConstants::META_ACTIVE, true));
        $user = new \WP_User($userId);
        self::assertContains('subscriber', $user->roles);
    }

    #[Test]
    public function reactivateDoesNotOverwriteExistingRoles(): void
    {
        $userId = $this->insertUser('scim_reactivate_keep_role');
        $user = new \WP_User($userId);
        $user->set_role('editor');
        $this->users->updateMeta($userId, ScimConstants::META_ACTIVE, '0');

        $this->repository->reactivate($userId, 'subscriber');

        $reloaded = new \WP_User($userId);
        self::assertContains('editor', $reloaded->roles);
        self::assertNotContains('subscriber', $reloaded->roles);
    }

    // ── delete ──────────────────────────────────────────────────────

    #[Test]
    public function deleteRemovesUser(): void
    {
        $userId = $this->insertUser('scim_delete');

        $this->repository->delete($userId);

        self::assertNull($this->repository->find($userId));
    }

    // ── findFiltered ────────────────────────────────────────────────

    #[Test]
    public function findFilteredPaginationReturnsTotal(): void
    {
        $this->insertUser('scim_filter_a');
        $this->insertUser('scim_filter_b');

        $result = $this->repository->findFiltered(null, startIndex: 1, count: 10);

        self::assertArrayHasKey('users', $result);
        self::assertArrayHasKey('totalResults', $result);
        self::assertGreaterThanOrEqual(2, $result['totalResults']);
    }

    #[Test]
    public function findFilteredWithFilterRestrictsResults(): void
    {
        $ext = 'okta-filtered-' . \uniqid();
        $userId = $this->insertUser('scim_filter_ext', $ext);

        $parser = new FilterParser();
        $result = $this->repository->findFiltered(
            $parser->parse(sprintf('externalId eq "%s"', $ext)),
            startIndex: 1,
            count: 10,
        );

        self::assertSame(1, $result['totalResults']);
        self::assertSame($userId, $result['users'][0]->ID);
    }
}
