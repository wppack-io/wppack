<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget;

use WpPack\Component\HttpFoundation\InvokeArgumentResolverTrait;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Security;
use WpPack\Component\Templating\TemplateRendererInterface;

final class DashboardWidgetRegistry
{
    use InvokeArgumentResolverTrait;

    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
    ) {}

    public function register(AbstractDashboardWidget $widget): void
    {
        if ($this->renderer !== null) {
            $widget->setTemplateRenderer($this->renderer);
        }

        $resolver = $this->createArgumentResolver($widget, $this->request, $this->security);
        if ($resolver !== null) {
            $widget->setInvokeArgumentResolver($resolver);
        }

        $configureResolver = $this->createArgumentResolver($widget, $this->request, $this->security, 'configure');
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
