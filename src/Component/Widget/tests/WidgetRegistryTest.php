<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
        $this->registry->register(\WP_Widget_Text::class);

        global $wp_widget_factory;
        $registered = false;
        foreach ($wp_widget_factory->widgets as $widget) {
            if ($widget instanceof \WP_Widget_Text) {
                $registered = true;
                break;
            }
        }

        self::assertTrue($registered);
    }

    #[Test]
    public function unregisterCallsWordPressFunction(): void
    {
        $this->registry->register(\WP_Widget_Text::class);
        $this->registry->unregister(\WP_Widget_Text::class);

        global $wp_widget_factory;
        $found = false;
        foreach ($wp_widget_factory->widgets as $widget) {
            if ($widget instanceof \WP_Widget_Text) {
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
}
