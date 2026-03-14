<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'shortcode')]
final class ShortcodePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'shortcode';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $totalCount = (int) ($data['total_count'] ?? 0);
        $usedCount = (int) ($data['used_count'] ?? 0);
        $executionTime = (float) ($data['execution_time'] ?? 0.0);
        /** @var list<string> $usedShortcodes */
        $usedShortcodes = $data['used_shortcodes'] ?? [];
        /** @var array<string, array{tag: string, callback: string, used: bool}> $shortcodes */
        $shortcodes = $data['shortcodes'] ?? [];
        /** @var list<array{tag: string, start: float, duration: float}> $executions */
        $executions = $data['executions'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Registered', (string) $totalCount);
        $html .= $this->renderTableRow('Used in Content', (string) $usedCount);
        if ($executionTime > 0) {
            $html .= $this->renderTableRow('Execution Time', $this->formatMs($executionTime));
        }
        $html .= '</table>';
        $html .= '</div>';

        // Execution Times
        if ($executions !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Execution Times</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Tag</th>';
            $html .= '<th class="wpd-col-time">Duration</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($executions as $exec) {
                $html .= '<tr>';
                $html .= '<td><code>[' . $this->esc($exec['tag']) . ']</code></td>';
                $html .= '<td class="wpd-col-time">' . $this->formatMs($exec['duration']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Used shortcodes
        if ($usedShortcodes !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Used in Current Page</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($usedShortcodes as $tag) {
                $html .= '<span class="wpd-tag wpd-text-green">' . $this->esc($tag) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // All shortcodes
        if ($shortcodes !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">All Shortcodes</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Tag</th>';
            $html .= '<th>Callback</th>';
            $html .= '<th>Used</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($shortcodes as $info) {
                $usedHtml = $info['used']
                    ? '<span class="wpd-text-green">Yes</span>'
                    : '<span class="wpd-text-dim">No</span>';

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($info['tag']) . '</code></td>';
                $html .= '<td class="wpd-text-dim">' . $this->esc($info['callback']) . '</td>';
                $html .= '<td>' . $usedHtml . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
