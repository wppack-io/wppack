<?php

declare(strict_types=1);

namespace WpPack\Component\Widget;

use WpPack\Component\HttpFoundation\InvokeArgumentResolverTrait;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Security;
use WpPack\Component\Templating\TemplateRendererInterface;

final class WidgetRegistry
{
    use InvokeArgumentResolverTrait;

    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
    ) {}

    public function register(AbstractWidget $widget): void
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

        register_widget($widget);
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
