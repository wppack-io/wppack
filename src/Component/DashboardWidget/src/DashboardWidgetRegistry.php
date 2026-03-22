<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget;

use WpPack\Component\Templating\TemplateRendererInterface;

final class DashboardWidgetRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractDashboardWidget $widget): void
    {
        if ($this->renderer !== null) {
            $widget->setTemplateRenderer($this->renderer);
        }

        $widget->register();
    }

    public function unregister(string $widgetId): void
    {
        remove_meta_box($widgetId, 'dashboard', 'normal');
    }
}
