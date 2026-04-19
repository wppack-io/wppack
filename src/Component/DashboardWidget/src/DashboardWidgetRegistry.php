<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\DashboardWidget;

use WPPack\Component\Templating\TemplateRendererInterface;

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
