<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget;

use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\Templating\TemplateRendererInterface;

final class DashboardWidgetRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?ArgumentResolver $argumentResolver = null,
    ) {}

    public function register(AbstractDashboardWidget $widget): void
    {
        if ($this->renderer !== null) {
            $widget->setTemplateRenderer($this->renderer);
        }

        $resolver = $this->argumentResolver?->createResolver($widget);
        if ($resolver !== null) {
            $widget->setInvokeArgumentResolver($resolver);
        }

        $configureResolver = $this->argumentResolver?->createResolver($widget, 'configure');
        if ($configureResolver !== null) {
            $widget->setConfigureArgumentResolver($configureResolver);
        }

        $widget->register();
    }

    public function unregister(string $widgetId): void
    {
        remove_meta_box($widgetId, 'dashboard', 'normal');
    }
}
