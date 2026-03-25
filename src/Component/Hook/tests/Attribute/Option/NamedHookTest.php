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

namespace WpPack\Component\Hook\Tests\Attribute\Option;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Option\Action\AddOptionAction;
use WpPack\Component\Hook\Attribute\Option\Action\DeleteOptionAction;
use WpPack\Component\Hook\Attribute\Option\Action\UpdateOptionAction;
use WpPack\Component\Hook\Attribute\Option\Action\UpdateSiteOptionAction;
use WpPack\Component\Hook\Attribute\Option\Filter\DefaultOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Filter\OptionFilter;
use WpPack\Component\Hook\Attribute\Option\Filter\PreOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Filter\PreSiteOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Filter\PreUpdateOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Filter\SiteOptionFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function addOptionActionHasCorrectHookName(): void
    {
        $action = new AddOptionAction(name: 'my_opt');

        self::assertSame('add_option_my_opt', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function addOptionActionAcceptsCustomPriority(): void
    {
        $action = new AddOptionAction(name: 'my_opt', priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function addOptionActionPropertyIsAccessible(): void
    {
        $action = new AddOptionAction(name: 'my_opt');

        self::assertSame('my_opt', $action->name);
    }

    #[Test]
    public function deleteOptionActionHasCorrectHookName(): void
    {
        $action = new DeleteOptionAction(name: 'my_opt');

        self::assertSame('delete_option_my_opt', $action->hook);
    }

    #[Test]
    public function updateOptionActionHasCorrectHookName(): void
    {
        $action = new UpdateOptionAction(name: 'my_opt');

        self::assertSame('update_option_my_opt', $action->hook);
    }

    #[Test]
    public function updateSiteOptionActionHasCorrectHookName(): void
    {
        $action = new UpdateSiteOptionAction(name: 'my_opt');

        self::assertSame('update_site_option_my_opt', $action->hook);
    }

    #[Test]
    public function defaultOptionFilterHasCorrectHookName(): void
    {
        $filter = new DefaultOptionFilter(name: 'my_opt');

        self::assertSame('default_option_my_opt', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function optionFilterHasCorrectHookName(): void
    {
        $filter = new OptionFilter(name: 'my_opt');

        self::assertSame('option_my_opt', $filter->hook);
    }

    #[Test]
    public function preOptionFilterHasCorrectHookName(): void
    {
        $filter = new PreOptionFilter(name: 'my_opt');

        self::assertSame('pre_option_my_opt', $filter->hook);
    }

    #[Test]
    public function preSiteOptionFilterHasCorrectHookName(): void
    {
        $filter = new PreSiteOptionFilter(name: 'my_opt');

        self::assertSame('pre_site_option_my_opt', $filter->hook);
    }

    #[Test]
    public function preUpdateOptionFilterHasCorrectHookName(): void
    {
        $filter = new PreUpdateOptionFilter(name: 'my_opt');

        self::assertSame('pre_update_option_my_opt', $filter->hook);
    }

    #[Test]
    public function siteOptionFilterHasCorrectHookName(): void
    {
        $filter = new SiteOptionFilter(name: 'my_opt');

        self::assertSame('site_option_my_opt', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new AddOptionAction(name: 'test'));
        self::assertInstanceOf(Action::class, new DeleteOptionAction(name: 'test'));
        self::assertInstanceOf(Action::class, new UpdateOptionAction(name: 'test'));
        self::assertInstanceOf(Action::class, new UpdateSiteOptionAction(name: 'test'));
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DefaultOptionFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new OptionFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new PreOptionFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new PreSiteOptionFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new PreUpdateOptionFilter(name: 'test'));
        self::assertInstanceOf(Filter::class, new SiteOptionFilter(name: 'test'));
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[AddOptionAction(name: 'theme_color')]
            public function onAddOption(): void {}

            #[OptionFilter(name: 'theme_color', priority: 5)]
            public function onOption(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onAddOption');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('add_option_theme_color', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onOption');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('option_theme_color', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
