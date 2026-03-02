<?php

declare(strict_types=1);

namespace WpPack\Component\Option\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Option\Attribute\Action\AddOptionAction;
use WpPack\Component\Option\Attribute\Action\DeleteOptionAction;
use WpPack\Component\Option\Attribute\Action\UpdateOptionAction;
use WpPack\Component\Option\Attribute\Action\UpdateSiteOptionAction;
use WpPack\Component\Option\Attribute\Filter\DefaultOptionFilter;
use WpPack\Component\Option\Attribute\Filter\OptionFilter;
use WpPack\Component\Option\Attribute\Filter\PreOptionFilter;
use WpPack\Component\Option\Attribute\Filter\PreSiteOptionFilter;
use WpPack\Component\Option\Attribute\Filter\PreUpdateOptionFilter;
use WpPack\Component\Option\Attribute\Filter\SiteOptionFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function addOptionActionHasCorrectHookName(): void
    {
        $action = new AddOptionAction(optionName: 'my_opt');

        self::assertSame('add_option_my_opt', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function addOptionActionAcceptsCustomPriority(): void
    {
        $action = new AddOptionAction(optionName: 'my_opt', priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function addOptionActionPropertyIsAccessible(): void
    {
        $action = new AddOptionAction(optionName: 'my_opt');

        self::assertSame('my_opt', $action->optionName);
    }

    #[Test]
    public function deleteOptionActionHasCorrectHookName(): void
    {
        $action = new DeleteOptionAction(optionName: 'my_opt');

        self::assertSame('delete_option_my_opt', $action->hook);
    }

    #[Test]
    public function updateOptionActionHasCorrectHookName(): void
    {
        $action = new UpdateOptionAction(optionName: 'my_opt');

        self::assertSame('update_option_my_opt', $action->hook);
    }

    #[Test]
    public function updateSiteOptionActionHasCorrectHookName(): void
    {
        $action = new UpdateSiteOptionAction(optionName: 'my_opt');

        self::assertSame('update_site_option_my_opt', $action->hook);
    }

    #[Test]
    public function defaultOptionFilterHasCorrectHookName(): void
    {
        $filter = new DefaultOptionFilter(optionName: 'my_opt');

        self::assertSame('default_option_my_opt', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function optionFilterHasCorrectHookName(): void
    {
        $filter = new OptionFilter(optionName: 'my_opt');

        self::assertSame('option_my_opt', $filter->hook);
    }

    #[Test]
    public function preOptionFilterHasCorrectHookName(): void
    {
        $filter = new PreOptionFilter(optionName: 'my_opt');

        self::assertSame('pre_option_my_opt', $filter->hook);
    }

    #[Test]
    public function preSiteOptionFilterHasCorrectHookName(): void
    {
        $filter = new PreSiteOptionFilter(optionName: 'my_opt');

        self::assertSame('pre_site_option_my_opt', $filter->hook);
    }

    #[Test]
    public function preUpdateOptionFilterHasCorrectHookName(): void
    {
        $filter = new PreUpdateOptionFilter(optionName: 'my_opt');

        self::assertSame('pre_update_option_my_opt', $filter->hook);
    }

    #[Test]
    public function siteOptionFilterHasCorrectHookName(): void
    {
        $filter = new SiteOptionFilter(optionName: 'my_opt');

        self::assertSame('site_option_my_opt', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new AddOptionAction(optionName: 'test'));
        self::assertInstanceOf(Action::class, new DeleteOptionAction(optionName: 'test'));
        self::assertInstanceOf(Action::class, new UpdateOptionAction(optionName: 'test'));
        self::assertInstanceOf(Action::class, new UpdateSiteOptionAction(optionName: 'test'));
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DefaultOptionFilter(optionName: 'test'));
        self::assertInstanceOf(Filter::class, new OptionFilter(optionName: 'test'));
        self::assertInstanceOf(Filter::class, new PreOptionFilter(optionName: 'test'));
        self::assertInstanceOf(Filter::class, new PreSiteOptionFilter(optionName: 'test'));
        self::assertInstanceOf(Filter::class, new PreUpdateOptionFilter(optionName: 'test'));
        self::assertInstanceOf(Filter::class, new SiteOptionFilter(optionName: 'test'));
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[AddOptionAction(optionName: 'theme_color')]
            public function onAddOption(): void {}

            #[OptionFilter(optionName: 'theme_color', priority: 5)]
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
