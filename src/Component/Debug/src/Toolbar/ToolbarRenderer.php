<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;

final class ToolbarRenderer
{
    private const ICONS = [
        'performance' => "\xF0\x9F\x9A\x80",
        'request' => "\xF0\x9F\x8C\x90",
        'database' => "\xF0\x9F\x92\xBE",
        'memory' => "\xF0\x9F\x93\x8A",
        'time' => "\xE2\x8F\xB1\xEF\xB8\x8F",
        'cache' => "\xF0\x9F\x93\xA6",
        'wordpress' => "\xE2\x9A\x99\xEF\xB8\x8F",
        'user' => "\xF0\x9F\x91\xA4",
        'mail' => "\xE2\x9C\x89\xEF\xB8\x8F",
        'event' => "\xF0\x9F\x94\x94",
        'logger' => "\xF0\x9F\x93\x9D",
        'router' => "\xF0\x9F\x9B\xA4\xEF\xB8\x8F",
        'http_client' => "\xF0\x9F\x94\x97",
        'translation' => "\xF0\x9F\x94\xA0",
        'dump' => "\xF0\x9F\x93\x8C",
        'plugin' => "\xF0\x9F\x94\x8C",
        'theme' => "\xF0\x9F\x8E\xA8",
        'scheduler' => "\xE2\x8F\xB0",
    ];

    private const DEFAULT_ICON = "\xF0\x9F\x93\x8B";

    private const BADGE_COLORS = [
        'green' => '#a6e3a1',
        'yellow' => '#f9e2af',
        'red' => '#f38ba8',
        'default' => '#cdd6f4',
    ];

    private float $requestTimeFloat = 0.0;

    public function render(Profile $profile): string
    {
        $collectors = $profile->getCollectors();

        // Extract request_time_float for relative time display across panels
        if (isset($collectors['time'])) {
            $timeData = $collectors['time']->getData();
            $this->requestTimeFloat = (float) ($timeData['request_time_float'] ?? 0.0);
        }

        $badges = '';
        $panels = '';

        foreach ($collectors as $name => $collector) {
            $badges .= $this->renderBadge($collector);
            $panels .= $this->renderPanel($collector);
        }

        $perfBadge = $this->renderPerformanceBadge($profile);
        $perfPanel = $this->renderPerformancePanel($profile);
        $badges = $perfBadge . $badges;
        $panels = $perfPanel . $panels;

        $requestInfo = $this->esc($profile->getMethod()) . ' ' . $this->esc((string) $profile->getStatusCode());
        $totalTime = $this->formatMs($profile->getTime());

        $css = $this->renderCss();
        $js = $this->renderJs();

        return <<<HTML
        <div id="wppack-debug">
        <style>{$css}</style>
        {$panels}
        <div class="wpd-mini" title="Show WpPack Debug Toolbar">
            <span class="wpd-mini-logo">WP</span>
        </div>
        <div class="wpd-bar">
            <div class="wpd-bar-logo" title="WpPack Debug">
                <span class="wpd-logo-text">WP</span>
            </div>
            <div class="wpd-bar-badges">
                {$badges}
            </div>
            <div class="wpd-bar-meta">
                <span class="wpd-meta-item">{$requestInfo}</span>
                <span class="wpd-meta-sep">|</span>
                <span class="wpd-meta-item">{$totalTime}</span>
            </div>
            <button class="wpd-close-btn" data-action="minimize" title="Close toolbar">&times;</button>
        </div>
        <script>{$js}</script>
        </div>
        HTML;
    }

    private function renderBadge(DataCollectorInterface $collector): string
    {
        $name = $this->esc($collector->getName());
        $label = $this->esc($collector->getLabel());
        $value = $this->esc($collector->getBadgeValue());
        $colorKey = $collector->getBadgeColor();
        $color = self::BADGE_COLORS[$colorKey] ?? self::BADGE_COLORS['default'];
        $icon = self::ICONS[$collector->getName()] ?? self::DEFAULT_ICON;

        return <<<HTML
        <button class="wpd-badge" data-panel="{$name}" title="{$label}">
            <span class="wpd-badge-icon">{$icon}</span>
            <span class="wpd-badge-value" style="color:{$color}">{$value}</span>
        </button>
        HTML;
    }

    private function renderPanel(DataCollectorInterface $collector): string
    {
        $name = $this->esc($collector->getName());
        $label = $this->esc($collector->getLabel());
        $icon = self::ICONS[$collector->getName()] ?? self::DEFAULT_ICON;
        $content = $this->renderPanelContent($collector);

        return <<<HTML
        <div class="wpd-panel" id="wpd-panel-{$name}" style="display:none">
            <div class="wpd-panel-header">
                <span class="wpd-panel-title">{$icon} {$label}</span>
                <button class="wpd-panel-close" data-action="close-panel" title="Close">&times;</button>
            </div>
            <div class="wpd-panel-body">
                {$content}
            </div>
        </div>
        HTML;
    }

