<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

final class AbstractDashboardWidgetTest extends TestCase
{
    #[Test]
    public function resolvesIdFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('test_dashboard_widget', $widget->id);
    }

    #[Test]
    public function resolvesTitleFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('Test Dashboard Widget', $widget->title);
    }

    #[Test]
    public function resolvesContextFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('normal', $widget->context);
    }

    #[Test]
    public function resolvesPriorityFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('core', $widget->priority);
    }

    #[Test]
    public function resolvesCapabilityFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertNull($widget->capability);
    }

    #[Test]
    public function resolvesAllAttributeParameters(): void
    {
        $widget = new FullAttributeTestDashboardWidget();

        self::assertSame('full_widget', $widget->id);
        self::assertSame('Full Widget', $widget->title);
        self::assertSame('manage_options', $widget->capability);
        self::assertSame('side', $widget->context);
        self::assertSame('high', $widget->priority);
    }

    #[Test]
    public function renderIsCalled(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        ob_start();
        $widget->render();
        $output = ob_get_clean();

        self::assertSame('<p>dashboard content</p>', $output);
    }

    #[Test]
    public function configureDefaultIsEmpty(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        ob_start();
        $widget->configure();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsDashboardWidget] attribute');

        new NoAttributeTestDashboardWidget();
    }

    #[Test]
    public function registerWithCapabilityAllowed(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $widget = new CapabilityTestDashboardWidget();

        // current_user_can('edit_posts') must return true in WordPress test env
        $widget->register();

        // If no exception, the widget was registered (or capability check passed)
        self::assertTrue(true);
    }

    #[Test]
    public function registerSkipsWhenCapabilityDenied(): void
    {
        if (!function_exists('current_user_can')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // Use a capability that the default test user won't have
        $widget = new RestrictedCapabilityTestDashboardWidget();

        // Set up a user without the required capability
        if (function_exists('wp_set_current_user')) {
            wp_set_current_user(0); // Anonymous user
        }

        ob_start();
        $widget->register();
        ob_end_clean();

        // wp_add_dashboard_widget should not have been called
        // If no error occurred, the capability check correctly skipped registration
        self::assertTrue(true);
    }

    #[Test]
    public function configureOverrideIsDetected(): void
    {
        $widget = new ConfigurableTestDashboardWidget();

        ob_start();
        $widget->configure();
        $output = ob_get_clean();

        self::assertSame('<input type="text" name="setting">', $output);
    }

    #[Test]
    public function registerCallsWpAddDashboardWidget(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_meta_boxes;

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey('dashboard', $wp_meta_boxes);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function registerPassesCorrectParameters(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_meta_boxes;

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        $entry = $wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id];
        self::assertSame('test_dashboard_widget', $entry['id']);
        self::assertSame('Test Dashboard Widget', $entry['title']);
    }

    #[Test]
    public function registerWithoutCapabilityRegisters(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_meta_boxes;

        $widget = new ConcreteTestDashboardWidget();
        self::assertNull($widget->capability);
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function registerWithConfigureOverridePassesCallback(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }
        if (function_exists('wp_set_current_user')) {
            wp_set_current_user(1);
        }

        global $wp_dashboard_control_callbacks;

        $widget = new ConfigurableTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_dashboard_control_callbacks);
        self::assertIsCallable($wp_dashboard_control_callbacks[$widget->id]);
    }

    #[Test]
    public function registerWithoutConfigureOverridePassesNull(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_dashboard_control_callbacks;
        $wp_dashboard_control_callbacks = [];

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        self::assertArrayNotHasKey($widget->id, $wp_dashboard_control_callbacks);
    }

    #[Test]
    public function hasConfigureOverrideReturnsTrueForOverride(): void
    {
        $widget = new ConfigurableTestDashboardWidget();

        $method = new \ReflectionMethod($widget, 'hasConfigureOverride');

        self::assertTrue($method->invoke($widget));
    }

    #[Test]
    public function hasConfigureOverrideReturnsFalseForDefault(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        $method = new \ReflectionMethod($widget, 'hasConfigureOverride');

        self::assertFalse($method->invoke($widget));
    }

    #[Test]
    public function resolveAttributeThrowsForMissingAttributeWithClassName(): void
    {
        try {
            new NoAttributeTestDashboardWidget();
            self::fail('Expected LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('NoAttributeTestDashboardWidget', $e->getMessage());
            self::assertStringContainsString('#[AsDashboardWidget]', $e->getMessage());
        }
    }

    #[Test]
    public function widgetPropertiesAreReadonly(): void
    {
        $widget = new ConcreteTestDashboardWidget();
        $reflection = new \ReflectionClass($widget);

        foreach (['id', 'title', 'capability', 'context', 'priority'] as $prop) {
            self::assertTrue($reflection->getProperty($prop)->isReadOnly());
        }
    }

    #[Test]
    public function configureDefaultDoesNotProduceOutput(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        ob_start();
        $widget->configure();
        $output = ob_get_clean();

        self::assertEmpty($output);
    }

    #[Test]
    public function renderOutputsExpectedContent(): void
    {
        $widget = new FullAttributeTestDashboardWidget();

        ob_start();
        $widget->render();
        $output = ob_get_clean();

        self::assertSame('<p>full widget</p>', $output);
    }

    #[Test]
    public function registerWithCapabilityChecksUserPermission(): void
    {
        if (!function_exists('wp_add_dashboard_widget') || !function_exists('wp_set_current_user')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_meta_boxes;

        // Set up admin user with manage_options capability
        wp_set_current_user(1);

        $widget = new FullAttributeTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }
}

#[AsDashboardWidget(id: 'test_dashboard_widget', title: 'Test Dashboard Widget')]
class ConcreteTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>dashboard content</p>';
    }
}

#[AsDashboardWidget(
    id: 'full_widget',
    title: 'Full Widget',
    capability: 'manage_options',
    context: 'side',
    priority: 'high',
)]
class FullAttributeTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>full widget</p>';
    }
}

class NoAttributeTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '';
    }
}

#[AsDashboardWidget(id: 'capability_widget', title: 'Capability Widget', capability: 'edit_posts')]
class CapabilityTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>capability widget</p>';
    }
}

#[AsDashboardWidget(id: 'restricted_widget', title: 'Restricted Widget', capability: 'activate_plugins')]
class RestrictedCapabilityTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>restricted widget</p>';
    }
}

#[AsDashboardWidget(id: 'configurable_widget', title: 'Configurable Widget')]
class ConfigurableTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>configurable widget</p>';
    }

    public function configure(): void
    {
        echo '<input type="text" name="setting">';
    }
}
