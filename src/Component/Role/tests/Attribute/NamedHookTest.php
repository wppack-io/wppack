<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Role\Attribute\Action\GrantSuperAdminAction;
use WpPack\Component\Role\Attribute\Action\RevokeSuperAdminAction;
use WpPack\Component\Role\Attribute\Action\SetUserRoleAction;
use WpPack\Component\Role\Attribute\Filter\MapMetaCapFilter;
use WpPack\Component\Role\Attribute\Filter\UserHasCapFilter;

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
