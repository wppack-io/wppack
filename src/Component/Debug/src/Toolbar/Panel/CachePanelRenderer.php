<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'cache')]
final class CachePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'cache';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
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
        $hitRateColor = match (true) {
            $hitRate >= 80 => 'wpd-text-green',
            $hitRate >= 50 => 'wpd-text-yellow',
            default => 'wpd-text-red',
        };
        $barColor = match (true) {
            $hitRate >= 80 => 'var(--wpd-green)',
            $hitRate >= 50 => 'var(--wpd-yellow)',
            default => 'var(--wpd-red)',
        };
        $hitRateValue = '<span class="wpd-inline-bar"><span class="wpd-inline-bar-fill" style="width:' . $this->esc(sprintf('%.1f', min($hitRate, 100))) . '%;background:' . $this->esc($barColor) . '"></span></span>'
            . '<span class="' . $hitRateColor . '">' . $this->esc(sprintf('%.1f%%', $hitRate)) . '</span>';
        $html .= $this->renderTableRow('Hit Rate', $hitRateValue);
        $html .= '</table>';
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
                    ? '<span class="wpd-query-tag" style="background:var(--wpd-green-a8);color:var(--wpd-green)">SET</span>'
                    : '<span class="wpd-query-tag" style="background:var(--wpd-red-a8);color:var(--wpd-red)">DELETE</span>';

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
