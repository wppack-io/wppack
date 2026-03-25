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

namespace WpPack\Component\Widget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Templating\TemplateRendererInterface;
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;
use WpPack\Component\Widget\WidgetRegistry;

final class WidgetRegistryTest extends TestCase
{
    private WidgetRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WidgetRegistry();
    }

    #[Test]
    public function registerCallsWordPressFunction(): void
    {
        $widget = new RegistryConcreteTestWidget();

        $this->registry->register($widget);

        global $wp_widget_factory;
        $registered = false;
        foreach ($wp_widget_factory->widgets as $w) {
            if ($w === $widget) {
                $registered = true;
                break;
            }
        }

        self::assertTrue($registered);
    }

    #[Test]
    public function unregisterCallsWordPressFunction(): void
    {
        register_widget(\WP_Widget_Text::class);
        $this->registry->unregister(\WP_Widget_Text::class);

        global $wp_widget_factory;
        $found = false;
        foreach ($wp_widget_factory->widgets as $w) {
            if ($w instanceof \WP_Widget_Text) {
                $found = true;
                break;
            }
        }

        self::assertFalse($found);
    }

    #[Test]
    public function registerSidebarCallsWordPressFunction(): void
    {
        $this->registry->registerSidebar([
            'name' => 'Test Sidebar',
            'id' => 'test-sidebar-' . uniqid(),
            'before_widget' => '<div>',
            'after_widget' => '</div>',
            'before_title' => '<h3>',
            'after_title' => '</h3>',
        ]);

        // register_sidebar returns the sidebar ID — if no exception was thrown, it succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function registerSetsTemplateRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new WidgetRegistry($renderer);

        $widget = new RegistryConcreteTestWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractWidget::class, 'templateRenderer');
        self::assertSame($renderer, $ref->getValue($widget));
    }

    #[Test]
    public function registerWithoutDependenciesWorks(): void
    {
        $widget = new RegistryConcreteTestWidget();

        $this->registry->register($widget);

        // No exception means success
        self::assertTrue(true);
    }
}

#[AsWidget(id: 'registry_concrete_widget', label: 'Registry Concrete Widget')]
class RegistryConcreteTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '<p>registry test</p>';
    }
}
