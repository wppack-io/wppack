<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'widget')]
final class WidgetPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'widget';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $totalWidgets = (int) ($data['total_widgets'] ?? 0);
        $totalSidebars = (int) ($data['total_sidebars'] ?? 0);
        $activeWidgets = (int) ($data['active_widgets'] ?? 0);
        $renderTime = (float) ($data['render_time'] ?? 0.0);
        /** @var array<string, array{name: string, widgets: list<string>}> $sidebars */
        $sidebars = $data['sidebars'] ?? [];
        /** @var list<array{sidebar: string, name: string, start: float, duration: float}> $sidebarTimings */
        $sidebarTimings = $data['sidebar_timings'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Sidebars', (string) $totalSidebars);
        $html .= $this->renderTableRow('Total Widgets', (string) $totalWidgets);
        $html .= $this->renderTableRow('Active Widgets', (string) $activeWidgets);
        if ($renderTime > 0) {
            $html .= $this->renderTableRow('Render Time', $this->formatMs($renderTime));
        }
        $html .= '</table>';
        $html .= '</div>';

        // Sidebar Render Times
        if ($sidebarTimings !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Sidebar Render Times</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Sidebar</th>';
            $html .= '<th class="wpd-col-time">Duration</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($sidebarTimings as $timing) {
                $html .= '<tr>';
                $html .= '<td>' . $this->esc($timing['name']) . '</td>';
                $html .= '<td class="wpd-col-time">' . $this->formatMs($timing['duration']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Sidebars
        if ($sidebars !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Sidebars</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Name</th>';
            $html .= '<th>Widgets</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($sidebars as $id => $sidebar) {
                $widgetTags = '';
                if ($sidebar['widgets'] !== []) {
                    foreach ($sidebar['widgets'] as $widget) {
                        $widgetTags .= '<span class="wpd-tag">' . $this->esc($widget) . '</span>';
                    }
                } else {
                    $widgetTags = '<span class="wpd-text-dim">empty</span>';
                }

                $html .= '<tr>';
                $html .= '<td>' . $this->esc($sidebar['name']) . '</td>';
                $html .= '<td><div class="wpd-tag-list">' . $widgetTags . '</div></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
