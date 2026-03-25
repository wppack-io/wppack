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

namespace WpPack\Component\DashboardWidget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WpPack\Component\DashboardWidget\DashboardWidgetRegistry;
use WpPack\Component\Templating\TemplateRendererInterface;

final class DashboardWidgetRegistryTest extends TestCase
{
    private DashboardWidgetRegistry $registry;

    protected function setUp(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;
        $wp_meta_boxes = [];

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
    public function unregisterCallsRemoveMetaBox(): void
    {
        $this->registry->unregister('test_registry_widget');

        // If no exception was thrown, removal succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function registerRegistersWidgetInMetaBoxes(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        self::assertArrayHasKey('dashboard', $wp_meta_boxes);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function unregisterRemovesWidgetFromMetaBoxes(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);

        $this->registry->unregister($widget->id);

        self::assertFalse($wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id]);
    }

    #[Test]
    public function registerAndUnregisterRoundTrip(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();

        $this->registry->register($widget);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);

        $this->registry->unregister($widget->id);
        self::assertFalse($wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id]);
    }

    #[Test]
    public function registerSetsTemplateRendererWhenProvided(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new DashboardWidgetRegistry($renderer);

        $widget = new RegistryTestDashboardWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractDashboardWidget::class, 'renderer');
        self::assertSame($renderer, $ref->getValue($widget));
    }

    #[Test]
    public function registerPassesWidgetPropertiesToMetaBox(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        $entry = $wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id];
        self::assertSame('test_registry_widget', $entry['id']);
        self::assertSame('Registry Test Widget', $entry['title']);
    }
}

#[AsDashboardWidget(id: 'test_registry_widget', label: 'Registry Test Widget')]
class RegistryTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>registry test</p>';
    }
}
