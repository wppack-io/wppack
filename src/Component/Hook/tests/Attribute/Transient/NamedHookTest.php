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

namespace WPPack\Component\Hook\Tests\Attribute\Transient;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Attribute\Transient\Action\DeletedTransientAction;
use WPPack\Component\Hook\Attribute\Transient\Action\SetSiteTransientAction;
use WPPack\Component\Hook\Attribute\Transient\Action\SetTransientAction;
use WPPack\Component\Hook\Attribute\Transient\Filter\PreSetTransientFilter;
use WPPack\Component\Hook\Attribute\Transient\Filter\PreSiteTransientFilter;
use WPPack\Component\Hook\Attribute\Transient\Filter\PreTransientFilter;
use WPPack\Component\Hook\Attribute\Transient\Filter\SiteTransientFilter;
use WPPack\Component\Hook\Attribute\Transient\Filter\TransientFilter;
use WPPack\Component\Hook\Attribute\Transient\Filter\TransientTimeoutFilter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function deletedTransientActionHasCorrectHookName(): void
    {
        $action = new DeletedTransientAction();

        self::assertSame('deleted_transient', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function deletedTransientActionAcceptsCustomPriority(): void
    {
        $action = new DeletedTransientAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function setSiteTransientActionHasCorrectHookName(): void
    {
        $action = new SetSiteTransientAction(name: 'my_cache');

        self::assertSame('set_site_transient_my_cache', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function setSiteTransientActionAcceptsCustomPriority(): void
    {
        $action = new SetSiteTransientAction(name: 'my_cache', priority: 20);

        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function setSiteTransientActionPropertyIsAccessible(): void
    {
        $action = new SetSiteTransientAction(name: 'my_cache');

        self::assertSame('my_cache', $action->name);
    }

    #[Test]
    public function setTransientActionHasCorrectHookName(): void
    {
        $action = new SetTransientAction(name: 'my_cache');

        self::assertSame('set_transient_my_cache', $action->hook);
    }

    #[Test]
    public function preSetTransientFilterHasCorrectHookName(): void
    {
        $filter = new PreSetTransientFilter(name: 'my_cache');

        self::assertSame('pre_set_transient_my_cache', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function preSiteTransientFilterHasCorrectHookName(): void
    {
        $filter = new PreSiteTransientFilter(name: 'my_cache');

        self::assertSame('pre_site_transient_my_cache', $filter->hook);
    }

    #[Test]
    public function preTransientFilterHasCorrectHookName(): void
    {
        $filter = new PreTransientFilter(name: 'my_cache');

        self::assertSame('pre_transient_my_cache', $filter->hook);
    }

    #[Test]
    public function siteTransientFilterHasCorrectHookName(): void
    {
        $filter = new SiteTransientFilter(name: 'my_cache');

        self::assertSame('site_transient_my_cache', $filter->hook);
    }

    #[Test]
    public function transientFilterHasCorrectHookName(): void
    {
        $filter = new TransientFilter(name: 'my_cache');

        self::assertSame('transient_my_cache', $filter->hook);
    }

    #[Test]
    public function transientTimeoutFilterHasCorrectHookName(): void
    {
        $filter = new TransientTimeoutFilter(name: 'my_cache');

        self::assertSame('expiration_of_transient_my_cache', $filter->hook);
    }

    #[Test]
    public function transientTimeoutFilterPropertyIsAccessible(): void
    {
        $filter = new TransientTimeoutFilter(name: 'my_cache');

        self::assertSame('my_cache', $filter->name);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new DeletedTransientAction());
        self::assertInstanceOf(Action::class, new SetSiteTransientAction(name: 'test'));
        self::assertInstanceOf(Action::class, new SetTransientAction(name: 'test'));
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PreSetTransientFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new PreSiteTransientFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new PreTransientFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new SiteTransientFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new TransientFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new TransientTimeoutFilter(name: 'test'));
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[SetTransientAction(name: 'my_cache')]
            public function onSetTransient(): void {}

            #[TransientTimeoutFilter(name: 'my_cache', priority: 5)]
            public function onTimeout(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onSetTransient');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('set_transient_my_cache', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onTimeout');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('expiration_of_transient_my_cache', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
