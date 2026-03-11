<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class EventPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'event';
    }

    public function render(array $data): string
    {
        $totalFirings = (int) ($data['total_firings'] ?? 0);
        $uniqueHooks = (int) ($data['unique_hooks'] ?? 0);
        $registeredHooks = (int) ($data['registered_hooks'] ?? 0);
        $orphanHooks = (int) ($data['orphan_hooks'] ?? 0);
        /** @var array<string, int> $topHooks */
        $topHooks = $data['top_hooks'] ?? [];
        /** @var array<string, array{count: int, total_time: float, start: float}> $hookTimings */
        $hookTimings = $data['hook_timings'] ?? [];
        /** @var array<string, int> $listenerCounts */
        $listenerCounts = $data['listener_counts'] ?? [];
        /** @var array<string, array{type: string, hooks: int, listeners: int, total_time: float}> $componentSummary */
        $componentSummary = $data['component_summary'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Firings', (string) $totalFirings);
        $html .= $this->renderTableRow('Unique Hooks', (string) $uniqueHooks);
        $html .= $this->renderTableRow('Registered Hooks', (string) $registeredHooks);
        $html .= $this->renderTableRow('Orphan Hooks', (string) $orphanHooks, $orphanHooks > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';
        $html .= '</div>';

        // Component Summary section
        if ($componentSummary !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Component Summary</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Component</th>';
            $html .= '<th>Type</th>';
            $html .= '<th class="wpd-col-right">Hooks</th>';
            $html .= '<th class="wpd-col-right">Listeners</th>';
            $html .= '<th class="wpd-col-right">Duration</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($componentSummary as $component => $summary) {
                $typeTag = match ($summary['type']) {
                    'plugin' => '<span class="wpd-query-tag" style="background:rgba(245,194,231,0.2);color:#f5c2e7">plugin</span>',
                    'theme' => '<span class="wpd-query-tag" style="background:rgba(250,179,135,0.2);color:#fab387">theme</span>',
                    'core' => '<span class="wpd-query-tag" style="background:rgba(137,180,250,0.2);color:#89b4fa">core</span>',
                    default => '<span class="wpd-tag">' . $this->esc($summary['type']) . '</span>',
                };

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc((string) $component) . '</code></td>';
                $html .= '<td>' . $typeTag . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $summary['hooks']) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $summary['listeners']) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs((float) $summary['total_time']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if ($topHooks !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Top Hooks</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th>Hook</th>';
            $html .= '<th class="wpd-col-right">Firings</th>';
            $html .= '<th class="wpd-col-right">Listeners</th>';
            $html .= '<th class="wpd-col-right">Time</th>';
            $html .= '<th class="wpd-col-right">Duration</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            $index = 0;
            foreach ($topHooks as $hook => $count) {
                $listeners = $listenerCounts[$hook] ?? 0;
                $timing = $hookTimings[$hook] ?? null;
                $duration = $timing !== null ? $this->formatMs($timing['total_time']) : '-';
                $hookStart = $timing !== null ? $this->esc('+' . number_format(max(0, $timing['start']), 0)) : '-';

                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) (++$index)) . '</td>';
                $html .= '<td><code>' . $this->esc($hook) . '</code></td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $count) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $listeners) . '</td>';
                $html .= '<td class="wpd-col-right wpd-text-dim">' . $hookStart . '</td>';
                $html .= '<td class="wpd-col-right">' . $duration . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
