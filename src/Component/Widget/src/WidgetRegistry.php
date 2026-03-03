<?php

declare(strict_types=1);

namespace WpPack\Component\Widget;

final class WidgetRegistry
{
    /**
     * @param class-string<\WP_Widget> $widgetClass
     */
    public function register(string $widgetClass): void
    {
        register_widget($widgetClass);
    }

    /**
     * @param class-string<\WP_Widget> $widgetClass
     */
    public function unregister(string $widgetClass): void
    {
        unregister_widget($widgetClass);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function registerSidebar(array $args): void
    {
        register_sidebar($args);
    }
}
