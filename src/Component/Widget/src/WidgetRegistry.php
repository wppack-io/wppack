<?php

declare(strict_types=1);

namespace WpPack\Component\Widget;

use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\Templating\TemplateRendererInterface;

final class WidgetRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?ArgumentResolver $argumentResolver = null,
    ) {}

    public function register(AbstractWidget $widget): void
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