    private function renderPanelContent(DataCollectorInterface $collector): string
    {
        $name = $collector->getName();
        $data = $collector->getData();

        return match ($name) {
            'database' => $this->renderDatabasePanel($data),
            'time' => $this->renderTimePanel($data),
            'memory' => $this->renderMemoryPanel($data),
            'request' => $this->renderRequestPanel($data),
            'cache' => $this->renderCachePanel($data),
            'wordpress' => $this->renderWordPressPanel($data),
            'user' => $this->renderUserPanel($data),
            'mail' => $this->renderMailPanel($data),
            'event' => $this->renderEventPanel($data),
            'logger' => $this->renderLoggerPanel($data),
            'router' => $this->renderRouterPanel($data),
            'http_client' => $this->renderHttpClientPanel($data),
            'translation' => $this->renderTranslationPanel($data),
            'dump' => $this->renderDumpPanel($data),
            'plugin' => $this->renderPluginPanel($data),
            'theme' => $this->renderThemePanel($data),
            'scheduler' => $this->renderSchedulerPanel($data),
            default => $this->renderGenericPanel($data),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderDatabasePanel(array $data): string
    {
        $totalCount = (int) ($data['total_count'] ?? 0);
        $totalTime = (float) ($data['total_time'] ?? 0.0);
        $duplicateCount = (int) ($data['duplicate_count'] ?? 0);
        $slowCount = (int) ($data['slow_count'] ?? 0);
        /** @var list<string> $suggestions */
        $suggestions = $data['suggestions'] ?? [];
        /** @var list<array{sql: string, time: float, caller: string, start: float, data: array<string, mixed>}> $queries */
        $queries = $data['queries'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Queries', (string) $totalCount);
        $html .= $this->renderTableRow('Total Time', $this->formatMs($totalTime * 1000));
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
            $html .= '<th>Count</th>';
            $html .= '<th>Total Time</th>';
            $html .= '<th>Avg Time</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($callerStats as $caller => $stats) {
                $avgTime = $stats['total_time'] / $stats['count'];
                $countClass = $stats['count'] > 5 ? 'wpd-text-yellow' : '';

                // Show only the last entry for long caller strings
                $shortCaller = $caller;
                $parts = preg_split('/,\s*/', $caller);
                if ($parts !== false && count($parts) > 1) {
                    $shortCaller = end($parts);
                }

                $html .= '<tr>';
                $html .= '<td title="' . $this->esc($caller) . '"><span class="wpd-caller">' . $this->esc($shortCaller) . '</span></td>';
                $html .= '<td class="' . $countClass . '">' . $this->esc((string) $stats['count']) . '</td>';
                $html .= '<td>' . $this->formatMs($stats['total_time']) . '</td>';
                $html .= '<td>' . $this->formatMs($avgTime) . '</td>';
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

    /**
     * @param array<string, mixed> $data
     */
    private function renderTimePanel(array $data): string
    {
        $totalTime = (float) ($data['total_time'] ?? 0.0);
        /** @var array<string, float> $phases */
        $phases = $data['phases'] ?? [];
        /** @var array<string, array{name: string, category: string, duration: float, memory: int, start_time: float, end_time: float}> $events */
        $events = $data['events'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Time', $this->formatMs($totalTime));
        $html .= '</table>';
        $html .= '</div>';

        if ($events !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Stopwatch Events</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-reltime">Time</th>';
            $html .= '<th>Event</th>';
            $html .= '<th>Category</th>';
            $html .= '<th>Duration</th>';
            $html .= '<th>Memory</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($events as $event) {
                $startMs = (float) $event['start_time'];
                $relTime = $this->esc('+' . number_format(max(0, $startMs), 0) . ' ms');

                $html .= '<tr>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $relTime . '</td>';
                $html .= '<td>' . $this->esc($event['name']) . '</td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc($event['category']) . '</span></td>';
                $html .= '<td>' . $this->formatMs($event['duration']) . '</td>';
                $html .= '<td>' . $this->formatBytes($event['memory']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderMemoryPanel(array $data): string
    {
        $current = (int) ($data['current'] ?? 0);
        $peak = (int) ($data['peak'] ?? 0);
        $limit = (int) ($data['limit'] ?? 0);
        $usagePercentage = (float) ($data['usage_percentage'] ?? 0.0);
        /** @var array<string, int> $snapshots */
        $snapshots = $data['snapshots'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Current Usage', $this->formatBytes($current));
        $html .= $this->renderTableRow('Peak Usage', $this->formatBytes($peak));
        $html .= $this->renderTableRow('Memory Limit', $limit > 0 ? $this->formatBytes($limit) : 'Unlimited');
        $html .= $this->renderTableRow(
            'Usage',
            $this->esc(sprintf('%.1f%%', $usagePercentage)),
            match (true) {
                $usagePercentage >= 90 => 'wpd-text-red',
                $usagePercentage >= 70 => 'wpd-text-yellow',
                default => 'wpd-text-green',
            },
        );
        $html .= '</table>';

        // Memory usage bar
        $html .= '<div class="wpd-memory-bar-wrap">';
        $barColor = match (true) {
            $usagePercentage >= 90 => self::BADGE_COLORS['red'],
            $usagePercentage >= 70 => self::BADGE_COLORS['yellow'],
            default => self::BADGE_COLORS['green'],
        };
        $barWidth = min($usagePercentage, 100);
        $html .= '<div class="wpd-memory-bar" style="width:' . $this->esc(sprintf('%.1f', $barWidth)) . '%;background:' . $this->esc($barColor) . '"></div>';
        $html .= '</div>';
        $html .= '</div>';

        if ($snapshots !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Memory Snapshots</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Checkpoint</th>';
            $html .= '<th>Memory</th>';
            $html .= '<th>Delta</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            $previousMemory = 0;
            foreach ($snapshots as $snapshotLabel => $snapshotMemory) {
                $delta = $previousMemory > 0 ? $snapshotMemory - $previousMemory : 0;
                $deltaSign = $delta >= 0 ? '+' : '';
                $deltaClass = $delta > 1024 * 1024 ? 'wpd-text-yellow' : '';

                $html .= '<tr>';
                $html .= '<td>' . $this->esc($snapshotLabel) . '</td>';
                $html .= '<td>' . $this->formatBytes($snapshotMemory) . '</td>';
                $html .= '<td class="' . $deltaClass . '">' . $deltaSign . $this->formatBytes(abs($delta)) . '</td>';
                $html .= '</tr>';

                $previousMemory = $snapshotMemory;
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderRequestPanel(array $data): string
    {
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Request</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Method', (string) ($data['method'] ?? ''));
        $html .= $this->renderTableRow('URL', (string) ($data['url'] ?? ''));
        $html .= $this->renderTableRow('Status Code', (string) ($data['status_code'] ?? ''));
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, string> $requestHeaders */
        $requestHeaders = $data['request_headers'] ?? [];
        if ($requestHeaders !== []) {
            $html .= $this->renderKeyValueSection('Request Headers', $requestHeaders);
        }

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = $data['response_headers'] ?? [];
        if ($responseHeaders !== []) {
            $html .= $this->renderKeyValueSection('Response Headers', $responseHeaders);
        }

        /** @var array<string, mixed> $getParams */
        $getParams = $data['get_params'] ?? [];
        if ($getParams !== []) {
            $html .= $this->renderKeyValueSection('GET Parameters', $getParams);
        }

        /** @var array<string, mixed> $postParams */
        $postParams = $data['post_params'] ?? [];
        if ($postParams !== []) {
            $html .= $this->renderKeyValueSection('POST Parameters', $postParams);
        }

        /** @var array<string, mixed> $serverVars */
        $serverVars = $data['server_vars'] ?? [];
        if ($serverVars !== []) {
            $html .= $this->renderKeyValueSection('Server Variables', $serverVars);
        }

        /** @var list<array{url: string, args: array<string, mixed>, response: mixed}> $httpApiCalls */
        $httpApiCalls = $data['http_api_calls'] ?? [];
        if ($httpApiCalls !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">HTTP API Calls (' . $this->esc((string) count($httpApiCalls)) . ')</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>#</th><th>URL</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($httpApiCalls as $index => $call) {
                $html .= '<tr>';
                $html .= '<td>' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td><code>' . $this->esc($call['url']) . '</code></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderCachePanel(array $data): string
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
            $html .= '<th>Expiration</th>';
            $html .= '<th>Caller</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($transientOps as $index => $op) {
                $expDisplay = match (true) {
                    $op['operation'] === 'delete' => "\xe2\x80\x94",
                    $op['expiration'] === 0 => 'none',
                    default => $this->esc((string) $op['expiration']) . 's',
                };
                $opTag = $op['operation'] === 'set'
                    ? '<span class="wpd-query-tag" style="background:rgba(166,227,161,0.2);color:#a6e3a1">SET</span>'
                    : '<span class="wpd-query-tag" style="background:rgba(243,139,168,0.2);color:#f38ba8">DELETE</span>';

                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td><code>' . $this->esc($op['name']) . '</code></td>';
                $html .= '<td>' . $opTag . '</td>';
                $html .= '<td>' . $expDisplay . '</td>';
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
            $html .= '<thead><tr><th>Group</th><th>Entries</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($cacheGroups as $group => $count) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($group) . '</code></td>';
                $html .= '<td>' . $this->esc((string) $count) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderWordPressPanel(array $data): string
    {
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Environment</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('WordPress Version', (string) ($data['wp_version'] ?? 'N/A'));
        $html .= $this->renderTableRow('PHP Version', (string) ($data['php_version'] ?? 'N/A'));
        $html .= $this->renderTableRow('Environment', (string) ($data['environment_type'] ?? 'N/A'));
        $html .= $this->renderTableRow('Theme', (string) ($data['theme'] ?? 'N/A'));

        $isBlockTheme = (bool) ($data['is_block_theme'] ?? false);
        $themeTypeLabel = $isBlockTheme ? 'Block (FSE)' : 'Classic';
        $html .= $this->renderTableRow('Theme Type', '<span class="wpd-tag">' . $this->esc($themeTypeLabel) . '</span>');

        $isChildTheme = (bool) ($data['is_child_theme'] ?? false);
        if ($isChildTheme) {
            $html .= $this->renderTableRow('Child Theme', $this->esc((string) ($data['child_theme'] ?? '')));
            $html .= $this->renderTableRow('Parent Theme', $this->esc((string) ($data['parent_theme'] ?? '')));
        }

        $themeVersion = (string) ($data['theme_version'] ?? '');
        if ($themeVersion !== '') {
            $html .= $this->renderTableRow('Theme Version', $this->esc($themeVersion));
        }

        $html .= $this->renderTableRow('Multisite', ($data['is_multisite'] ?? false) ? 'Yes' : 'No');
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, bool|null> $constants */
        $constants = $data['constants'] ?? [];
        if ($constants !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Debug Constants</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            foreach ($constants as $constant => $value) {
                $display = match ($value) {
                    null => '<span class="wpd-text-dim">undefined</span>',
                    true => '<span class="wpd-text-green">true</span>',
                    false => '<span class="wpd-text-red">false</span>',
                };
                $html .= '<tr><td class="wpd-kv-key">' . $this->esc($constant) . '</td><td class="wpd-kv-val">' . $display . '</td></tr>';
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        /** @var list<string> $plugins */
        $plugins = $data['active_plugins'] ?? [];
        if ($plugins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Active Plugins (' . $this->esc((string) count($plugins)) . ')</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($plugins as $plugin) {
                $html .= '<li><code>' . $this->esc($plugin) . '</code></li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        /** @var list<string> $extensions */
        $extensions = $data['extensions'] ?? [];
        if ($extensions !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">PHP Extensions (' . $this->esc((string) count($extensions)) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($extensions as $ext) {
                $html .= '<span class="wpd-tag">' . $this->esc($ext) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderUserPanel(array $data): string
    {
        $isLoggedIn = (bool) ($data['is_logged_in'] ?? false);
        $username = (string) ($data['username'] ?? '');
        $displayName = (string) ($data['display_name'] ?? '');
        $email = (string) ($data['email'] ?? '');
        /** @var list<string> $roles */
        $roles = $data['roles'] ?? [];
        $isSuperAdmin = (bool) ($data['is_super_admin'] ?? false);
        $auth = (string) ($data['authentication'] ?? 'none');

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">User</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Logged In', $this->formatValue($isLoggedIn));
        $html .= $this->renderTableRow('Username', $this->esc($username ?: '-'));
        $html .= $this->renderTableRow('Display Name', $this->esc($displayName ?: '-'));
        $html .= $this->renderTableRow('Email', $this->esc($email ?: '-'));
        $html .= $this->renderTableRow('Authentication', $this->esc($auth));
        if ($isSuperAdmin) {
            $html .= $this->renderTableRow('Super Admin', '<span class="wpd-text-yellow">Yes</span>');
        }
        $html .= '</table>';
        $html .= '</div>';

        if ($roles !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Roles</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($roles as $role) {
                $html .= '<span class="wpd-tag">' . $this->esc($role) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        /** @var array<string, bool> $capabilities */
        $capabilities = $data['capabilities'] ?? [];
        if ($capabilities !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Capabilities (' . $this->esc((string) count($capabilities)) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($capabilities as $cap => $granted) {
                $color = $granted ? 'wpd-text-green' : 'wpd-text-red';
                $html .= '<span class="wpd-tag ' . $color . '">' . $this->esc($cap) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderMailPanel(array $data): string
    {
        $totalCount = (int) ($data['total_count'] ?? 0);
        $successCount = (int) ($data['success_count'] ?? 0);
        $failureCount = (int) ($data['failure_count'] ?? 0);
        /** @var list<array<string, mixed>> $emails */
        $emails = $data['emails'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Emails', (string) $totalCount);
        $html .= $this->renderTableRow('Sent', (string) $successCount, $successCount > 0 ? 'wpd-text-green' : '');
        $html .= $this->renderTableRow('Failed', (string) $failureCount, $failureCount > 0 ? 'wpd-text-red' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($emails !== []) {
            foreach ($emails as $index => $email) {
                $statusTag = match ($email['status'] ?? 'pending') {
                    'sent' => '<span class="wpd-query-tag" style="background:rgba(166,227,161,0.2);color:#a6e3a1">SENT</span>',
                    'failed' => '<span class="wpd-query-tag" style="background:rgba(243,139,168,0.2);color:#f38ba8">FAILED</span>',
                    default => '<span class="wpd-query-tag" style="background:rgba(249,226,175,0.2);color:#f9e2af">PENDING</span>',
                };

                $html .= '<div class="wpd-section">';
                $html .= '<h4 class="wpd-section-title">Email #' . $this->esc((string) ($index + 1)) . ' ' . $statusTag . '</h4>';

                // Headers table
                $html .= '<table class="wpd-table wpd-table-kv">';
                $to = $email['to'] ?? '';
                $toDisplay = is_array($to) ? implode(', ', $to) : (string) $to;
                $html .= $this->renderTableRow('To', '<code>' . $this->esc($toDisplay) . '</code>');
                $html .= $this->renderTableRow('Subject', $this->esc((string) ($email['subject'] ?? '')));

                $from = (string) ($email['from'] ?? '');
                if ($from !== '') {
                    $html .= $this->renderTableRow('From', '<code>' . $this->esc($from) . '</code>');
                }

                /** @var list<string> $cc */
                $cc = $email['cc'] ?? [];
                if ($cc !== []) {
                    $html .= $this->renderTableRow('Cc', '<code>' . $this->esc(implode(', ', $cc)) . '</code>');
                }

                /** @var list<string> $bcc */
                $bcc = $email['bcc'] ?? [];
                if ($bcc !== []) {
                    $html .= $this->renderTableRow('Bcc', '<code>' . $this->esc(implode(', ', $bcc)) . '</code>');
                }

                $replyTo = (string) ($email['reply_to'] ?? '');
                if ($replyTo !== '') {
                    $html .= $this->renderTableRow('Reply-To', '<code>' . $this->esc($replyTo) . '</code>');
                }

                $contentType = (string) ($email['content_type'] ?? '');
                if ($contentType !== '') {
                    $charset = (string) ($email['charset'] ?? '');
                    $ctDisplay = $contentType . ($charset !== '' ? '; charset=' . $charset : '');
                    $html .= $this->renderTableRow('Content-Type', $this->esc($ctDisplay));
                }

                $html .= $this->renderTableRow('Status', $statusTag);

                $error = (string) ($email['error'] ?? '');
                if ($error !== '') {
                    $html .= $this->renderTableRow('Error', '<span class="wpd-text-red">' . $this->esc($error) . '</span>');
                }

                $html .= '</table>';

                // Body preview
                $message = (string) ($email['message'] ?? '');
                if ($message !== '') {
                    $html .= '<div style="margin-top:8px">';
                    $html .= '<pre style="background:#181825;padding:8px 12px;border-radius:4px;overflow-x:auto;font-size:12px;color:#cdd6f4;margin:0;max-height:200px;overflow-y:auto">';
                    $html .= $this->esc($message);
                    $html .= '</pre>';
                    $html .= '</div>';
                }

                // Attachments
                /** @var list<array{filename: string, size: int}> $attachmentDetails */
                $attachmentDetails = $email['attachment_details'] ?? [];
                if ($attachmentDetails !== []) {
                    $html .= '<div style="margin-top:8px">';
                    $html .= '<table class="wpd-table wpd-table-full">';
                    $html .= '<thead><tr><th>Attachment</th><th>Size</th></tr></thead>';
                    $html .= '<tbody>';
                    foreach ($attachmentDetails as $att) {
                        $html .= '<tr>';
                        $html .= '<td><code>' . $this->esc($att['filename']) . '</code></td>';
                        $html .= '<td>' . $this->formatBytes($att['size']) . '</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table>';
                    $html .= '</div>';
                }

                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderEventPanel(array $data): string
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
            $html .= '<th>Hooks</th>';
            $html .= '<th>Listeners</th>';
            $html .= '<th>Duration</th>';
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
                $html .= '<td>' . $this->esc((string) $summary['hooks']) . '</td>';
                $html .= '<td>' . $this->esc((string) $summary['listeners']) . '</td>';
                $html .= '<td>' . $this->formatMs((float) $summary['total_time']) . '</td>';
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
            $html .= '<th>Firings</th>';
            $html .= '<th>Listeners</th>';
            $html .= '<th>Time</th>';
            $html .= '<th>Duration</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            $index = 0;
            foreach ($topHooks as $hook => $count) {
                $listeners = $listenerCounts[$hook] ?? 0;
                $timing = $hookTimings[$hook] ?? null;
                $duration = $timing !== null ? $this->formatMs($timing['total_time']) : '-';
                $hookStart = $timing !== null ? $this->esc('+' . number_format(max(0, $timing['start']), 0) . ' ms') : '-';

                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) (++$index)) . '</td>';
                $html .= '<td><code>' . $this->esc($hook) . '</code></td>';
                $html .= '<td>' . $this->esc((string) $count) . '</td>';
                $html .= '<td>' . $this->esc((string) $listeners) . '</td>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $hookStart . '</td>';
                $html .= '<td>' . $duration . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderLoggerPanel(array $data): string
    {
        $totalCount = (int) ($data['total_count'] ?? 0);
        $errorCount = (int) ($data['error_count'] ?? 0);
        $deprecationCount = (int) ($data['deprecation_count'] ?? 0);
        /** @var array<string, int> $levelCounts */
        $levelCounts = $data['level_counts'] ?? [];
        /** @var list<array<string, mixed>> $logs */
        $logs = $data['logs'] ?? [];

        $warningCount = (int) ($levelCounts['warning'] ?? 0);

        // Collect unique channels
        $channels = [];
        foreach ($logs as $log) {
            $ch = $log['channel'] ?? 'app';
            if (!in_array($ch, $channels, true)) {
                $channels[] = $ch;
            }
        }

        // Summary section
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Entries', (string) $totalCount);
        $html .= $this->renderTableRow('Errors', (string) $errorCount, $errorCount > 0 ? 'wpd-text-red' : '');
        $html .= $this->renderTableRow('Deprecations', (string) $deprecationCount, $deprecationCount > 0 ? 'wpd-text-orange' : '');
        $html .= $this->renderTableRow('Warnings', (string) $warningCount, $warningCount > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';

        if ($channels !== []) {
            $html .= '<div style="margin-top:8px" class="wpd-tag-list">';
            foreach ($channels as $ch) {
                $html .= '<span class="wpd-tag">' . $this->esc($ch) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($logs !== []) {
            // Count entries per tab
            $errorTabCount = 0;
            $deprecationTabCount = 0;
            $warningTabCount = 0;
            $infoTabCount = 0;
            $debugTabCount = 0;
            foreach ($logs as $log) {
                $lvl = $log['level'] ?? 'debug';
                if (in_array($lvl, ['emergency', 'alert', 'critical', 'error'], true)) {
                    $errorTabCount++;
                } elseif ($lvl === 'deprecation') {
                    $deprecationTabCount++;
                } elseif (in_array($lvl, ['warning', 'notice'], true)) {
                    $warningTabCount++;
                } elseif ($lvl === 'info') {
                    $infoTabCount++;
                } else {
                    $debugTabCount++;
                }
            }

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Log Entries</h4>';

            // Filter tabs
            $html .= '<div class="wpd-log-tabs">';
            $html .= '<button class="wpd-log-tab wpd-active" data-log-filter="all">All (' . $this->esc((string) count($logs)) . ')</button>';
            $html .= '<button class="wpd-log-tab" data-log-filter="error">Errors (' . $this->esc((string) $errorTabCount) . ')</button>';
            $html .= '<button class="wpd-log-tab" data-log-filter="deprecation">Deprecations (' . $this->esc((string) $deprecationTabCount) . ')</button>';
            $html .= '<button class="wpd-log-tab" data-log-filter="warning">Warnings (' . $this->esc((string) $warningTabCount) . ')</button>';
            $html .= '<button class="wpd-log-tab" data-log-filter="info">Info (' . $this->esc((string) $infoTabCount) . ')</button>';
            $html .= '<button class="wpd-log-tab" data-log-filter="debug">Debug (' . $this->esc((string) $debugTabCount) . ')</button>';
            $html .= '</div>';

            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th>Time</th>';
            $html .= '<th>Level</th>';
            $html .= '<th>Channel</th>';
            $html .= '<th>Message</th>';
            $html .= '<th>File</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($logs as $index => $log) {
                $level = $log['level'] ?? 'debug';
                $levelColor = match ($level) {
                    'emergency', 'alert', 'critical', 'error' => 'wpd-text-red',
                    'deprecation' => 'wpd-text-orange',
                    'warning' => 'wpd-text-yellow',
                    'info' => 'wpd-text-green',
                    default => 'wpd-text-dim',
                };

                $file = (string) ($log['file'] ?? '');
                $line = (int) ($log['line'] ?? 0);
                $fileDisplay = '';
                if ($file !== '') {
                    $basename = basename($file);
                    $fileDisplay = $line > 0 ? $basename . ':' . $line : $basename;
                }

                $timestamp = (float) ($log['timestamp'] ?? 0);
                $timeDisplay = $this->formatRelativeTime($timestamp);

                $context = $log['context'] ?? [];
                $hasContext = is_array($context) && $context !== [];
                $rowClass = $hasContext ? ' class="wpd-log-toggle"' : '';

                $html .= '<tr data-log-level="' . $this->esc($level) . '"' . $rowClass . '>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $this->esc($timeDisplay) . '</td>';
                $html .= '<td><span class="wpd-tag ' . $levelColor . '">' . $this->esc($level) . '</span></td>';
                $html .= '<td>' . $this->esc($log['channel'] ?? 'app') . '</td>';
                $html .= '<td><code>' . $this->esc($log['message'] ?? '') . '</code></td>';
                $html .= '<td title="' . $this->esc($file) . '">' . $this->esc($fileDisplay) . '</td>';
                $html .= '</tr>';

                if ($hasContext) {
                    $html .= '<tr class="wpd-log-context" style="display:none" data-log-level="' . $this->esc($level) . '">';
                    $html .= '<td colspan="6"><pre>' . $this->esc(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') . '</pre></td>';
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderRouterPanel(array $data): string
    {
        $template = (string) ($data['template'] ?? '');
        $templatePath = (string) ($data['template_path'] ?? '');
        $matchedRule = (string) ($data['matched_rule'] ?? '');
        $matchedQuery = (string) ($data['matched_query'] ?? '');
        $queryType = (string) ($data['query_type'] ?? '');
        $is404 = (bool) ($data['is_404'] ?? false);
        $rewriteRulesCount = (int) ($data['rewrite_rules_count'] ?? 0);
        $isBlockTheme = (bool) ($data['is_block_theme'] ?? false);

        $html = '';

        // Template section — FSE vs Classic
        if ($isBlockTheme) {
            /** @var array<string, mixed> $blockTemplate */
            $blockTemplate = $data['block_template'] ?? [];
            $slug = (string) ($blockTemplate['slug'] ?? '');
            $templateId = (string) ($blockTemplate['id'] ?? '');
            $source = (string) ($blockTemplate['source'] ?? '');
            $hasThemeFile = (bool) ($blockTemplate['has_theme_file'] ?? false);
            $filePath = (string) ($blockTemplate['file_path'] ?? '');

            $sourceLabel = $source === 'theme' ? 'Theme file' : ($source !== '' ? 'User customized (DB)' : '-');

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Block Template (FSE)</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            $html .= $this->renderTableRow('Template Slug', $this->esc($slug ?: '-'));
            $html .= $this->renderTableRow('Template ID', $templateId !== '' ? '<code>' . $this->esc($templateId) . '</code>' : '-');
            $html .= $this->renderTableRow('Source', $this->esc($sourceLabel));
            $html .= $this->renderTableRow('Has Theme File', $this->formatValue($hasThemeFile));
            $html .= $this->renderTableRow('File Path', $this->esc($filePath ?: '-'));
            $html .= '</table>';
            $html .= '</div>';

            /** @var list<array<string, mixed>> $parts */
            $parts = $blockTemplate['parts'] ?? [];
            if ($parts !== []) {
                $html .= '<div class="wpd-section">';
                $html .= '<h4 class="wpd-section-title">Template Parts</h4>';
                $html .= '<table class="wpd-table wpd-table-full">';
                $html .= '<thead><tr><th>Slug</th><th>Area</th><th>Source</th></tr></thead>';
                $html .= '<tbody>';
                foreach ($parts as $part) {
                    $partSource = (string) ($part['source'] ?? '');
                    $partSourceLabel = $partSource === 'theme' ? 'Theme file' : ($partSource !== '' ? 'User customized (DB)' : '-');
                    $html .= '<tr>';
                    $html .= '<td><code>' . $this->esc((string) ($part['slug'] ?? '')) . '</code></td>';
                    $html .= '<td>' . $this->esc((string) ($part['area'] ?? '')) . '</td>';
                    $html .= '<td>' . $this->esc($partSourceLabel) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Template (Classic)</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            $html .= $this->renderTableRow('Template', $this->esc($template ?: '-'));
            $html .= $this->renderTableRow('Template Path', $this->esc($templatePath ?: '-'));
            $html .= '</table>';
            $html .= '</div>';
        }

        // Route section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Route</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Query Type', $this->esc($queryType ?: '-'));
        $html .= $this->renderTableRow('Matched Rule', $matchedRule !== '' ? '<code>' . $this->esc($matchedRule) . '</code>' : '-');
        $html .= $this->renderTableRow('Matched Query', $this->esc($matchedQuery ?: '-'));
        $html .= $this->renderTableRow('404', $this->formatValue($is404));
        $html .= $this->renderTableRow('Rewrite Rules', (string) $rewriteRulesCount);
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, string> $queryVars */
        $queryVars = $data['query_vars'] ?? [];
        if ($queryVars !== []) {
            $html .= $this->renderKeyValueSection('Query Variables', $queryVars);
        }

        // Conditional tags
        $conditionals = [];
        if ($data['is_front_page'] ?? false) {
            $conditionals[] = 'is_front_page';
        }
        if ($data['is_singular'] ?? false) {
            $conditionals[] = 'is_singular';
        }
        if ($data['is_archive'] ?? false) {
            $conditionals[] = 'is_archive';
        }
        if ($is404) {
            $conditionals[] = 'is_404';
        }
        if ($conditionals !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Conditional Tags</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($conditionals as $tag) {
                $html .= '<span class="wpd-tag wpd-text-green">' . $this->esc($tag) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderHttpClientPanel(array $data): string
    {
        $totalCount = (int) ($data['total_count'] ?? 0);
        $totalTime = (float) ($data['total_time'] ?? 0.0);
        $errorCount = (int) ($data['error_count'] ?? 0);
        $slowCount = (int) ($data['slow_count'] ?? 0);
        /** @var list<array<string, mixed>> $requests */
        $requests = $data['requests'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Requests', (string) $totalCount);
        $html .= $this->renderTableRow('Total Time', $this->formatMs($totalTime));
        $html .= $this->renderTableRow('Errors', (string) $errorCount, $errorCount > 0 ? 'wpd-text-red' : '');
        $html .= $this->renderTableRow('Slow Requests', (string) $slowCount, $slowCount > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($requests !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Requests</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th>Time</th>';
            $html .= '<th>Method</th>';
            $html .= '<th>URL</th>';
            $html .= '<th>Status</th>';
            $html .= '<th>Duration</th>';
            $html .= '<th>Size</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($requests as $index => $request) {
                $statusCode = (int) ($request['status_code'] ?? 0);
                $statusColor = match (true) {
                    $statusCode >= 200 && $statusCode < 300 => 'wpd-text-green',
                    $statusCode >= 300 && $statusCode < 400 => 'wpd-text-yellow',
                    $statusCode === 0 => 'wpd-text-dim',
                    default => 'wpd-text-red',
                };
                $error = ($request['error'] ?? '') !== '' ? '<br><small class="wpd-text-red">' . $this->esc($request['error']) . '</small>' : '';

                $startTime = (float) ($request['start'] ?? 0);
                $relTime = $this->formatRelativeTime($startTime);

                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $relTime . '</td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc($request['method'] ?? 'GET') . '</span></td>';
                $html .= '<td><code>' . $this->esc($request['url'] ?? '') . '</code></td>';
                $html .= '<td class="' . $statusColor . '">' . ($statusCode > 0 ? $this->esc((string) $statusCode) : '-') . $error . '</td>';
                $html .= '<td>' . $this->formatMs((float) ($request['duration'] ?? 0.0)) . '</td>';
                $html .= '<td>' . $this->formatBytes((int) ($request['response_size'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTranslationPanel(array $data): string
    {
        $totalLookups = (int) ($data['total_lookups'] ?? 0);
        $missingCount = (int) ($data['missing_count'] ?? 0);
        /** @var list<string> $loadedDomains */
        $loadedDomains = $data['loaded_domains'] ?? [];
        /** @var array<string, int> $domainUsage */
        $domainUsage = $data['domain_usage'] ?? [];
        /** @var list<array<string, string>> $missing */
        $missing = $data['missing_translations'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Lookups', (string) $totalLookups);
        $html .= $this->renderTableRow('Loaded Domains', (string) count($loadedDomains));
        $html .= $this->renderTableRow('Missing Translations', (string) $missingCount, $missingCount > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($loadedDomains !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Loaded Domains</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($loadedDomains as $domain) {
                $html .= '<span class="wpd-tag">' . $this->esc($domain) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($domainUsage !== []) {
            arsort($domainUsage);
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Domain Usage</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>Domain</th><th>Lookups</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($domainUsage as $domain => $count) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc((string) $domain) . '</code></td>';
                $html .= '<td>' . $this->esc((string) $count) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if ($missing !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Missing Translations</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th>Original</th>';
            $html .= '<th>Domain</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';
            foreach ($missing as $index => $entry) {
                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td><code>' . $this->esc($entry['original'] ?? '') . '</code></td>';
                $html .= '<td>' . $this->esc($entry['domain'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderDumpPanel(array $data): string
    {
        /** @var list<array<string, mixed>> $dumps */
        $dumps = $data['dumps'] ?? [];
        $totalCount = (int) ($data['total_count'] ?? 0);

        if ($dumps === []) {
            return '<div class="wpd-section"><p class="wpd-text-dim">No dump() calls recorded.</p></div>';
        }

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Dumps (' . $this->esc((string) $totalCount) . ')</h4>';

        foreach ($dumps as $index => $dump) {
            $file = $dump['file'] ?? 'unknown';
            $line = $dump['line'] ?? 0;
            $dumpData = $dump['data'] ?? '';

            $html .= '<div style="margin-bottom:12px">';
            $html .= '<div class="wpd-text-dim" style="font-size:11px;margin-bottom:4px">';
            $html .= '#' . $this->esc((string) ($index + 1)) . ' ' . $this->esc($file) . ':' . $this->esc((string) $line);
            $html .= '</div>';
            $html .= '<pre style="background:#181825;padding:8px 12px;border-radius:4px;overflow-x:auto;font-size:12px;color:#cdd6f4;margin:0">';
            $html .= $this->esc($dumpData);
            $html .= '</pre>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderPluginPanel(array $data): string
    {
        $totalPlugins = (int) ($data['total_plugins'] ?? 0);
        $totalHookTime = (float) ($data['total_hook_time'] ?? 0.0);
        $slowestPlugin = (string) ($data['slowest_plugin'] ?? '');
        /** @var array<string, array<string, mixed>> $plugins */
        $plugins = $data['plugins'] ?? [];
        /** @var list<string> $muPlugins */
        $muPlugins = $data['mu_plugins'] ?? [];
        /** @var list<string> $dropins */
        $dropins = $data['dropins'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Active Plugins', (string) $totalPlugins);
        $html .= $this->renderTableRow('Total Hook Time', $this->formatMs($totalHookTime));
        if ($slowestPlugin !== '') {
            $slowestName = (string) ($plugins[$slowestPlugin]['name'] ?? $slowestPlugin);
            $html .= $this->renderTableRow('Slowest Plugin', $this->esc($slowestName), 'wpd-text-yellow');
        }
        $html .= '</table>';
        $html .= '</div>';

        if ($plugins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Plugins</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Plugin</th>';
            $html .= '<th>Load</th>';
            $html .= '<th>Hook Time</th>';
            $html .= '<th>Hooks</th>';
            $html .= '<th>Listeners</th>';
            $html .= '<th>Queries</th>';
            $html .= '<th>Query Time</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($plugins as $slug => $info) {
                $name = (string) ($info['name'] ?? $slug);
                $loadTime = (float) ($info['load_time'] ?? 0.0);
                $hookTime = (float) ($info['hook_time'] ?? 0.0);
                $hookCount = (int) ($info['hook_count'] ?? 0);
                $listenerCount = (int) ($info['listener_count'] ?? 0);
                $queryCount = (int) ($info['query_count'] ?? 0);
                $queryTime = (float) ($info['query_time'] ?? 0.0);

                $html .= '<tr>';
                $html .= '<td><strong>' . $this->esc($name) . '</strong></td>';
                $html .= '<td>' . ($loadTime > 0 ? $this->formatMs($loadTime) : '-') . '</td>';
                $html .= '<td>' . $this->formatMs($hookTime) . '</td>';
                $html .= '<td>' . $this->esc((string) $hookCount) . '</td>';
                $html .= '<td>' . $this->esc((string) $listenerCount) . '</td>';
                $html .= '<td>' . ($queryCount > 0 ? $this->esc((string) $queryCount) : '-') . '</td>';
                $html .= '<td>' . ($queryTime > 0 ? $this->formatMs($queryTime) : '-') . '</td>';
                $html .= '</tr>';

                // Expandable hook detail rows
                /** @var list<array{hook: string, listeners: int, time: float}> $hooks */
                $hooks = $info['hooks'] ?? [];
                if ($hooks !== []) {
                    $html .= '<tr class="wpd-row-duplicate"><td colspan="7" style="padding:0">';
                    $html .= '<table class="wpd-table wpd-table-full" style="margin:0;background:rgba(0,0,0,0.15)">';
                    $html .= '<thead><tr><th style="padding-left:32px">Hook</th><th>Listeners</th><th>Time</th></tr></thead>';
                    $html .= '<tbody>';
                    foreach (array_slice($hooks, 0, 10) as $hookInfo) {
                        $html .= '<tr>';
                        $html .= '<td style="padding-left:32px"><code>' . $this->esc($hookInfo['hook']) . '</code></td>';
                        $html .= '<td>' . $this->esc((string) $hookInfo['listeners']) . '</td>';
                        $html .= '<td>' . $this->formatMs((float) $hookInfo['time']) . '</td>';
                        $html .= '</tr>';
                    }
                    if (count($hooks) > 10) {
                        $html .= '<tr><td colspan="3" class="wpd-text-dim" style="padding-left:32px">... and ' . $this->esc((string) (count($hooks) - 10)) . ' more hooks</td></tr>';
                    }
                    $html .= '</tbody></table>';
                    $html .= '</td></tr>';
                }
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if ($muPlugins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">MU Plugins</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($muPlugins as $muPlugin) {
                $html .= '<li><code>' . $this->esc($muPlugin) . '</code></li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if ($dropins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Drop-ins</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($dropins as $dropin) {
                $html .= '<li><code>' . $this->esc($dropin) . '</code></li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderThemePanel(array $data): string
    {
        $name = (string) ($data['name'] ?? '');
        $version = (string) ($data['version'] ?? '');
        $isChildTheme = (bool) ($data['is_child_theme'] ?? false);
        $isBlockTheme = (bool) ($data['is_block_theme'] ?? false);
        $setupTime = (float) ($data['setup_time'] ?? 0.0);
        $renderTime = (float) ($data['render_time'] ?? 0.0);
        $hookTime = (float) ($data['hook_time'] ?? 0.0);
        $hookCount = (int) ($data['hook_count'] ?? 0);
        $listenerCount = (int) ($data['listener_count'] ?? 0);
        $templateFile = (string) ($data['template_file'] ?? '');
        /** @var list<string> $templateParts */
        $templateParts = $data['template_parts'] ?? [];
        /** @var list<string> $bodyClasses */
        $bodyClasses = $data['body_classes'] ?? [];
        /** @var array<string, bool> $conditionalTags */
        $conditionalTags = $data['conditional_tags'] ?? [];
        /** @var list<string> $enqueuedStyles */
        $enqueuedStyles = $data['enqueued_styles'] ?? [];
        /** @var list<string> $enqueuedScripts */
        $enqueuedScripts = $data['enqueued_scripts'] ?? [];
        /** @var list<array{hook: string, listeners: int, time: float}> $hooks */
        $hooks = $data['hooks'] ?? [];

        // Theme Info
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Theme Info</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Name', $this->esc($name ?: '-'));
        if ($version !== '') {
            $html .= $this->renderTableRow('Version', $this->esc($version));
        }
        $html .= $this->renderTableRow('Child Theme', $this->formatValue($isChildTheme));
        if ($isChildTheme) {
            $html .= $this->renderTableRow('Child', $this->esc((string) ($data['child_theme'] ?? '')));
            $html .= $this->renderTableRow('Parent', $this->esc((string) ($data['parent_theme'] ?? '')));
        }
        $html .= $this->renderTableRow('Block Theme', $this->formatValue($isBlockTheme));
        $html .= '</table>';
        $html .= '</div>';

        // Timing cards
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Timing</h4>';
        $html .= '<div class="wpd-perf-cards">';
        $html .= $this->renderPerfCard('Setup Time', $this->formatMs($setupTime), '');
        $html .= $this->renderPerfCard('Render Time', $this->formatMs($renderTime), '');
        $html .= $this->renderPerfCard('Hook Time', $this->formatMs($hookTime), $this->esc((string) $hookCount) . ' hooks, ' . $this->esc((string) $listenerCount) . ' listeners');
        $html .= '</div>';
        $html .= '</div>';

        // Hook breakdown
        if ($hooks !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Hook Breakdown</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>Hook</th><th>Listeners</th><th>Time</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($hooks as $hookInfo) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($hookInfo['hook']) . '</code></td>';
                $html .= '<td>' . $this->esc((string) $hookInfo['listeners']) . '</td>';
                $html .= '<td>' . $this->formatMs($hookInfo['time']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Template
        if ($templateFile !== '' || $templateParts !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Template</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            if ($templateFile !== '') {
                $html .= $this->renderTableRow('Template File', '<code>' . $this->esc($templateFile) . '</code>');
            }
            if ($templateParts !== []) {
                $html .= $this->renderTableRow('Template Parts', '<code>' . $this->esc(implode(', ', $templateParts)) . '</code>');
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        // Conditional Tags
        if ($conditionalTags !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Conditional Tags</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($conditionalTags as $tag => $value) {
                $color = $value ? 'wpd-text-green' : 'wpd-text-dim';
                $html .= '<span class="wpd-tag ' . $color . '">' . $this->esc($tag) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Assets
        if ($enqueuedStyles !== [] || $enqueuedScripts !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Enqueued Assets</h4>';
            if ($enqueuedStyles !== []) {
                $html .= '<div style="margin-bottom:4px"><strong style="color:#a6adc8;font-size:11px">Styles</strong></div>';
                $html .= '<div class="wpd-tag-list" style="margin-bottom:8px">';
                foreach ($enqueuedStyles as $style) {
                    $html .= '<span class="wpd-tag">' . $this->esc($style) . '</span>';
                }
                $html .= '</div>';
            }
            if ($enqueuedScripts !== []) {
                $html .= '<div style="margin-bottom:4px"><strong style="color:#a6adc8;font-size:11px">Scripts</strong></div>';
                $html .= '<div class="wpd-tag-list">';
                foreach ($enqueuedScripts as $script) {
                    $html .= '<span class="wpd-tag">' . $this->esc($script) . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Body classes
        if ($bodyClasses !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Body Classes</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($bodyClasses as $class) {
                $html .= '<span class="wpd-tag">' . $this->esc($class) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderSchedulerPanel(array $data): string
    {
        $cronTotal = (int) ($data['cron_total'] ?? 0);
        $cronOverdue = (int) ($data['cron_overdue'] ?? 0);
        $asAvailable = (bool) ($data['action_scheduler_available'] ?? false);
        $asVersion = (string) ($data['action_scheduler_version'] ?? '');
        $asPending = (int) ($data['as_pending'] ?? 0);
        $asFailed = (int) ($data['as_failed'] ?? 0);
        $asComplete = (int) ($data['as_complete'] ?? 0);
        $cronDisabled = (bool) ($data['cron_disabled'] ?? false);
        $alternateCron = (bool) ($data['alternate_cron'] ?? false);
        /** @var list<array<string, mixed>> $cronEvents */
        $cronEvents = $data['cron_events'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('WP-Cron Events', (string) $cronTotal);
        $html .= $this->renderTableRow('Overdue', (string) $cronOverdue, $cronOverdue > 0 ? 'wpd-text-red' : '');
        $html .= $this->renderTableRow('Action Scheduler', $asAvailable ? 'Available' . ($asVersion !== '' ? ' (v' . $this->esc($asVersion) . ')' : '') : 'Not available');
        if ($asAvailable) {
            $html .= $this->renderTableRow('AS Pending', (string) $asPending, $asPending > 0 ? 'wpd-text-yellow' : '');
            $html .= $this->renderTableRow('AS Failed', (string) $asFailed, $asFailed > 0 ? 'wpd-text-red' : '');
            $html .= $this->renderTableRow('AS Complete', (string) $asComplete);
        }
        $html .= '</table>';
        $html .= '</div>';

        // Configuration
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Configuration</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('DISABLE_WP_CRON', $this->formatValue($cronDisabled));
        $html .= $this->renderTableRow('ALTERNATE_WP_CRON', $this->formatValue($alternateCron));
        $html .= '</table>';
        $html .= '</div>';

        // WP-Cron Events table
        if ($cronEvents !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">WP-Cron Events</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Hook</th>';
            $html .= '<th>Schedule</th>';
            $html .= '<th>Next Run</th>';
            $html .= '<th>Callbacks</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($cronEvents as $event) {
                $isOverdue = (bool) ($event['is_overdue'] ?? false);
                $rowClass = $isOverdue ? 'wpd-row-slow' : '';

                $html .= '<tr class="' . $rowClass . '">';
                $html .= '<td><code>' . $this->esc((string) ($event['hook'] ?? '')) . '</code></td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc((string) ($event['schedule'] ?? '')) . '</span></td>';
                $html .= '<td>' . $this->esc((string) ($event['next_run_relative'] ?? ''));
                if ($isOverdue) {
                    $html .= ' <span class="wpd-query-tag wpd-tag-slow">OVERDUE</span>';
                }
                $html .= '</td>';
                $html .= '<td>' . $this->esc((string) ($event['callbacks'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderGenericPanel(array $data): string
    {
        if ($data === []) {
            return '<div class="wpd-section"><p class="wpd-text-dim">No data collected.</p></div>';
        }

        return $this->renderKeyValueSection('Data', $data);
    }

    /**
     * @param array<string, mixed> $items
     */
    private function renderKeyValueSection(string $title, array $items): string
    {
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">' . $this->esc($title) . '</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';

        foreach ($items as $key => $value) {
            $html .= $this->renderTableRow($key, $this->formatValue($value));
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    private function renderTableRow(string $key, string $value, string $valueClass = ''): string
    {
        $classAttr = $valueClass !== '' ? ' class="wpd-kv-val ' . $this->esc($valueClass) . '"' : ' class="wpd-kv-val"';

        return '<tr><td class="wpd-kv-key">' . $this->esc($key) . '</td><td' . $classAttr . '>' . $value . '</td></tr>';
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value
                ? '<span class="wpd-text-green">true</span>'
                : '<span class="wpd-text-red">false</span>';
        }

        if ($value === null) {
            return '<span class="wpd-text-dim">null</span>';
        }

        if (is_array($value)) {
            return '<code>' . $this->esc(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]') . '</code>';
        }

        return $this->esc((string) $value);
    }

    private function formatMs(float $ms): string
    {
        if ($ms >= 1000) {
            return $this->esc(sprintf('%.2f s', $ms / 1000));
        }

        return $this->esc(sprintf('%.1f ms', $ms));
    }

    /**
     * Format an absolute timestamp as relative time from request start (+N ms).
     */
    private function formatRelativeTime(float $absoluteTimestamp): string
    {
        if ($absoluteTimestamp <= 0 || $this->requestTimeFloat <= 0) {
            return '';
        }

        $relativeMs = ($absoluteTimestamp - $this->requestTimeFloat) * 1000;

        return $this->esc('+' . number_format(max(0, $relativeMs), 0) . ' ms');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return $this->esc(sprintf('%.1f %s', round($value, 1), $units[$power]));
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function getCollectorData(Profile $profile, string $name): array
    {
        try {
            return $profile->getCollector($name)->getData();
        } catch (\Throwable) {
            return [];
        }
    }

    private function renderPerformanceBadge(Profile $profile): string
    {
        $totalTime = $profile->getTime();
        $value = $this->formatMs($totalTime);
        $icon = self::ICONS['performance'];

        $memoryData = $this->getCollectorData($profile, 'memory');
        $usagePercentage = (float) ($memoryData['usage_percentage'] ?? 0.0);

        $dbData = $this->getCollectorData($profile, 'database');
        $slowQueries = (int) ($dbData['slow_count'] ?? 0);

        $color = match (true) {
            $usagePercentage >= 90, $slowQueries > 0, $totalTime >= 1000 => self::BADGE_COLORS['red'],
            $totalTime >= 200 => self::BADGE_COLORS['yellow'],
            default => self::BADGE_COLORS['green'],
        };

        return <<<HTML
        <button class="wpd-badge" data-panel="performance" title="Performance">
            <span class="wpd-badge-icon">{$icon}</span>
            <span class="wpd-badge-value" style="color:{$color}">{$value}</span>
        </button>
        HTML;
    }

    private function renderPerformancePanel(Profile $profile): string
    {
        $icon = self::ICONS['performance'];
        $content = $this->renderPerformancePanelContent($profile);

        return <<<HTML
        <div class="wpd-panel" id="wpd-panel-performance" style="display:none">
            <div class="wpd-panel-header">
                <span class="wpd-panel-title">{$icon} Performance</span>
                <button class="wpd-panel-close" data-action="close-panel" title="Close">&times;</button>
            </div>
            <div class="wpd-panel-body">
                {$content}
            </div>
        </div>
        HTML;
    }

    private function renderPerformancePanelContent(Profile $profile): string
    {
        $timeData = $this->getCollectorData($profile, 'time');
        $memoryData = $this->getCollectorData($profile, 'memory');
        $dbData = $this->getCollectorData($profile, 'database');
        $cacheData = $this->getCollectorData($profile, 'cache');
        $httpData = $this->getCollectorData($profile, 'http_client');
        $eventData = $this->getCollectorData($profile, 'event');

        $totalTime = (float) ($timeData['total_time'] ?? $profile->getTime());
        $peakMemory = (int) ($memoryData['peak'] ?? 0);
        $memoryLimit = (int) ($memoryData['limit'] ?? 0);
        $usagePercentage = (float) ($memoryData['usage_percentage'] ?? 0.0);
        $dbCount = (int) ($dbData['total_count'] ?? 0);
        $dbTime = (float) ($dbData['total_time'] ?? 0.0);
        $cacheHitRate = (float) ($cacheData['hit_rate'] ?? 0.0);
        $httpCount = (int) ($httpData['total_count'] ?? 0);
        $httpTime = (float) ($httpData['total_time'] ?? 0.0);
        $hookFirings = (int) ($eventData['total_firings'] ?? 0);

        $html = '';

        // Section 1: Overview cards
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Overview</h4>';
        $html .= '<div class="wpd-perf-cards">';

        $html .= $this->renderPerfCard('Total Time', $this->formatMs($totalTime), '');

        $memorySub = '';
        if ($memoryLimit > 0) {
            $memorySub = $this->esc(sprintf('%.0f%%', $usagePercentage)) . ' of ' . $this->formatBytes($memoryLimit);
        }
        $html .= $this->renderPerfCard('Peak Memory', $peakMemory > 0 ? $this->formatBytes($peakMemory) : 'N/A', $memorySub);

        $dbSub = $dbCount > 0 ? $this->formatMs($dbTime) . ' total' : '';
        $html .= $this->renderPerfCard('Database', $dbCount > 0 ? $this->esc((string) $dbCount) . ' queries' : 'N/A', $dbSub);

        $html .= $this->renderPerfCard('Cache Hit Rate', $cacheData !== [] ? $this->esc(sprintf('%.1f%%', $cacheHitRate)) : 'N/A', '');

        $httpSub = $httpCount > 0 ? $this->formatMs($httpTime) . ' total' : '';
        $html .= $this->renderPerfCard('HTTP Client', $httpCount > 0 ? $this->esc((string) $httpCount) . ' requests' : 'N/A', $httpSub);

        $html .= $this->renderPerfCard('Hook Firings', $eventData !== [] ? $this->esc((string) $hookFirings) : 'N/A', '');

        $html .= '</div>';
        $html .= '</div>';

        // Section 2: Time Distribution
        /** @var array<string, array{name: string, category: string, duration: float, memory: int, start_time: float, end_time: float}> $events */
        $events = $timeData['events'] ?? [];
        $customEvents = array_filter($events, static fn(array $e): bool => $e['category'] !== 'wp_lifecycle');

        if ($totalTime > 0) {
            $dbTimeMs = $dbTime;
            $httpTimeMs = $httpTime;

            // Aggregate custom stopwatch event durations by category
            $categoryTimes = [];
            foreach ($customEvents as $event) {
                $cat = $event['category'];
                $categoryTimes[$cat] = ($categoryTimes[$cat] ?? 0.0) + (float) $event['duration'];
            }

            // Aggregate transient operation times as "cache" category
            /** @var list<array{name: string, operation: string, expiration: int, caller: string, time?: float}> $transientOps */
            $transientOps = $cacheData['transient_operations'] ?? [];
            $cacheTimeMs = 0.0;
            foreach ($transientOps as $op) {
                if (isset($op['time'])) {
                    $cacheTimeMs += 0.5; // approximate per-operation cost
                }
            }

            $customTotal = array_sum($categoryTimes) + $cacheTimeMs;
            $phpTime = max(0.0, $totalTime - $dbTimeMs - $httpTimeMs - $customTotal);

            // Build segments: fixed categories first, then dynamic
            $segments = [];
            $segments[] = ['label' => 'PHP', 'time' => $phpTime, 'color' => '#89b4fa'];
            $segments[] = ['label' => 'Database', 'time' => $dbTimeMs, 'color' => '#f9e2af'];
            $segments[] = ['label' => 'HTTP Client', 'time' => $httpTimeMs, 'color' => '#cba6f7'];

            if ($cacheTimeMs > 0) {
                $segments[] = ['label' => 'Cache', 'time' => $cacheTimeMs, 'color' => '#a6e3a1'];
            }

            $dynamicCategoryColors = [
                'template' => '#fab387',
                'controller' => '#94e2d5',
            ];
            foreach ($categoryTimes as $cat => $catTime) {
                $color = $dynamicCategoryColors[$cat] ?? '#74c7ec';
                $label = ucfirst($cat);
                $segments[] = ['label' => $label, 'time' => $catTime, 'color' => $color];
            }

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Time Distribution</h4>';

            $html .= '<div class="wpd-perf-dist-bar">';
            foreach ($segments as $seg) {
                $pct = ($seg['time'] / $totalTime) * 100;
                if ($pct > 0) {
                    $html .= '<div class="wpd-perf-dist-segment" style="width:' . $this->esc(sprintf('%.2f', $pct)) . '%;background:' . $this->esc($seg['color']) . '"></div>';
                }
            }
            $html .= '</div>';

            $html .= '<div class="wpd-perf-dist-legend">';
            foreach ($segments as $seg) {
                if ($seg['time'] > 0) {
                    $pct = ($seg['time'] / $totalTime) * 100;
                    $html .= '<span class="wpd-perf-legend-item"><span class="wpd-perf-legend-color" style="background:' . $this->esc($seg['color']) . '"></span> ' . $this->esc($seg['label']) . ' ' . $this->formatMs($seg['time']) . ' (' . $this->esc(sprintf('%.1f%%', $pct)) . ')</span>';
                }
            }
            $html .= '</div>';

            $html .= '</div>';
        }

        // Section 3: Unified Timeline (waterfall)
        /** @var array<string, float> $phases */
        $phases = $timeData['phases'] ?? [];
        $requestTimeFloat = (float) ($timeData['request_time_float'] ?? 0.0);

        $categoryColors = [
            'lifecycle' => '#89b4fa',
            'database' => '#f9e2af',
            'cache' => '#a6e3a1',
            'http' => '#cba6f7',
            'default' => '#74c7ec',
            'controller' => '#94e2d5',
            'template' => '#fab387',
            'security' => '#f38ba8',
        ];

        // Build unified timeline entries
        $timelineEntries = [];

        // 1. Lifecycle phases
        $previousTime = 0.0;
        foreach ($phases as $phaseName => $phaseTime) {
            $timelineEntries[] = [
                'name' => $phaseName,
                'start' => $previousTime,
                'duration' => $phaseTime - $previousTime,
                'category' => 'lifecycle',
                'title' => $phaseName,
            ];
            $previousTime = $phaseTime;
        }

        // 2. DB queries — single row with individual ticks per query
        /** @var list<array{sql: string, time: float, caller: string, start?: float}> $queries */
        $queries = $dbData['queries'] ?? [];
        $dbBars = [];
        $dbTotalMs = 0.0;
        foreach ($queries as $query) {
            if (!isset($query['start'])) {
                continue;
            }
            $startMs = ((float) $query['start'] - $requestTimeFloat) * 1000;
            $durationMs = (float) $query['time'];
            $dbTotalMs += $durationMs;
            $dbBars[] = ['start' => $startMs, 'duration' => $durationMs];
        }
        if ($dbBars !== []) {
            $dbLabel = sprintf('Database (%d queries)', count($dbBars));
            $timelineEntries[] = [
                'name' => $dbLabel,
                'start' => $dbBars[0]['start'],
                'duration' => 0.0,
                'category' => 'database',
                'title' => $dbLabel . ' — ' . $this->formatMs($dbTotalMs) . ' total',
                'value' => $dbTotalMs,
                'bars' => $dbBars,
            ];
        }

        // 3. Transient operations — single row with individual ticks
        /** @var list<array{name: string, operation: string, expiration: int, caller: string, time?: float}> $transientOps */
        $transientOps = $cacheData['transient_operations'] ?? [];
        $cacheBars = [];
        foreach ($transientOps as $op) {
            if (!isset($op['time'])) {
                continue;
            }
            $cacheBars[] = ['start' => (float) $op['time'], 'duration' => 0.5];
        }
        if ($cacheBars !== []) {
            $cacheLabel = sprintf('Cache (%d ops)', count($cacheBars));
            $timelineEntries[] = [
                'name' => $cacheLabel,
                'start' => $cacheBars[0]['start'],
                'duration' => 0.0,
                'category' => 'cache',
                'title' => $cacheLabel,
                'bars' => $cacheBars,
            ];
        }

        // 4. HTTP Client requests — single row with individual ticks
        /** @var list<array{url: string, method: string, status_code: int, duration: float, start?: float, response_size?: int, error?: string}> $httpRequests */
        $httpRequests = $httpData['requests'] ?? [];
        $httpBars = [];
        $httpTotalMs = 0.0;
        foreach ($httpRequests as $req) {
            if (!isset($req['start'])) {
                continue;
            }
            $startMs = ((float) $req['start'] - $requestTimeFloat) * 1000;
            $durationMs = (float) $req['duration'];
            $httpTotalMs += $durationMs;
            $httpBars[] = ['start' => $startMs, 'duration' => $durationMs];
        }
        if ($httpBars !== []) {
            $httpLabel = sprintf('HTTP Client (%d requests)', count($httpBars));
            $timelineEntries[] = [
                'name' => $httpLabel,
                'start' => $httpBars[0]['start'],
                'duration' => 0.0,
                'category' => 'http',
                'title' => $httpLabel . ' — ' . $this->formatMs($httpTotalMs) . ' total',
                'value' => $httpTotalMs,
                'bars' => $httpBars,
            ];
        }

        // 5. Custom stopwatch events (excluding wp_lifecycle)
        foreach ($customEvents as $event) {
            $timelineEntries[] = [
                'name' => $event['name'],
                'start' => (float) $event['start_time'],
                'duration' => (float) $event['duration'],
                'category' => $event['category'],
                'title' => $event['name'],
            ];
        }

        // 6. Plugin hook processing bars (including load time during plugins_loaded)
        // Plugins share hooks — within each hook, their processing is sequential.
        // Track per-hook cumulative offset so bars don't overlap.
        $pluginData = $this->getCollectorData($profile, 'plugin');
        /** @var array<string, array<string, mixed>> $pluginEntries */
        $pluginEntries = $pluginData['plugins'] ?? [];
        $pluginTimelineEntries = [];
        /** @var array<string, array{count: int, total_time: float, start: float}> $hookTimings */
        $hookTimings = $eventData['hook_timings'] ?? [];

        /** @var array<string, float> $hookOffsets per-hook cumulative offset in ms */
        $hookOffsets = [];

        // Pre-calculate plugin load offsets during plugins_loaded phase
        /** @var list<string> $loadOrder */
        $loadOrder = $pluginData['load_order'] ?? [];
        /** @var array<string, float> $pluginLoadStarts slug → ms from plugins_loaded start */
        $pluginLoadStarts = [];
        $loadOffset = 0.0;

        // plugins_loaded phase start from lifecycle events
        $pluginsLoadedStart = 0.0;
        foreach ($events as $event) {
            if ($event['name'] === 'plugins_loaded') {
                $pluginsLoadedStart = (float) $event['start_time'];
                break;
            }
        }

        foreach ($loadOrder as $pluginFile) {
            $pluginLoadStarts[$pluginFile] = $loadOffset;
            $loadTime = (float) ($pluginEntries[$pluginFile]['load_time'] ?? 0.0);
            $loadOffset += $loadTime;
        }

        foreach ($pluginEntries as $slug => $info) {
            /** @var list<array{hook: string, listeners: int, time: float, start?: float}> $pluginHooks */
            $pluginHooks = $info['hooks'] ?? [];

            $pluginBars = [];

            // Add plugin load time bar during plugins_loaded phase
            $loadTime = (float) ($info['load_time'] ?? 0.0);
            if ($loadTime > 0 && $pluginsLoadedStart > 0) {
                $loadStart = $pluginsLoadedStart + ($pluginLoadStarts[$slug] ?? 0.0);
                $pluginBars[] = ['start' => $loadStart, 'duration' => $loadTime];
            }

            foreach ($pluginHooks as $hookInfo) {
                $hookName = $hookInfo['hook'];
                $hookTiming = $hookTimings[$hookName] ?? null;
                if ($hookTiming !== null && $hookTiming['start'] > 0) {
                    $offset = $hookOffsets[$hookName] ?? 0.0;
                    $duration = max($hookInfo['time'], 0.5);
                    $pluginBars[] = ['start' => $hookTiming['start'] + $offset, 'duration' => $duration];
                    $hookOffsets[$hookName] = $offset + $duration;
                }
            }

            if ($pluginBars !== []) {
                $hookTime = (float) ($info['hook_time'] ?? 0.0);
                $pluginTimelineEntries[] = [
                    'name' => (string) ($info['name'] ?? $slug),
                    'start' => $pluginBars[0]['start'],
                    'duration' => 0.0,
                    'category' => 'plugin',
                    'title' => (string) ($info['name'] ?? $slug) . ' — ' . $this->formatMs($hookTime),
                    'value' => $hookTime,
                    'bars' => $pluginBars,
                ];
            }
        }

        // 7. Theme hook processing bars
        // Theme bars start after all plugin bars within each hook (sequential).
        $themeData = $this->getCollectorData($profile, 'theme');
        /** @var list<array{hook: string, listeners: int, time: float}> $themeHooks */
        $themeHooks = $themeData['hooks'] ?? [];
        $themeTimelineEntries = [];

        if ($themeHooks !== []) {
            $themeBars = [];
            foreach ($themeHooks as $hookInfo) {
                $hookName = $hookInfo['hook'];
                $hookTiming = $hookTimings[$hookName] ?? null;
                if ($hookTiming !== null && $hookTiming['start'] > 0) {
                    $offset = $hookOffsets[$hookName] ?? 0.0;
                    $duration = max($hookInfo['time'], 0.5);
                    $themeBars[] = ['start' => $hookTiming['start'] + $offset, 'duration' => $duration];
                    $hookOffsets[$hookName] = $offset + $duration;
                }
            }

            if ($themeBars !== []) {
                $themeHookTime = (float) ($themeData['hook_time'] ?? 0.0);
                $themeName = (string) ($themeData['name'] ?? 'Theme');
                $themeTimelineEntries[] = [
                    'name' => $themeName,
                    'start' => $themeBars[0]['start'],
                    'duration' => 0.0,
                    'category' => 'theme_hooks',
                    'title' => $themeName . ' — ' . $this->formatMs($themeHookTime),
                    'value' => $themeHookTime,
                    'bars' => $themeBars,
                ];
            }
        }

        // Sort by start time
        usort($timelineEntries, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

        // Add category colors for new types
        $categoryColors['plugin'] = '#f5c2e7';
        $categoryColors['theme_hooks'] = '#fab387';

        if ($timelineEntries !== [] || $pluginTimelineEntries !== [] || $themeTimelineEntries !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Timeline</h4>';
            $html .= '<div class="wpd-perf-waterfall">';

            // Main timeline entries
            foreach ($timelineEntries as $entry) {
                $color = $categoryColors[$entry['category']] ?? $categoryColors['default'];
                $html .= $this->renderTimelineRow($entry, $color, $totalTime);
            }

            // Plugin section divider + entries
            if ($pluginTimelineEntries !== []) {
                $html .= '<div class="wpd-perf-wf-divider"><span>Plugins</span></div>';
                usort($pluginTimelineEntries, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
                foreach ($pluginTimelineEntries as $entry) {
                    $html .= $this->renderTimelineRow($entry, $categoryColors['plugin'], $totalTime);
                }
            }

            // Theme section divider + entries
            if ($themeTimelineEntries !== []) {
                $html .= '<div class="wpd-perf-wf-divider"><span>Theme</span></div>';
                foreach ($themeTimelineEntries as $entry) {
                    $html .= $this->renderTimelineRow($entry, $categoryColors['theme_hooks'], $totalTime);
                }
            }

            $html .= '</div>';

            // Category legend
            $allEntries = array_merge($timelineEntries, $pluginTimelineEntries, $themeTimelineEntries);
            $usedCategories = array_unique(array_column($allEntries, 'category'));
            $categoryLabels = [
                'lifecycle' => 'Lifecycle',
                'database' => 'Database',
                'cache' => 'Cache',
                'http' => 'HTTP Client',
                'default' => 'Default',
                'controller' => 'Controller',
                'template' => 'Template',
                'security' => 'Security',
                'plugin' => 'Plugin',
                'theme_hooks' => 'Theme',
            ];
            $html .= '<div class="wpd-perf-dist-legend" style="margin-top:8px">';
            foreach ($usedCategories as $cat) {
                $color = $categoryColors[$cat] ?? $categoryColors['default'];
                $label = $categoryLabels[$cat] ?? ucfirst($cat);
                $html .= '<span class="wpd-perf-legend-item"><span class="wpd-perf-legend-color" style="background:' . $this->esc($color) . '"></span> ' . $this->esc($label) . '</span>';
            }
            $html .= '</div>';

            $html .= '</div>';
        }

        return $html;
    }

    private function renderPerfCard(string $label, string $value, string $sub): string
    {
        $html = '<div class="wpd-perf-card">';
        $html .= '<div class="wpd-perf-card-value">' . $value . '</div>';
        $html .= '<div class="wpd-perf-card-label">' . $this->esc($label) . '</div>';
        if ($sub !== '') {
            $html .= '<div class="wpd-perf-card-sub">' . $sub . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function renderTimelineRow(array $entry, string $color, float $totalTime): string
    {
        $html = '<div class="wpd-perf-wf-row">';
        $html .= '<div class="wpd-perf-wf-label" title="' . $this->esc((string) ($entry['title'] ?? '')) . '">' . $this->esc((string) ($entry['name'] ?? '')) . '</div>';
        $html .= '<div class="wpd-perf-wf-track">';

        /** @var non-empty-list<array{start: float, duration: float}>|null $bars */
        $bars = $entry['bars'] ?? null;
        if ($bars !== null) {
            foreach ($bars as $bar) {
                $left = $totalTime > 0 ? ($bar['start'] / $totalTime) * 100 : 0;
                $width = $totalTime > 0 ? ($bar['duration'] / $totalTime) * 100 : 0;
                $width = max($width, 0.3);
                $html .= '<div class="wpd-perf-wf-bar" style="left:' . $this->esc(sprintf('%.2f', $left)) . '%;width:' . $this->esc(sprintf('%.2f', $width)) . '%;background:' . $this->esc($color) . '"></div>';
            }
        } else {
            $left = $totalTime > 0 ? ((float) ($entry['start'] ?? 0.0) / $totalTime) * 100 : 0;
            $width = $totalTime > 0 ? ((float) ($entry['duration'] ?? 0.0) / $totalTime) * 100 : 0;
            $width = max($width, 0.3);
            $html .= '<div class="wpd-perf-wf-bar" style="left:' . $this->esc(sprintf('%.2f', $left)) . '%;width:' . $this->esc(sprintf('%.2f', $width)) . '%;background:' . $this->esc($color) . '"></div>';
        }

        $html .= '</div>';
        $displayValue = (float) ($entry['value'] ?? $entry['duration'] ?? 0.0);
        $html .= '<div class="wpd-perf-wf-value">' . $this->formatMs($displayValue) . '</div>';
        $html .= '</div>';

        return $html;
    }

    private function renderCss(): string
    {
        return <<<'CSS'
        #wppack-debug *, #wppack-debug *::before, #wppack-debug *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        #wppack-debug {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #cdd6f4;
            direction: ltr;
            text-align: left;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 99999;
        }

        /* ---- Summary bar ---- */
        #wppack-debug .wpd-bar {
            display: flex;
            align-items: center;
            background: #1e1e2e;
            border-top: 1px solid #313244;
            height: 36px;
            width: 100%;
        }

        /* ---- Logo (fixed left, does not scroll) ---- */
        #wppack-debug .wpd-bar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #89b4fa, #cba6f7);
            flex-shrink: 0;
            cursor: default;
        }
        #wppack-debug .wpd-logo-text {
            font-size: 10px;
            font-weight: 800;
            color: #1e1e2e;
            letter-spacing: -0.5px;
        }

        /* ---- Badges container ---- */
        #wppack-debug .wpd-bar-badges {
            display: flex;
            align-items: center;
            height: 100%;
            flex: 1 1 auto;
            min-width: 0;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
        }
        #wppack-debug .wpd-bar-badges::-webkit-scrollbar {
            display: none;
        }

        /* ---- Badges ---- */
        #wppack-debug .wpd-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 0 10px;
            background: transparent;
            border: none;
            border-right: 1px solid #313244;
            color: #cdd6f4;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            white-space: nowrap;
            flex-shrink: 0;
            height: 100%;
            transition: background 0.15s ease;
        }
        #wppack-debug .wpd-badge:last-child {
            border-right: none;
        }
        #wppack-debug .wpd-badge:hover {
            background: #313244;
        }
        #wppack-debug .wpd-badge.wpd-active {
            background: #313244;
            box-shadow: inset 0 -2px 0 #89b4fa;
        }
        #wppack-debug .wpd-badge-icon {
            font-size: 14px;
            line-height: 1;
        }
        #wppack-debug .wpd-badge-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        /* ---- Bar meta ---- */
        #wppack-debug .wpd-bar-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            padding: 0 10px;
            height: 100%;
            border-left: 1px solid #313244;
        }
        #wppack-debug .wpd-meta-item {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
        }
        #wppack-debug .wpd-meta-sep {
            color: #45475a;
            font-size: 11px;
        }

        /* ---- Close button ---- */
        #wppack-debug .wpd-close-btn {
            background: transparent;
            border: none;
            border-left: 1px solid #313244;
            color: #6c7086;
            cursor: pointer;
            font-size: 16px;
            padding: 0 10px;
            height: 100%;
            flex-shrink: 0;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-close-btn:hover {
            color: #f38ba8;
            background: #313244;
        }

        /* ---- Minimized state button ---- */
        #wppack-debug .wpd-mini {
            display: none;
            position: fixed;
            bottom: 6px;
            right: 6px;
            z-index: 99999;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #89b4fa, #cba6f7);
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        #wppack-debug .wpd-mini:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }
        #wppack-debug .wpd-mini-logo {
            font-size: 10px;
            font-weight: 800;
            color: #1e1e2e;
            letter-spacing: -0.5px;
        }

        /* ---- Minimized state ---- */
        #wppack-debug.wpd-minimized .wpd-bar {
            display: none;
        }
        #wppack-debug.wpd-minimized .wpd-panel {
            display: none !important;
        }
        #wppack-debug.wpd-minimized .wpd-mini {
            display: flex;
        }

        /* ---- Panels ---- */
        #wppack-debug .wpd-panel {
            position: absolute;
            bottom: 36px;
            left: 0;
            right: 0;
            background: #1e1e2e;
            border-top: 1px solid #313244;
            max-height: 55vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #45475a transparent;
        }
        #wppack-debug .wpd-panel::-webkit-scrollbar {
            width: 6px;
        }
        #wppack-debug .wpd-panel::-webkit-scrollbar-track {
            background: transparent;
        }
        #wppack-debug .wpd-panel::-webkit-scrollbar-thumb {
            background: #45475a;
            border-radius: 3px;
        }
        #wppack-debug .wpd-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #181825;
            border-bottom: 1px solid #313244;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        #wppack-debug .wpd-panel-title {
            font-size: 14px;
            font-weight: 600;
            color: #cdd6f4;
        }
        #wppack-debug .wpd-panel-close {
            background: transparent;
            border: none;
            color: #6c7086;
            font-size: 20px;
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 4px;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-panel-close:hover {
            color: #f38ba8;
            background: #313244;
        }
        #wppack-debug .wpd-panel-body {
            padding: 12px 16px;
        }

        /* ---- Sections ---- */
        #wppack-debug .wpd-section {
            margin-bottom: 16px;
        }
        #wppack-debug .wpd-section:last-child {
            margin-bottom: 0;
        }
        #wppack-debug .wpd-section-title {
            font-size: 12px;
            font-weight: 600;
            color: #89b4fa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 6px;
            margin-bottom: 8px;
            border-bottom: 1px solid #313244;
        }

        /* ---- Tables ---- */
        #wppack-debug .wpd-table {
            width: 100%;
            border-collapse: collapse;
        }
        #wppack-debug .wpd-table th,
        #wppack-debug .wpd-table td {
            padding: 5px 10px;
            text-align: left;
            border-bottom: 1px solid #262637;
            vertical-align: top;
        }
        #wppack-debug .wpd-table thead th {
            font-size: 11px;
            font-weight: 600;
            color: #a6adc8;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #181825;
            position: sticky;
            top: 47px;
            z-index: 1;
        }
        #wppack-debug .wpd-table tbody tr:hover {
            background: #262637;
        }

        /* Key-value table */
        #wppack-debug .wpd-table-kv .wpd-kv-key {
            width: 200px;
            font-weight: 500;
            color: #a6adc8;
            white-space: nowrap;
        }
        #wppack-debug .wpd-table-kv .wpd-kv-val {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
        }

        /* Full-width table columns */
        #wppack-debug .wpd-col-reltime {
            width: 70px;
            white-space: nowrap;
            font-size: 12px;
        }
        #wppack-debug .wpd-col-num {
            width: 40px;
            text-align: center;
            color: #6c7086;
            font-size: 11px;
        }
        #wppack-debug .wpd-col-sql {
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #wppack-debug .wpd-col-sql code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #cdd6f4;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #wppack-debug .wpd-col-time {
            width: 90px;
            white-space: nowrap;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
        }
        #wppack-debug .wpd-col-caller {
            width: 260px;
        }
        #wppack-debug .wpd-caller {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #a6adc8;
            word-break: break-all;
        }

        /* Query row highlighting */
        #wppack-debug .wpd-row-slow {
            background: rgba(243, 139, 168, 0.08);
        }
        #wppack-debug .wpd-row-slow:hover {
            background: rgba(243, 139, 168, 0.14);
        }
        #wppack-debug .wpd-row-duplicate {
            background: rgba(249, 226, 175, 0.06);
        }
        #wppack-debug .wpd-row-duplicate:hover {
            background: rgba(249, 226, 175, 0.12);
        }

        /* Query tags */
        #wppack-debug .wpd-query-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 6px;
            vertical-align: middle;
        }
        #wppack-debug .wpd-tag-slow {
            background: rgba(243, 139, 168, 0.2);
            color: #f38ba8;
        }
        #wppack-debug .wpd-tag-dup {
            background: rgba(249, 226, 175, 0.2);
            color: #f9e2af;
        }

        /* ---- Timeline ---- */
        #wppack-debug .wpd-timeline {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        #wppack-debug .wpd-timeline-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #wppack-debug .wpd-timeline-label {
            width: 150px;
            font-size: 12px;
            color: #a6adc8;
            text-align: right;
            flex-shrink: 0;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
        }
        #wppack-debug .wpd-timeline-bar-wrap {
            flex: 1;
            height: 14px;
            background: #262637;
            border-radius: 3px;
            overflow: hidden;
        }
        #wppack-debug .wpd-timeline-bar {
            height: 100%;
            background: #89b4fa;
            border-radius: 3px;
            min-width: 2px;
            transition: width 0.3s ease;
        }
        #wppack-debug .wpd-timeline-value {
            width: 160px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ---- Memory bar ---- */
        #wppack-debug .wpd-memory-bar-wrap {
            height: 8px;
            background: #262637;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        #wppack-debug .wpd-memory-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* ---- Tags ---- */
        #wppack-debug .wpd-tag {
            display: inline-block;
            font-size: 11px;
            padding: 1px 7px;
            background: #313244;
            color: #a6adc8;
            border-radius: 3px;
            margin: 2px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
        }
        #wppack-debug .wpd-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }

        /* ---- Lists ---- */
        #wppack-debug .wpd-list {
            list-style: none;
            padding: 0;
        }
        #wppack-debug .wpd-list li {
            padding: 4px 0;
            border-bottom: 1px solid #262637;
            font-size: 12px;
        }
        #wppack-debug .wpd-list li:last-child {
            border-bottom: none;
        }
        #wppack-debug .wpd-list code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
        }

        /* ---- Suggestions ---- */
        #wppack-debug .wpd-suggestions {
            list-style: none;
            padding: 0;
        }
        #wppack-debug .wpd-suggestion-item {
            padding: 6px 10px;
            background: rgba(249, 226, 175, 0.08);
            border-left: 3px solid #f9e2af;
            border-radius: 0 4px 4px 0;
            margin-bottom: 4px;
            font-size: 12px;
            color: #f9e2af;
        }

        /* ---- Utility text colors ---- */
        #wppack-debug .wpd-text-green { color: #a6e3a1; }
        #wppack-debug .wpd-text-yellow { color: #f9e2af; }
        #wppack-debug .wpd-text-red { color: #f38ba8; }
        #wppack-debug .wpd-text-orange { color: #fab387; }
        #wppack-debug .wpd-text-dim { color: #6c7086; font-style: italic; }

        /* ---- Code blocks ---- */
        #wppack-debug code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
        }

        /* ---- Performance cards ---- */
        #wppack-debug .wpd-perf-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        #wppack-debug .wpd-perf-card {
            background: #181825;
            border: 1px solid #313244;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }
        #wppack-debug .wpd-perf-card-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 18px;
            font-weight: 700;
            color: #cdd6f4;
        }
        #wppack-debug .wpd-perf-card-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #a6adc8;
            letter-spacing: 0.3px;
            margin-top: 4px;
        }
        #wppack-debug .wpd-perf-card-sub {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
            margin-top: 2px;
        }

        /* ---- Time Distribution ---- */
        #wppack-debug .wpd-perf-dist-bar {
            display: flex;
            height: 20px;
            background: #262637;
            border-radius: 4px;
            overflow: hidden;
        }
        #wppack-debug .wpd-perf-dist-segment {
            min-width: 2px;
        }
        #wppack-debug .wpd-perf-dist-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }
        #wppack-debug .wpd-perf-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #a6adc8;
        }
        #wppack-debug .wpd-perf-legend-color {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        /* ---- Waterfall ---- */
        #wppack-debug .wpd-perf-waterfall {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        #wppack-debug .wpd-perf-wf-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #wppack-debug .wpd-perf-wf-label {
            width: 180px;
            flex-shrink: 0;
            text-align: right;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #a6adc8;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #wppack-debug .wpd-perf-wf-track {
            flex: 1;
            height: 16px;
            position: relative;
            background: #262637;
            border-radius: 3px;
        }
        #wppack-debug .wpd-perf-wf-bar {
            position: absolute;
            top: 0;
            height: 100%;
            background: #89b4fa;
            border-radius: 3px;
            min-width: 2px;
        }
        #wppack-debug .wpd-perf-wf-value {
            width: 80px;
            flex-shrink: 0;
            text-align: right;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
            white-space: nowrap;
        }

        /* ---- Timeline dividers ---- */
        #wppack-debug .wpd-perf-wf-divider {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 6px 0 3px;
            color: #6c7086;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        #wppack-debug .wpd-perf-wf-divider::before,
        #wppack-debug .wpd-perf-wf-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #313244;
        }

        /* ---- Log filter tabs ---- */
        #wppack-debug .wpd-log-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #313244;
        }
        #wppack-debug .wpd-log-tab {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c7086;
            padding: 6px 14px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }
        #wppack-debug .wpd-log-tab:hover {
            color: #cdd6f4;
        }
        #wppack-debug .wpd-log-tab.wpd-active {
            color: #89b4fa;
            border-bottom-color: #89b4fa;
        }
        #wppack-debug .wpd-log-context pre {
            background: #181825;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 11px;
            color: #a6adc8;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #wppack-debug .wpd-log-toggle {
            cursor: pointer;
        }
        #wppack-debug .wpd-log-toggle:hover {
            background: #313244;
        }
        CSS;
    }

    private function renderJs(): string
    {
        return <<<'JS'
        (function() {
            var root = document.getElementById('wppack-debug');
            if (!root) return;

            var activePanel = null;

            function closeAllPanels() {
                var panels = root.querySelectorAll('.wpd-panel');
                for (var i = 0; i < panels.length; i++) {
                    panels[i].style.display = 'none';
                }
                var badges = root.querySelectorAll('.wpd-badge');
                for (var i = 0; i < badges.length; i++) {
                    badges[i].classList.remove('wpd-active');
                }
                activePanel = null;
            }

            function openPanel(name) {
                closeAllPanels();
                var panel = root.querySelector('#wpd-panel-' + name);
                if (panel) {
                    panel.style.display = 'block';
                    activePanel = name;
                    var badge = root.querySelector('.wpd-badge[data-panel="' + name + '"]');
                    if (badge) badge.classList.add('wpd-active');
                }
            }

            root.addEventListener('click', function(e) {
                // Mini button — restore toolbar
                var miniBtn = e.target.closest('.wpd-mini');
                if (miniBtn) {
                    root.classList.remove('wpd-minimized');
                    return;
                }

                // Badge click — toggle panel
                var badge = e.target.closest('.wpd-badge');
                if (badge) {
                    var panel = badge.getAttribute('data-panel');
                    if (activePanel === panel) {
                        closeAllPanels();
                    } else {
                        openPanel(panel);
                    }
                    return;
                }

                // Close button in panel header
                var closeBtn = e.target.closest('[data-action="close-panel"]');
                if (closeBtn) {
                    closeAllPanels();
                    return;
                }

                // Close/minimize toolbar
                var minimizeBtn = e.target.closest('[data-action="minimize"]');
                if (minimizeBtn) {
                    closeAllPanels();
                    root.classList.add('wpd-minimized');
                }
            });

            // Log filter tabs
            root.addEventListener('click', function(e) {
                var tab = e.target.closest('.wpd-log-tab');
                if (tab) {
                    var tabs = tab.closest('.wpd-log-tabs');
                    tabs.querySelectorAll('.wpd-log-tab').forEach(function(t) { t.classList.remove('wpd-active'); });
                    tab.classList.add('wpd-active');
                    var filter = tab.getAttribute('data-log-filter');
                    var section = tabs.closest('.wpd-section');
                    section.querySelectorAll('tr[data-log-level]').forEach(function(row) {
                        var level = row.getAttribute('data-log-level');
                        var show = false;
                        if (filter === 'all') { show = true; }
                        else if (filter === 'error') { show = (['emergency','alert','critical','error'].indexOf(level) !== -1); }
                        else if (filter === 'deprecation') { show = level === 'deprecation'; }
                        else if (filter === 'warning') { show = (['warning','notice'].indexOf(level) !== -1); }
                        else if (filter === 'info') { show = level === 'info'; }
                        else if (filter === 'debug') { show = level === 'debug'; }
                        row.style.display = show ? '' : 'none';
                    });
                    return;
                }
                // Context toggle
                var toggle = e.target.closest('.wpd-log-toggle');
                if (toggle) {
                    var ctx = toggle.nextElementSibling;
                    if (ctx && ctx.classList.contains('wpd-log-context')) {
                        ctx.style.display = ctx.style.display === 'none' ? '' : 'none';
                    }
                }
            });

            // Escape key closes panel
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && activePanel !== null) {
                    closeAllPanels();
                }
            });
        })();
        JS;
    }
}
