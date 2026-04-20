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

namespace WPPack\Component\Scim\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Event\GroupDeletedEvent;
use WPPack\Component\Scim\Event\GroupMembershipChangedEvent;
use WPPack\Component\Scim\Event\GroupProvisionedEvent;
use WPPack\Component\Scim\Event\GroupUpdatedEvent;
use WPPack\Component\Scim\Event\ScimUserAttributesMappedEvent;
use WPPack\Component\Scim\Event\ScimUserSerializedEvent;
use WPPack\Component\Scim\Event\UserDeactivatedEvent;
use WPPack\Component\Scim\Event\UserDeletedEvent;
use WPPack\Component\Scim\Event\UserProvisionedEvent;
use WPPack\Component\Scim\Event\UserReactivatedEvent;
use WPPack\Component\Scim\Event\UserUpdatedEvent;

#[CoversClass(GroupDeletedEvent::class)]
#[CoversClass(GroupMembershipChangedEvent::class)]
#[CoversClass(GroupProvisionedEvent::class)]
#[CoversClass(GroupUpdatedEvent::class)]
#[CoversClass(ScimUserAttributesMappedEvent::class)]
#[CoversClass(ScimUserSerializedEvent::class)]
#[CoversClass(UserDeactivatedEvent::class)]
#[CoversClass(UserDeletedEvent::class)]
#[CoversClass(UserProvisionedEvent::class)]
#[CoversClass(UserReactivatedEvent::class)]
#[CoversClass(UserUpdatedEvent::class)]
final class ScimEventsTest extends TestCase
{
    private static function createUser(string $prefix): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);

        return new \WP_User($userId);
    }

    // ── Group events ────────────────────────────────────────────────

    #[Test]
    public function groupProvisionedCarriesRoleIdentity(): void
    {
        $event = new GroupProvisionedEvent('editor', 'Editor');

        self::assertSame('editor', $event->getRoleName());
        self::assertSame('Editor', $event->getRoleLabel());
    }

    #[Test]
    public function groupDeletedCarriesRoleName(): void
    {
        $event = new GroupDeletedEvent('subscriber');

        self::assertSame('subscriber', $event->getRoleName());
    }

    #[Test]
    public function groupUpdatedExposesChangesPayload(): void
    {
        $event = new GroupUpdatedEvent('editor', ['displayName' => 'Editors']);

        self::assertSame('editor', $event->getRoleName());
        self::assertSame(['displayName' => 'Editors'], $event->getChanges());
    }

    #[Test]
    public function groupMembershipChangedTracksAddedAndRemovedIds(): void
    {
        $event = new GroupMembershipChangedEvent('editor', added: [1, 2], removed: [3]);

        self::assertSame('editor', $event->getRoleName());
        self::assertSame([1, 2], $event->getAdded());
        self::assertSame([3], $event->getRemoved());
    }

    // ── User events ─────────────────────────────────────────────────

    #[Test]
    public function userProvisionedCarriesUserAndAttributes(): void
    {
        $user = self::createUser('scim_prov');
        $attrs = ['userName' => $user->user_login, 'active' => true];

        $event = new UserProvisionedEvent($user, $attrs);

        self::assertSame($user, $event->getUser());
        self::assertSame($attrs, $event->getScimAttributes());
    }

    #[Test]
    public function userUpdatedExposesChangedAttributes(): void
    {
        $user = self::createUser('scim_upd');
        $event = new UserUpdatedEvent($user, ['displayName' => 'Alicia']);

        self::assertSame($user, $event->getUser());
        self::assertSame(['displayName' => 'Alicia'], $event->getChangedAttributes());
    }

    #[Test]
    public function userDeactivatedAndReactivatedJustCarryTheUser(): void
    {
        $user = self::createUser('scim_active');

        self::assertSame($user, (new UserDeactivatedEvent($user))->getUser());
        self::assertSame($user, (new UserReactivatedEvent($user))->getUser());
    }

    #[Test]
    public function userDeletedCarriesIdAndLogin(): void
    {
        $event = new UserDeletedEvent(42, 'alice');

        self::assertSame(42, $event->getUserId());
        self::assertSame('alice', $event->getUserLogin());
    }

    // ── Mutable SCIM-filter events ──────────────────────────────────

    #[Test]
    public function scimUserAttributesMappedEventAllowsListenersToMutateDataAndMeta(): void
    {
        $event = new ScimUserAttributesMappedEvent(
            data: ['user_login' => 'alice'],
            meta: ['_wppack_scim_title' => 'Dev'],
            scimAttributes: ['userName' => 'alice'],
        );

        self::assertSame(['user_login' => 'alice'], $event->getData());
        self::assertSame(['_wppack_scim_title' => 'Dev'], $event->getMeta());
        self::assertSame(['userName' => 'alice'], $event->getScimAttributes());

        $event->setData(['user_login' => 'alice', 'user_email' => 'a@example.com']);
        $event->setMeta(['_wppack_scim_title' => 'Staff']);

        self::assertSame(['user_login' => 'alice', 'user_email' => 'a@example.com'], $event->getData());
        self::assertSame(['_wppack_scim_title' => 'Staff'], $event->getMeta());
        // Source SCIM attributes are readonly — listeners should not mutate
        // the upstream document.
        self::assertSame(['userName' => 'alice'], $event->getScimAttributes());
    }

    #[Test]
    public function scimUserSerializedEventAllowsMutatingOutputAttributes(): void
    {
        $user = self::createUser('scim_ser');
        $event = new ScimUserSerializedEvent(
            scimAttributes: ['userName' => $user->user_login],
            user: $user,
        );

        self::assertSame(['userName' => $user->user_login], $event->getScimAttributes());
        self::assertSame($user, $event->getUser());

        $event->setScimAttributes([
            'userName' => $user->user_login,
            'displayName' => $user->display_name,
        ]);

        self::assertSame([
            'userName' => $user->user_login,
            'displayName' => $user->display_name,
        ], $event->getScimAttributes());
    }
}
