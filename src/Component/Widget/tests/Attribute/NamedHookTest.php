<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Widget\Attribute\Action\DynamicSidebarAfterAction;
use WpPack\Component\Widget\Attribute\Action\DynamicSidebarBeforeAction;
use WpPack\Component\Widget\Attribute\Action\WidgetsInitAction;
use WpPack\Component\Widget\Attribute\Filter\DynamicSidebarHasWidgetsFilter;
use WpPack\Component\Widget\Attribute\Filter\DynamicSidebarParamsFilter;
use WpPack\Component\Widget\Attribute\Filter\RegisterSidebarFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetAreaPreviewFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetContentFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetDisplayCallbackFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetFormCallbackFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetsPrefetchingFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetTextFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetTitleFilter;
use WpPack\Component\Widget\Attribute\Filter\WidgetUpdateCallbackFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function dynamicSidebarAfterActionHasCorrectHookName(): void
    {
        $action = new DynamicSidebarAfterAction();

        self::assertSame('dynamic_sidebar_after', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function dynamicSidebarBeforeActionHasCorrectHookName(): void
    {
        $action = new DynamicSidebarBeforeAction();

        self::assertSame('dynamic_sidebar_before', $action->hook);
    }

    #[Test]
    public function widgetsInitActionHasCorrectHookName(): void
    {
        $action = new WidgetsInitAction();

        self::assertSame('widgets_init', $action->hook);
    }

    #[Test]
    public function widgetsInitActionAcceptsCustomPriority(): void
    {
        $action = new WidgetsInitAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function dynamicSidebarHasWidgetsFilterHasCorrectHookName(): void
    {
        $filter = new DynamicSidebarHasWidgetsFilter();

        self::assertSame('dynamic_sidebar_has_widgets', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function dynamicSidebarParamsFilterHasCorrectHookName(): void
    {
        $filter = new DynamicSidebarParamsFilter();

        self::assertSame('dynamic_sidebar_params', $filter->hook);
    }

    #[Test]
    public function registerSidebarFilterHasCorrectHookName(): void
    {
        $filter = new RegisterSidebarFilter();

        self::assertSame('register_sidebar', $filter->hook);
    }

    #[Test]
    public function widgetAreaPreviewFilterHasCorrectHookName(): void
    {
        $filter = new WidgetAreaPreviewFilter();

        self::assertSame('widget_area_preview', $filter->hook);
    }

    #[Test]
    public function widgetContentFilterHasCorrectHookName(): void
    {
        $filter = new WidgetContentFilter();

        self::assertSame('widget_content', $filter->hook);
    }

    #[Test]
    public function widgetDisplayCallbackFilterHasCorrectHookName(): void
    {
        $filter = new WidgetDisplayCallbackFilter();

        self::assertSame('widget_display_callback', $filter->hook);
    }

    #[Test]
    public function widgetFormCallbackFilterHasCorrectHookName(): void
    {
        $filter = new WidgetFormCallbackFilter();

        self::assertSame('widget_form_callback', $filter->hook);
    }

    #[Test]
    public function widgetTextFilterHasCorrectHookName(): void
    {
        $filter = new WidgetTextFilter();

        self::assertSame('widget_text', $filter->hook);
    }

    #[Test]
    public function widgetTitleFilterHasCorrectHookName(): void
    {
        $filter = new WidgetTitleFilter();

        self::assertSame('widget_title', $filter->hook);
    }

    #[Test]
    public function widgetUpdateCallbackFilterHasCorrectHookName(): void
    {
        $filter = new WidgetUpdateCallbackFilter();

        self::assertSame('widget_update_callback', $filter->hook);
    }

    #[Test]
    public function widgetsPrefetchingFilterHasCorrectHookName(): void
    {
        $filter = new WidgetsPrefetchingFilter();

        self::assertSame('widgets_prefetching', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new DynamicSidebarAfterAction());
        self::assertInstanceOf(Action::class, new DynamicSidebarBeforeAction());
        self::assertInstanceOf(Action::class, new WidgetsInitAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DynamicSidebarHasWidgetsFilter());
        self::assertInstanceOf(Filter::class, new DynamicSidebarParamsFilter());
        self::assertInstanceOf(Filter::class, new RegisterSidebarFilter());
        self::assertInstanceOf(Filter::class, new WidgetAreaPreviewFilter());
        self::assertInstanceOf(Filter::class, new WidgetContentFilter());
        self::assertInstanceOf(Filter::class, new WidgetDisplayCallbackFilter());
        self::assertInstanceOf(Filter::class, new WidgetFormCallbackFilter());
        self::assertInstanceOf(Filter::class, new WidgetTextFilter());
        self::assertInstanceOf(Filter::class, new WidgetTitleFilter());
        self::assertInstanceOf(Filter::class, new WidgetUpdateCallbackFilter());
        self::assertInstanceOf(Filter::class, new WidgetsPrefetchingFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WidgetsInitAction]
            public function onWidgetsInit(): void {}

            #[WidgetTitleFilter(priority: 5)]
            public function onWidgetTitle(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onWidgetsInit');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('widgets_init', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onWidgetTitle');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('widget_title', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
