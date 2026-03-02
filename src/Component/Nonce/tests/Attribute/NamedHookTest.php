<?php

declare(strict_types=1);

namespace WpPack\Component\Nonce\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Nonce\Attribute\Action\CheckAdminRefererAction;
use WpPack\Component\Nonce\Attribute\Filter\NonceLifeFilter;
use WpPack\Component\Nonce\Attribute\Filter\NonceUserLoggedOutFilter;

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
    public function checkAdminRefererActionAcceptsCustomPriority(): void
    {
        $action = new CheckAdminRefererAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function nonceLifeFilterHasCorrectHookName(): void
    {
        $filter = new NonceLifeFilter();

        self::assertSame('nonce_life', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function nonceUserLoggedOutFilterHasCorrectHookName(): void
    {
        $filter = new NonceUserLoggedOutFilter();

        self::assertSame('nonce_user_logged_out', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CheckAdminRefererAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new NonceLifeFilter());
        self::assertInstanceOf(Filter::class, new NonceUserLoggedOutFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[CheckAdminRefererAction]
            public function onCheckAdminReferer(): void {}

            #[NonceLifeFilter(priority: 5)]
            public function onNonceLife(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onCheckAdminReferer');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('check_admin_referer', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onNonceLife');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('nonce_life', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
