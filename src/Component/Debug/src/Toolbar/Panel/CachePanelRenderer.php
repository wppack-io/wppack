<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class CachePanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    private const BADGE_COLORS = [
        'green' => '#a6e3a1',
        'yellow' => '#f9e2af',
        'red' => '#f38ba8',
        'default' => '#cdd6f4',
    ];

    public function getName(): string
    {
        return 'cache';
    }

    public function render(array $data): string
    {
        $hits = (int) ($data['hits'] ?? 0);
        $misses = (int) ($data['misses'] ?? 0);
        $hitRate = (float) ($data['hit_rate'] ?? 0.0);
        $transientSets = (int) ($data['transient_sets'] ?? 0);
        $transientDeletes = (int) ($data['transient_deletes'] ?? 0);
        $dropin = (string) ($data['object_cache_dropin'] ?? '');
        /** @var list<array{name: string, operation: string, expiration: int, caller: string}> $transientOps */
        $transientOps = $data['transient_operations'] ?? [];
        /** @var array<string, int> $cacheGroups */
        $cacheGroups = $data['cache_groups'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Object Cache</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        if ($dropin !== '') {
            $html .= $this->renderTableRow('Drop-in', $this->esc($dropin));
        }
        $html .= $this->renderTableRow('Cache Hits', (string) $hits);
        $html .= $this->renderTableRow('Cache Misses', (string) $misses);
        $html .= $this->renderTableRow('Hit Rate', sprintf('%.1f%%', $hitRate));
        $html .= '</table>';

        // Hit rate bar
        $html .= '<div class="wpd-memory-bar-wrap">';
        $barColor = match (true) {
            $hitRate >= 80 => self::BADGE_COLORS['green'],
            $hitRate >= 50 => self::BADGE_COLORS['yellow'],
            default => self::BADGE_COLORS['red'],
        };
        $html .= '<div class="wpd-memory-bar" style="width:' . $this->esc(sprintf('%.1f', min($hitRate, 100))) . '%;background:' . $this->esc($barColor) . '"></div>';
        $html .= '</div>';
        $html .= '</div>';

        // Transients section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Transients</h4>';

        if ($transientOps !== []) {
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th>Name</th>';
            $html .= '<th>Operation</th>';
            $html .= '<th class="wpd-col-right">Expiration</th>';
            $html .= '<th>Caller</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($transientOps as $index => $op) {
                $expDisplay = match (true) {
                    $op['operation'] === 'delete' => "\xe2\x80\x94",
                    $op['expiration'] === 0 => 'none',
                    default => $this->esc((string) $op['expiration']) . ' s',
                };
                $opTag = $op['operation'] === 'set'
                    ? '<span class="wpd-query-tag" style="background:rgba(166,227,161,0.2);color:#a6e3a1">SET</span>'
                    : '<span class="wpd-query-tag" style="background:rgba(243,139,168,0.2);color:#f38ba8">DELETE</span>';

                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td><code>' . $this->esc($op['name']) . '</code></td>';
                $html .= '<td>' . $opTag . '</td>';
                $html .= '<td class="wpd-col-right">' . $expDisplay . '</td>';
                $html .= '<td><span class="wpd-caller">' . $this->esc($op['caller']) . '</span></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        } else {
            $html .= '<table class="wpd-table wpd-table-kv">';
            $html .= $this->renderTableRow('Transient Sets', (string) $transientSets);
            $html .= $this->renderTableRow('Transient Deletes', (string) $transientDeletes);
            $html .= '</table>';
        }

        $html .= '</div>';

        // Cache Groups section
        if ($cacheGroups !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Cache Groups</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>Group</th><th class="wpd-col-right">Entries</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($cacheGroups as $group => $count) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($group) . '</code></td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $count) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
