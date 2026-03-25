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

namespace WpPack\Component\Hook\Tests\Attribute\Translation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Translation\Action\LoadTextdomainAction;
use WpPack\Component\Hook\Attribute\Translation\Action\UnloadTextdomainAction;
use WpPack\Component\Hook\Attribute\Translation\Filter\DetermineLocaleFilter;
use WpPack\Component\Hook\Attribute\Translation\Filter\GettextFilter;
use WpPack\Component\Hook\Attribute\Translation\Filter\LocaleFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function loadTextdomainActionHasCorrectHookName(): void
    {
        $action = new LoadTextdomainAction();

        self::assertSame('load_textdomain', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function unloadTextdomainActionHasCorrectHookName(): void
    {
        $action = new UnloadTextdomainAction();

        self::assertSame('unload_textdomain', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function localeFilterHasCorrectHookName(): void
    {
        $filter = new LocaleFilter();

        self::assertSame('locale', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function determineLocaleFilterHasCorrectHookName(): void
    {
        $filter = new DetermineLocaleFilter();

        self::assertSame('determine_locale', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function gettextFilterHasCorrectHookName(): void
    {
        $filter = new GettextFilter();

        self::assertSame('gettext', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function loadTextdomainActionAcceptsCustomPriority(): void
    {
        $action = new LoadTextdomainAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new LoadTextdomainAction());
        self::assertInstanceOf(Action::class, new UnloadTextdomainAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new LocaleFilter());
        self::assertInstanceOf(Filter::class, new DetermineLocaleFilter());
        self::assertInstanceOf(Filter::class, new GettextFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[LoadTextdomainAction]
            public function onLoadTextdomain(): void {}

            #[GettextFilter]
            public function onGettext(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onLoadTextdomain');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('load_textdomain', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onGettext');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('gettext', $attributes[0]->newInstance()->hook);
    }
}
