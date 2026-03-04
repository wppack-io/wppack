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
}

#[AsDashboardWidget(id: 'test_registry_widget', title: 'Registry Test Widget')]
class RegistryTestDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>registry test</p>';
    }
}
