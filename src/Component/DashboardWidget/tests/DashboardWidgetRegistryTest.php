<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WpPack\Component\DashboardWidget\DashboardWidgetRegistry;

final class DashboardWidgetRegistryTest extends TestCase
{
    private DashboardWidgetRegistry $registry;

    protected function setUp(): void
    {
        if (!function_exists('wp_add_dashboard_widget')) {
            self::markTestSkipped('WordPress dashboard functions are not available.');
        }

        $this->registry = new DashboardWidgetRegistry();
    }

    #[Test]
    public function registerCallsWidgetRegister(): void
    {
        $widget = new RegistryTestDashboardWidget();

        $this->registry->register($widget);

        // If no exception was thrown, registration succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function removeCallsRemoveMetaBox(): void
    {
        $this->registry->remove('test_registry_widget');

        // If no exception was thrown, removal succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function registerRegistersWidgetInMetaBoxes(): void
    {
        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        self::assertArrayHasKey('dashboard', $wp_meta_boxes);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function removeRemovesWidgetFromMetaBoxes(): void
    {
        if (function_exists('set_current_screen')) {
            set_current_screen('dashboard');
        }

        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);

        $this->registry->remove($widget->id);

        self::assertFalse($wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id]);
    }
}

#[AsDashboardWidget(id: 'test_registry_widget', title: 'Registry Test Widget')]
class RegistryTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>registry test</p>';
    }
}
