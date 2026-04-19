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

namespace WPPack\Component\Hook\Tests\Attribute\Role;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;
use WPPack\Component\Hook\Attribute\Role\Action\GrantSuperAdminAction;
use WPPack\Component\Hook\Attribute\Role\Action\RevokeSuperAdminAction;
use WPPack\Component\Hook\Attribute\Role\Action\SetUserRoleAction;
use WPPack\Component\Hook\Attribute\Role\Filter\MapMetaCapFilter;
use WPPack\Component\Hook\Attribute\Role\Filter\UserHasCapFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function grantSuperAdminActionHasCorrectHookName(): void
    {
        $action = new GrantSuperAdminAction();

        self::assertSame('grant_super_admin', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function revokeSuperAdminActionHasCorrectHookName(): void
    {
        $action = new RevokeSuperAdminAction();

        self::assertSame('revoke_super_admin', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function setUserRoleActionHasCorrectHookName(): void
    {
        $action = new SetUserRoleAction();

        self::assertSame('set_user_role', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function mapMetaCapFilterHasCorrectHookName(): void
    {
        $filter = new MapMetaCapFilter();

        self::assertSame('map_meta_cap', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function userHasCapFilterHasCorrectHookName(): void
    {
        $filter = new UserHasCapFilter();

        self::assertSame('user_has_cap', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function grantSuperAdminActionAcceptsCustomPriority(): void
    {
        $action = new GrantSuperAdminAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new GrantSuperAdminAction());
        self::assertInstanceOf(Action::class, new RevokeSuperAdminAction());
        self::assertInstanceOf(Action::class, new SetUserRoleAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new MapMetaCapFilter());
        self::assertInstanceOf(Filter::class, new UserHasCapFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[SetUserRoleAction]
            public function onSetUserRole(): void {}

            #[MapMetaCapFilter]
            public function onMapMetaCap(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onSetUserRole');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('set_user_role', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onMapMetaCap');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('map_meta_cap', $attributes[0]->newInstance()->hook);
    }
}
