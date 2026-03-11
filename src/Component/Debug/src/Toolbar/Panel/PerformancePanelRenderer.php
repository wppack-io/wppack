<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Profiler\Profile;

final class PerformancePanelRenderer extends AbstractPanelRenderer
{
    /**
     * @return array<string, mixed>
     */
    public function getCollectorData(Profile $profile, string $name): array
    {
        try {
            return $profile->getCollector($name)->getData();
        } catch (\Throwable) {
            return [];
        }
    }

    public function renderBadge(Profile $profile): string
    {
        $totalTime = $profile->getTime();
        $value = $this->formatMs($totalTime);
        $icon = "\xF0\x9F\x9A\x80";

        $memoryData = $this->getCollectorData($profile, 'memory');
        $usagePercentage = (float) ($memoryData['usage_percentage'] ?? 0.0);

        $dbData = $this->getCollectorData($profile, 'database');
        $slowQueries = (int) ($dbData['slow_count'] ?? 0);

        $badgeColors = self::getBadgeColors();
        $color = match (true) {
            $usagePercentage >= 90, $slowQueries > 0, $totalTime >= 1000 => $badgeColors['red'],
            $totalTime >= 200 => $badgeColors['yellow'],
            default => $badgeColors['green'],
        };

        return <<<HTML
        <button class="wpd-badge" data-panel="performance" title="Performance">
            <span class="wpd-badge-icon">{$icon}</span>
            <span class="wpd-badge-value" style="color:{$color}">{$value}</span>
        </button>
        HTML;
    }

    public function renderPanel(Profile $profile): string
    {
        $icon = "\xF0\x9F\x9A\x80";
        $content = $this->renderPanelContent($profile);

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

    private function renderPanelContent(Profile $profile): string
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
        $customEvents = array_filter($events, static fn(array $e): bool => $e['category'] !== 'wordpress');

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
            'wordpress' => '#89b4fa',
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
            $duration = $phaseTime - $previousTime;
            $timelineEntries[] = [
                'name' => $phaseName,
                'start' => $previousTime,
                'duration' => $duration,
                'category' => 'wordpress',
                'title' => $phaseName . "\n" . $this->formatMs($previousTime) . ' → ' . $this->formatMs($phaseTime) . ' (' . $this->formatMs($duration) . ')',
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
            $sql = mb_strimwidth($query['sql'], 0, 80, '…');
            $dbBars[] = [
                'start' => $startMs,
                'duration' => $durationMs,
                'title' => $sql . "\n" . $this->formatMs($durationMs) . ' — ' . $query['caller'],
            ];
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
            $cacheBars[] = [
                'start' => (float) $op['time'],
                'duration' => 0.5,
                'title' => strtoupper($op['operation']) . ' ' . $op['name'] . "\n" . $op['caller'],
            ];
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
            $httpBars[] = [
                'start' => $startMs,
                'duration' => $durationMs,
                'title' => $req['method'] . ' ' . $req['url'] . "\n" . $this->formatMs($durationMs) . ' — ' . $req['status_code'],
            ];
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
            $eventStart = (float) $event['start_time'];
            $eventDuration = (float) $event['duration'];
            $timelineEntries[] = [
                'name' => $event['name'],
                'start' => $eventStart,
                'duration' => $eventDuration,
                'category' => $event['category'],
                'title' => $event['name'] . "\n+" . $this->formatMs($eventStart) . ' — ' . $this->formatMs($eventDuration),
            ];
        }

        // 6. Plugin hook processing bars (including load time during plugins_loaded)
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
            $pluginName = (string) ($info['name'] ?? $slug);
            $loadTime = (float) ($info['load_time'] ?? 0.0);
            if ($loadTime > 0 && $pluginsLoadedStart > 0) {
                $loadStart = $pluginsLoadedStart + ($pluginLoadStarts[$slug] ?? 0.0);
                $pluginBars[] = [
                    'start' => $loadStart,
                    'duration' => $loadTime,
                    'title' => "load\n" . $this->formatMs($loadTime),
                ];
            }

            foreach ($pluginHooks as $hookInfo) {
                $hookName = $hookInfo['hook'];
                $hookTiming = $hookTimings[$hookName] ?? null;
                if ($hookTiming !== null && $hookTiming['start'] > 0) {
                    $offset = $hookOffsets[$hookName] ?? 0.0;
                    $duration = max($hookInfo['time'], 0.5);
                    $pluginBars[] = [
                        'start' => $hookTiming['start'] + $offset,
                        'duration' => $duration,
                        'title' => $hookName . "\n" . $this->formatMs($hookInfo['time']) . ' (' . $hookInfo['listeners'] . ' listeners)',
                    ];
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
        $themeData = $this->getCollectorData($profile, 'theme');
        /** @var list<array{hook: string, listeners: int, time: float}> $themeHooks */
        $themeHooks = $themeData['hooks'] ?? [];
        $themeTimelineEntries = [];

        if ($themeHooks !== []) {
            $themeName = (string) ($themeData['name'] ?? 'Theme');
            $themeBars = [];
            foreach ($themeHooks as $hookInfo) {
                $hookName = $hookInfo['hook'];
                $hookTiming = $hookTimings[$hookName] ?? null;
                if ($hookTiming !== null && $hookTiming['start'] > 0) {
                    $offset = $hookOffsets[$hookName] ?? 0.0;
                    $duration = max($hookInfo['time'], 0.5);
                    $themeBars[] = [
                        'start' => $hookTiming['start'] + $offset,
                        'duration' => $duration,
                        'title' => $hookName . "\n" . $this->formatMs($hookInfo['time']) . ' (' . $hookInfo['listeners'] . ' listeners)',
                    ];
                    $hookOffsets[$hookName] = $offset + $duration;
                }
            }

            if ($themeBars !== []) {
                $themeHookTime = (float) ($themeData['hook_time'] ?? 0.0);
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
}
