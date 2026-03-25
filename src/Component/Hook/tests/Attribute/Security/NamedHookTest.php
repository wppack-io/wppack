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

namespace WpPack\Component\Hook\Tests\Attribute\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Security\Action\PasswordResetAction;
use WpPack\Component\Hook\Attribute\Security\Action\RetrievePasswordAction;
use WpPack\Component\Hook\Attribute\Security\Action\WpLoginAction;
use WpPack\Component\Hook\Attribute\Security\Action\WpLoginFailedAction;
use WpPack\Component\Hook\Attribute\Security\Action\WpLogoutAction;
use WpPack\Component\Security\Attribute\AsAuthenticator;
use WpPack\Component\Security\Attribute\AsVoter;
use WpPack\Component\Hook\Attribute\Security\Filter\AuthenticateFilter;
use WpPack\Component\Hook\Attribute\Security\Filter\CheckPasswordFilter;
use WpPack\Component\Hook\Attribute\Security\Filter\DetermineCurrentUserFilter;
use WpPack\Component\Hook\Attribute\Security\Filter\MapMetaCapFilter;
use WpPack\Component\Hook\Attribute\Security\Filter\UserHasCapFilter;

final class NamedHookTest extends TestCase
{
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
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new PasswordResetAction());
        self::assertInstanceOf(Action::class, new RetrievePasswordAction());
        self::assertInstanceOf(Action::class, new WpLoginAction());
        self::assertInstanceOf(Action::class, new WpLoginFailedAction());
        self::assertInstanceOf(Action::class, new WpLogoutAction());
    }

    #[Test]
    public function userHasCapFilterHasCorrectHookName(): void
    {
        $filter = new UserHasCapFilter();

        self::assertSame('user_has_cap', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function mapMetaCapFilterHasCorrectHookName(): void
    {
        $filter = new MapMetaCapFilter();

        self::assertSame('map_meta_cap', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new AuthenticateFilter());
        self::assertInstanceOf(Filter::class, new CheckPasswordFilter());
        self::assertInstanceOf(Filter::class, new DetermineCurrentUserFilter());
        self::assertInstanceOf(Filter::class, new UserHasCapFilter());
        self::assertInstanceOf(Filter::class, new MapMetaCapFilter());
    }

    #[Test]
    public function asAuthenticatorAttributeHasDefaultPriority(): void
    {
        $attr = new AsAuthenticator();

        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function asAuthenticatorAttributeAcceptsCustomPriority(): void
    {
        $attr = new AsAuthenticator(priority: 10);

        self::assertSame(10, $attr->priority);
    }

    #[Test]
    public function asAuthenticatorTargetsClass(): void
    {
        $reflection = new \ReflectionClass(AsAuthenticator::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $attrInstance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }

    #[Test]
    public function asVoterAttributeHasDefaultPriority(): void
    {
        $attr = new AsVoter();

        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function asVoterAttributeAcceptsCustomPriority(): void
    {
        $attr = new AsVoter(priority: 5);

        self::assertSame(5, $attr->priority);
    }

    #[Test]
    public function asVoterTargetsClass(): void
    {
        $reflection = new \ReflectionClass(AsVoter::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $attrInstance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
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
