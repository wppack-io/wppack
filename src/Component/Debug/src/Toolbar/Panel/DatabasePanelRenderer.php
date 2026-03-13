<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'database')]
final class DatabasePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $totalCount = (int) ($data['total_count'] ?? 0);
        $totalTime = (float) ($data['total_time'] ?? 0.0);
        $duplicateCount = (int) ($data['duplicate_count'] ?? 0);
        $slowCount = (int) ($data['slow_count'] ?? 0);
        /** @var list<string> $suggestions */
        $suggestions = $data['suggestions'] ?? [];
        /** @var list<array{sql: string, time: float, caller: string, start?: float, data: array<string, mixed>}> $queries */
        $queries = $data['queries'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Queries', (string) $totalCount);
        $html .= $this->renderTableRow('Total Time', $this->formatMs($totalTime));
        $html .= $this->renderTableRow('Duplicate Queries', (string) $duplicateCount, $duplicateCount > 0 ? 'wpd-text-yellow' : '');
        $html .= $this->renderTableRow('Slow Queries', (string) $slowCount, $slowCount > 0 ? 'wpd-text-red' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($suggestions !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Suggestions</h4>';
            $html .= '<ul class="wpd-suggestions">';
            foreach ($suggestions as $suggestion) {
                $html .= '<li class="wpd-suggestion-item">' . $this->esc($suggestion) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if ($queries !== []) {
            // Caller grouping
            $callerStats = [];
            foreach ($queries as $query) {
                $caller = $query['caller'];
                $callerStats[$caller] ??= ['count' => 0, 'total_time' => 0.0];
                $callerStats[$caller]['count']++;
                $callerStats[$caller]['total_time'] += $query['time'];
            }
            uasort($callerStats, static fn(array $a, array $b): int => $b['total_time'] <=> $a['total_time']);

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Queries by Caller</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Caller</th>';
            $html .= '<th class="wpd-col-right">Count</th>';
            $html .= '<th class="wpd-col-right">Total Time</th>';
            $html .= '<th class="wpd-col-right">Avg Time</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($callerStats as $caller => $stats) {
                $avgTime = $stats['total_time'] / $stats['count'];
                $countClass = $stats['count'] > 5 ? ' wpd-text-yellow' : '';

                // Show only the last entry for long caller strings
                $shortCaller = $caller;
                $parts = preg_split('/,\s*/', $caller);
                if ($parts !== false && count($parts) > 1) {
                    $shortCaller = end($parts);
                }

                $html .= '<tr>';
                $html .= '<td title="' . $this->esc($caller) . '"><span class="wpd-caller">' . $this->esc($shortCaller) . '</span></td>';
                $html .= '<td class="wpd-col-right' . $countClass . '">' . $this->esc((string) $stats['count']) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs($stats['total_time']) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs($avgTime) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';

            // Count duplicates for highlighting
            $sqlCounts = [];
            foreach ($queries as $query) {
                $sql = $query['sql'];
                $sqlCounts[$sql] = ($sqlCounts[$sql] ?? 0) + 1;
            }

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Queries</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th class="wpd-col-reltime">Time</th>';
            $html .= '<th class="wpd-col-sql">SQL</th>';
            $html .= '<th class="wpd-col-time">Duration</th>';
            $html .= '<th class="wpd-col-caller">Caller</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($queries as $index => $query) {
                $sql = $query['sql'];
                $timeMs = (float) $query['time'];
                $isSlow = $timeMs > 100.0;
                $isDuplicate = ($sqlCounts[$sql] ?? 0) > 1;

                $rowClass = '';
                if ($isSlow) {
                    $rowClass = 'wpd-row-slow';
                } elseif ($isDuplicate) {
                    $rowClass = 'wpd-row-duplicate';
                }

                $badges = '';
                if ($isSlow) {
                    $badges .= '<span class="wpd-query-tag wpd-tag-slow">SLOW</span>';
                }
                if ($isDuplicate) {
                    $badges .= '<span class="wpd-query-tag wpd-tag-dup">DUP</span>';
                }

                $startTime = (float) ($query['start'] ?? 0);
                $relTime = $this->formatRelativeTime($startTime);

                $html .= '<tr class="' . $rowClass . '">';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $relTime . '</td>';
                $html .= '<td class="wpd-col-sql"><code>' . $this->esc($sql) . '</code>' . $badges . '</td>';
                $html .= '<td class="wpd-col-time">' . $this->formatMs($timeMs) . '</td>';
                $html .= '<td class="wpd-col-caller"><span class="wpd-caller">' . $this->esc($query['caller']) . '</span></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
