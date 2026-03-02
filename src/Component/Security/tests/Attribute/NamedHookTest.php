<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Security\Attribute\Action\CheckAdminRefererAction;
use WpPack\Component\Security\Attribute\Action\CheckAjaxRefererAction;
use WpPack\Component\Security\Attribute\Action\PasswordResetAction;
use WpPack\Component\Security\Attribute\Action\RetrievePasswordAction;
use WpPack\Component\Security\Attribute\Action\WpLoginAction;
use WpPack\Component\Security\Attribute\Action\WpLoginFailedAction;
use WpPack\Component\Security\Attribute\Action\WpLogoutAction;
use WpPack\Component\Security\Attribute\Filter\AuthenticateFilter;
use WpPack\Component\Security\Attribute\Filter\CheckPasswordFilter;
use WpPack\Component\Security\Attribute\Filter\DetermineCurrentUserFilter;
use WpPack\Component\Security\Attribute\Filter\MapMetaCapFilter;
use WpPack\Component\Security\Attribute\Filter\UserHasCapFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function checkAdminRefererActionHasCorrectHookName(): void
    {
        $action = new CheckAdminRefererAction();

        self::assertSame('check_admin_referer', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function checkAjaxRefererActionHasCorrectHookName(): void
    {
        $action = new CheckAjaxRefererAction();

        self::assertSame('check_ajax_referer', $action->hook);
    }

    #[Test]
    public function passwordResetActionHasCorrectHookName(): void
    {
        $action = new PasswordResetAction();

        self::assertSame('password_reset', $action->hook);
    }

    #[Test]
    public function retrievePasswordActionHasCorrectHookName(): void
    {
        $action = new RetrievePasswordAction();

        self::assertSame('retrieve_password', $action->hook);
    }

    #[Test]
    public function wpLoginActionHasCorrectHookName(): void
    {
        $action = new WpLoginAction();

        self::assertSame('wp_login', $action->hook);
    }

    #[Test]
    public function wpLoginFailedActionHasCorrectHookName(): void
    {
        $action = new WpLoginFailedAction();

        self::assertSame('wp_login_failed', $action->hook);
    }

    #[Test]
    public function wpLogoutActionHasCorrectHookName(): void
    {
        $action = new WpLogoutAction();

        self::assertSame('wp_logout', $action->hook);
    }

    #[Test]
    public function wpLoginActionAcceptsCustomPriority(): void
    {
        $action = new WpLoginAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function authenticateFilterHasCorrectHookName(): void
    {
        $filter = new AuthenticateFilter();

        self::assertSame('authenticate', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function checkPasswordFilterHasCorrectHookName(): void
    {
        $filter = new CheckPasswordFilter();

        self::assertSame('check_password', $filter->hook);
    }

    #[Test]
    public function determineCurrentUserFilterHasCorrectHookName(): void
    {
        $filter = new DetermineCurrentUserFilter();

        self::assertSame('determine_current_user', $filter->hook);
    }

    #[Test]
    public function mapMetaCapFilterHasCorrectHookName(): void
    {
        $filter = new MapMetaCapFilter();

        self::assertSame('map_meta_cap', $filter->hook);
    }

    #[Test]
    public function userHasCapFilterHasCorrectHookName(): void
    {
        $filter = new UserHasCapFilter();

        self::assertSame('user_has_cap', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CheckAdminRefererAction());
        self::assertInstanceOf(Action::class, new CheckAjaxRefererAction());
        self::assertInstanceOf(Action::class, new PasswordResetAction());
        self::assertInstanceOf(Action::class, new RetrievePasswordAction());
        self::assertInstanceOf(Action::class, new WpLoginAction());
        self::assertInstanceOf(Action::class, new WpLoginFailedAction());
        self::assertInstanceOf(Action::class, new WpLogoutAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new AuthenticateFilter());
        self::assertInstanceOf(Filter::class, new CheckPasswordFilter());
        self::assertInstanceOf(Filter::class, new DetermineCurrentUserFilter());
        self::assertInstanceOf(Filter::class, new MapMetaCapFilter());
        self::assertInstanceOf(Filter::class, new UserHasCapFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpLoginAction]
            public function onWpLogin(): void {}

            #[AuthenticateFilter(priority: 5)]
            public function onAuthenticate(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onWpLogin');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_login', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onAuthenticate');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('authenticate', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
