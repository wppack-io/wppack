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

namespace WpPack\Component\Hook\Tests\Attribute\DashboardWidget;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\DashboardWidget\Action\ActivityBoxEndAction;
use WpPack\Component\Hook\Attribute\DashboardWidget\Action\UpdateUserOptionAction;
use WpPack\Component\Hook\Attribute\DashboardWidget\Action\WpDashboardSetupAction;
use WpPack\Component\Hook\Attribute\DashboardWidget\Action\WpNetworkDashboardSetupAction;
use WpPack\Component\Hook\Attribute\DashboardWidget\Filter\DashboardGlanceItemsFilter;
use WpPack\Component\Hook\Attribute\Filter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpDashboardSetupActionHasCorrectHookName(): void
    {
        $action = new WpDashboardSetupAction();

        self::assertSame('wp_dashboard_setup', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpNetworkDashboardSetupActionHasCorrectHookName(): void
    {
        $action = new WpNetworkDashboardSetupAction();

        self::assertSame('wp_network_dashboard_setup', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function updateUserOptionActionHasCorrectHookName(): void
    {
        $action = new UpdateUserOptionAction(option: 'dashboard_widget_order');

        self::assertSame('update_user_option', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
        self::assertSame('dashboard_widget_order', $action->option);
    }

    #[Test]
    public function wpDashboardSetupActionAcceptsCustomPriority(): void
    {
        $action = new WpDashboardSetupAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function wpNetworkDashboardSetupActionAcceptsCustomPriority(): void
    {
        $action = new WpNetworkDashboardSetupAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function updateUserOptionActionOptionPropertyIsAccessible(): void
    {
        $action = new UpdateUserOptionAction(option: 'metaboxhidden_dashboard');

        self::assertSame('update_user_option', $action->hook);
        self::assertSame('metaboxhidden_dashboard', $action->option);
    }

    #[Test]
    public function updateUserOptionActionAcceptsCustomPriority(): void
    {
        $action = new UpdateUserOptionAction(option: 'dashboard_widget_order', priority: 20);

        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function activityBoxEndActionHasCorrectHookName(): void
    {
        $action = new ActivityBoxEndAction();

        self::assertSame('activity_box_end', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function activityBoxEndActionAcceptsCustomPriority(): void
    {
        $action = new ActivityBoxEndAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function dashboardGlanceItemsFilterHasCorrectHookName(): void
    {
        $filter = new DashboardGlanceItemsFilter();

        self::assertSame('dashboard_glance_items', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function dashboardGlanceItemsFilterAcceptsCustomPriority(): void
    {
        $filter = new DashboardGlanceItemsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpDashboardSetupAction());
        self::assertInstanceOf(Action::class, new WpNetworkDashboardSetupAction());
        self::assertInstanceOf(Action::class, new UpdateUserOptionAction(option: 'dashboard_widget_order'));
        self::assertInstanceOf(Action::class, new ActivityBoxEndAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DashboardGlanceItemsFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpDashboardSetupAction]
            public function onDashboardSetup(): void {}

            #[UpdateUserOptionAction(option: 'dashboard_widget_order')]
            public function onUpdateUserOption(): void {}

            #[ActivityBoxEndAction]
            public function onActivityBoxEnd(): void {}

            #[DashboardGlanceItemsFilter]
            public function filterGlanceItems(): void {}
        };

        $setupMethod = new \ReflectionMethod($class, 'onDashboardSetup');
        $attributes = $setupMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_dashboard_setup', $attributes[0]->newInstance()->hook);

        $optionMethod = new \ReflectionMethod($class, 'onUpdateUserOption');
        $attributes = $optionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('update_user_option', $attributes[0]->newInstance()->hook);

        $activityMethod = new \ReflectionMethod($class, 'onActivityBoxEnd');
        $attributes = $activityMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('activity_box_end', $attributes[0]->newInstance()->hook);

        $glanceMethod = new \ReflectionMethod($class, 'filterGlanceItems');
        $attributes = $glanceMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('dashboard_glance_items', $attributes[0]->newInstance()->hook);
    }
}
