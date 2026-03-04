<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget;

final class DashboardWidgetRegistry
{
    public function register(AbstractDashboardWidget $widget): void
    {
        $widget->register();
    }

    public function remove(string $widgetId): void
    {
        remove_meta_box($widgetId, 'dashboard', 'normal');
    }
}
