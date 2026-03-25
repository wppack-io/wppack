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

namespace WpPack\Component\Widget;

use WpPack\Component\Templating\TemplateRendererInterface;

final class WidgetRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractWidget $widget): void
    {
        if ($this->renderer !== null) {
            $widget->setTemplateRenderer($this->renderer);
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
