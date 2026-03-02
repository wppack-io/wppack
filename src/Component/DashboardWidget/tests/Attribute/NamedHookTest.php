<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\DashboardWidget\Attribute\Action\UpdateUserOptionAction;
use WpPack\Component\DashboardWidget\Attribute\Action\WpDashboardSetupAction;
use WpPack\Component\DashboardWidget\Attribute\Action\WpNetworkDashboardSetupAction;

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
    public function updateUserOptionActionOptionPropertyIsAccessible(): void
    {
        $action = new UpdateUserOptionAction(option: 'metaboxhidden_dashboard');

        self::assertSame('update_user_option', $action->hook);
        self::assertSame('metaboxhidden_dashboard', $action->option);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpDashboardSetupAction());
        self::assertInstanceOf(Action::class, new WpNetworkDashboardSetupAction());
        self::assertInstanceOf(Action::class, new UpdateUserOptionAction(option: 'dashboard_widget_order'));
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpDashboardSetupAction]
            public function onDashboardSetup(): void {}

            #[UpdateUserOptionAction(option: 'dashboard_widget_order')]
            public function onUpdateUserOption(): void {}
        };

        $setupMethod = new \ReflectionMethod($class, 'onDashboardSetup');
        $attributes = $setupMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_dashboard_setup', $attributes[0]->newInstance()->hook);

        $optionMethod = new \ReflectionMethod($class, 'onUpdateUserOption');
        $attributes = $optionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('update_user_option', $attributes[0]->newInstance()->hook);
    }
}
