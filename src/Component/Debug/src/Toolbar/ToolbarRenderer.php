<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;

final class ToolbarRenderer
{
    private const ICONS = [
        'request' => "\xF0\x9F\x8C\x90",
        'database' => "\xF0\x9F\x92\xBE",
        'memory' => "\xF0\x9F\x93\x8A",
        'time' => "\xE2\x8F\xB1\xEF\xB8\x8F",
        'cache' => "\xF0\x9F\x93\xA6",
        'wordpress' => "\xE2\x9A\x99\xEF\xB8\x8F",
    ];

    private const DEFAULT_ICON = "\xF0\x9F\x93\x8B";

    private const BADGE_COLORS = [
        'green' => '#a6e3a1',
        'yellow' => '#f9e2af',
        'red' => '#f38ba8',
        'default' => '#cdd6f4',
    ];

    public function render(Profile $profile): string
    {
        $collectors = $profile->getCollectors();

        $badges = '';
        $panels = '';

        foreach ($collectors as $name => $collector) {
            $badges .= $this->renderBadge($collector);
            $panels .= $this->renderPanel($collector);
        }

        $requestInfo = $this->esc($profile->getMethod()) . ' ' . $this->esc((string) $profile->getStatusCode());
        $totalTime = $this->formatMs($profile->getTime());

        $css = $this->renderCss();
        $js = $this->renderJs();

        return <<<HTML
        <div id="wppack-debug">
        <style>{$css}</style>
        {$panels}
        <div class="wpd-bar">
            <div class="wpd-bar-inner">
                <div class="wpd-logo" title="WpPack Debug">
                    <span class="wpd-logo-text">WP</span>
                </div>
                {$badges}
                <div class="wpd-bar-spacer"></div>
                <div class="wpd-bar-meta">
                    <span class="wpd-meta-item">{$requestInfo}</span>
                    <span class="wpd-meta-sep">|</span>
                    <span class="wpd-meta-item">{$totalTime}</span>
                </div>
                <button class="wpd-toggle-btn" data-action="minimize" title="Minimize toolbar">&#x25BC;</button>
            </div>
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
            <span class="wpd-badge-label">{$label}</span>
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
            $html .= '<th class="wpd-col-sql">SQL</th>';
            $html .= '<th class="wpd-col-time">Time</th>';
            $html .= '<th class="wpd-col-caller">Caller</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($queries as $index => $query) {
                $sql = $query['sql'];
                $timeMs = $query['time'] * 1000;
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

                $html .= '<tr class="' . $rowClass . '">';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
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

        if ($phases !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">WordPress Lifecycle</h4>';
            $html .= '<div class="wpd-timeline">';

            $previousTime = 0.0;
            foreach ($phases as $phaseName => $phaseTime) {
                $delta = $phaseTime - $previousTime;
                $barWidth = $totalTime > 0 ? min(($phaseTime / $totalTime) * 100, 100) : 0;

                $html .= '<div class="wpd-timeline-row">';
                $html .= '<div class="wpd-timeline-label">' . $this->esc($phaseName) . '</div>';
                $html .= '<div class="wpd-timeline-bar-wrap">';
                $html .= '<div class="wpd-timeline-bar" style="width:' . $this->esc(sprintf('%.1f', $barWidth)) . '%"></div>';
                $html .= '</div>';
                $html .= '<div class="wpd-timeline-value">' . $this->formatMs($phaseTime) . ' (+' . $this->formatMs($delta) . ')</div>';
                $html .= '</div>';

                $previousTime = $phaseTime;
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        if ($events !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Stopwatch Events</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Event</th>';
            $html .= '<th>Category</th>';
            $html .= '<th>Duration</th>';
            $html .= '<th>Memory</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($events as $event) {
                $html .= '<tr>';
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

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Object Cache</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
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

        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Transients</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Transient Sets', (string) $transientSets);
        $html .= $this->renderTableRow('Transient Deletes', (string) $transientDeletes);
        $html .= '</table>';
        $html .= '</div>';

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
            background: #1e1e2e;
            border-top: 1px solid #313244;
            height: 36px;
            width: 100%;
        }
        #wppack-debug .wpd-bar-inner {
            display: flex;
            align-items: center;
            height: 100%;
            padding: 0 8px;
            gap: 2px;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
        }
        #wppack-debug .wpd-bar-inner::-webkit-scrollbar {
            display: none;
        }

        /* ---- Logo ---- */
        #wppack-debug .wpd-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 24px;
            background: linear-gradient(135deg, #89b4fa, #cba6f7);
            border-radius: 4px;
            flex-shrink: 0;
            margin-right: 6px;
        }
        #wppack-debug .wpd-logo-text {
            font-size: 10px;
            font-weight: 800;
            color: #1e1e2e;
            letter-spacing: -0.5px;
        }

        /* ---- Badges ---- */
        #wppack-debug .wpd-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            background: transparent;
            border: none;
            border-radius: 4px;
            color: #cdd6f4;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            white-space: nowrap;
            flex-shrink: 0;
            height: 28px;
            transition: background 0.15s ease;
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
        #wppack-debug .wpd-badge-label {
            font-size: 11px;
            color: #a6adc8;
        }
        #wppack-debug .wpd-badge-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        /* ---- Bar spacer & meta ---- */
        #wppack-debug .wpd-bar-spacer {
            flex: 1 1 auto;
            min-width: 8px;
        }
        #wppack-debug .wpd-bar-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            margin-right: 4px;
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

        /* ---- Toggle button ---- */
        #wppack-debug .wpd-toggle-btn {
            background: transparent;
            border: none;
            color: #6c7086;
            cursor: pointer;
            font-size: 12px;
            padding: 4px 6px;
            border-radius: 3px;
            flex-shrink: 0;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-toggle-btn:hover {
            color: #cdd6f4;
            background: #313244;
        }

        /* ---- Minimized state ---- */
        #wppack-debug.wpd-minimized .wpd-bar-inner > *:not(.wpd-logo):not(.wpd-toggle-btn) {
            display: none;
        }
        #wppack-debug.wpd-minimized .wpd-bar {
            height: 28px;
        }
        #wppack-debug.wpd-minimized .wpd-logo {
            margin-right: 0;
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
            background: linear-gradient(90deg, #89b4fa, #cba6f7);
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
        #wppack-debug .wpd-text-dim { color: #6c7086; font-style: italic; }

        /* ---- Code blocks ---- */
        #wppack-debug code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
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

            // Badge click handlers
            root.addEventListener('click', function(e) {
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

                // Close button
                var closeBtn = e.target.closest('[data-action="close-panel"]');
                if (closeBtn) {
                    closeAllPanels();
                    return;
                }

                // Toggle (minimize/restore)
                var toggleBtn = e.target.closest('[data-action="minimize"]');
                if (toggleBtn) {
                    closeAllPanels();
                    root.classList.toggle('wpd-minimized');
                    var isMinimized = root.classList.contains('wpd-minimized');
                    toggleBtn.innerHTML = isMinimized ? '&#x25B2;' : '&#x25BC;';
                    toggleBtn.title = isMinimized ? 'Restore toolbar' : 'Minimize toolbar';
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
