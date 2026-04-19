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

namespace WPPack\Component\Debug\Adapter;

use WPPack\Component\Debug\Attribute\AsDataCollector;
use WPPack\Component\Debug\DataCollector\AbstractDataCollector;

#[AsDataCollector(name: 'debug_bar_panel', priority: -100)]
final class DebugBarPanelAdapter extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'debug_bar_panel';
    }

    public function getLabel(): string
    {
        return 'Debug Bar';
    }

    public function collect(): void
    {
        // Guard: only run if Debug Bar plugin is active
        if (!class_exists('Debug_Bar_Panel')) {
            return;
        }

        // Get registered panels via debug_bar_panels filter
        /** @var list<object> $panels */
        $panels = apply_filters('debug_bar_panels', []);

        $panelData = [];
        foreach ($panels as $panel) {
            if (!method_exists($panel, 'render') || !method_exists($panel, 'title')) {
                continue;
            }

            ob_start();
            $panel->render();
            $html = ob_get_clean();

            $panelData[] = [
                'title' => $panel->title(),
                'html' => $this->sanitizeHtml($html ?: ''),
            ];
        }

        $this->data = [
            'panels' => $panelData,
            'panel_count' => count($panelData),
        ];
    }

    public function getIndicatorValue(): string
    {
        $count = $this->data['panel_count'] ?? 0;

        return $count > 0 ? (string) $count : '';
    }

    private function sanitizeHtml(string $html): string
    {
        return wp_kses_post($html);
    }
}
