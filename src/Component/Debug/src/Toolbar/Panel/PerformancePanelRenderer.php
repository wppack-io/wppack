<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'performance')]
final class PerformancePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    /** @var array<string, string> */
    private const CATEGORY_COLORS = [
        'wordpress' => 'var(--wpd-primary)',
        'database' => '#f0c33c',
        'cache' => '#4ab866',
        'http' => '#9b8afb',
        'default' => '#5b9fe6',
        'controller' => '#3fcf8e',
        'template' => '#e26f56',
        'security' => '#e65054',
        'plugin' => '#d97ae6',
        'theme_hooks' => '#e26f56',
        'widget' => '#4ab866',
        'shortcode' => '#e6a23c',
        'mail' => '#e65490',
    ];

    /** @var array<string, string> */
    private const CATEGORY_LABELS = [
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
        'widget' => 'Widget',
        'shortcode' => 'Shortcode',
        'mail' => 'Mail',
    ];

    public function getName(): string
    {
        return 'performance';
    }

    public function renderPanel(): string
    {
        $fmt = $this->getFormatters();

        $timeData = $this->getCollectorData('stopwatch');
        $memoryData = $this->getCollectorData('memory');
        $dbData = $this->getCollectorData('database');
        $cacheData = $this->getCollectorData('cache');
        $httpData = $this->getCollectorData('http_client');
        $eventData = $this->getCollectorData('event');
        $mailData = $this->getCollectorData('mail');

        $totalTime = (float) ($timeData['total_time'] ?? $this->profile->getTime());
        $peakMemory = (int) ($memoryData['peak'] ?? 0);
        $memoryLimit = (int) ($memoryData['limit'] ?? 0);
        $usagePercentage = (float) ($memoryData['usage_percentage'] ?? 0.0);
        $dbCount = (int) ($dbData['total_count'] ?? 0);
        $dbTime = (float) ($dbData['total_time'] ?? 0.0);
        $cacheHitRate = (float) ($cacheData['hit_rate'] ?? 0.0);
        $httpCount = (int) ($httpData['total_count'] ?? 0);
        $httpTime = (float) ($httpData['total_time'] ?? 0.0);
        $hookFirings = (int) ($eventData['total_firings'] ?? 0);

        // Build overview cards
        $overviewCards = $this->buildOverviewCards(
            $fmt,
            $totalTime,
            $peakMemory,
            $memoryLimit,
            $usagePercentage,
            $dbCount,
            $dbTime,
            $cacheData,
            $cacheHitRate,
            $httpCount,
            $httpTime,
            $eventData,
            $hookFirings,
        );

        // Build time distribution segments
        $segments = $this->buildSegments($totalTime, $dbTime, $httpTime, $timeData, $cacheData, $mailData);

        // Build timeline entries
        $requestTimeFloat = (float) ($timeData['request_time_float'] ?? 0.0);

        /** @var array<string, array{name: string, category: string, duration: float, memory: int, start_time: float, end_time: float}> $events */
        $events = $timeData['events'] ?? [];

        [$timelineEntries, $pluginTimelineEntries, $themeTimelineEntries] = $this->buildTimeline(
            $fmt,
            $timeData,
            $dbData,
            $cacheData,
            $httpData,
            $eventData,
            $mailData,
            $totalTime,
            $requestTimeFloat,
            $events,
        );

        // Determine used categories for legend
        $allEntries = array_merge($timelineEntries, $pluginTimelineEntries, $themeTimelineEntries);
        $usedCategories = array_unique(array_column($allEntries, 'category'));

        return $this->getPhpRenderer()->render('toolbar/panels/performance', [
            'overviewCards' => $overviewCards,
            'totalTime' => $totalTime,
            'segments' => $segments,
            'timelineEntries' => $timelineEntries,
            'pluginTimelineEntries' => $pluginTimelineEntries,
            'themeTimelineEntries' => $themeTimelineEntries,
            'categoryColors' => self::CATEGORY_COLORS,
            'categoryLabels' => self::CATEGORY_LABELS,
            'usedCategories' => $usedCategories,
            'fmt' => $fmt,
        ]);
    }

    public function renderIndicator(): string
    {
        $fmt = $this->getFormatters();
        $totalTime = $this->profile->getTime();
        $value = $fmt->ms($totalTime);
        $icon = ToolbarIcons::svg('performance');

        $memoryData = $this->getCollectorData('memory');
        $usagePercentage = (float) ($memoryData['usage_percentage'] ?? 0.0);

        $dbData = $this->getCollectorData('database');
        $slowQueries = (int) ($dbData['slow_count'] ?? 0);

        $indicatorColors = self::getIndicatorColors();
        $colors = match (true) {
            $usagePercentage >= 90, $slowQueries > 0, $totalTime >= 1000 => $indicatorColors['red'],
            $totalTime >= 200 => $indicatorColors['yellow'],
            default => $indicatorColors['default'],
        };

        $bgStyle = $colors['bg'] !== 'transparent' ? ' style="background:' . $colors['bg'] . '"' : '';

        return $this->getPhpRenderer()->render('toolbar/indicators/performance', [
            'value' => $value,
            'icon' => $icon,
            'colors' => $colors,
            'bgStyle' => $bgStyle,
        ]);
    }

    /**
     * @param array<string, mixed> $cacheData
     * @param array<string, mixed> $eventData
     * @return list<array{label: string, value: string, unit: string, sub: string}>
     */
    private function buildOverviewCards(
        TemplateFormatters $fmt,
        float $totalTime,
        int $peakMemory,
        int $memoryLimit,
        float $usagePercentage,
        int $dbCount,
        float $dbTime,
        array $cacheData,
        float $cacheHitRate,
        int $httpCount,
        float $httpTime,
        array $eventData,
        int $hookFirings,
    ): array {
        $cards = [];

        [$timeVal, $timeUnit] = $fmt->msCard($totalTime);
        $cards[] = ['label' => 'Total Time', 'value' => $timeVal, 'unit' => $timeUnit, 'sub' => ''];

        $memorySub = '';
        if ($memoryLimit > 0) {
            $memorySub = $fmt->percentage($usagePercentage) . ' of ' . $fmt->bytes($memoryLimit);
        }
        if ($peakMemory > 0) {
            [$memVal, $memUnit] = $fmt->bytesCard($peakMemory);
            $cards[] = ['label' => 'Peak Memory', 'value' => $memVal, 'unit' => $memUnit, 'sub' => $memorySub];
        } else {
            $cards[] = ['label' => 'Peak Memory', 'value' => 'N/A', 'unit' => '', 'sub' => $memorySub];
        }

        $dbSub = $dbCount > 0 ? $fmt->ms($dbTime) . ' total' : '';
        $cards[] = [
            'label' => 'Database',
            'value' => $dbCount > 0 ? (string) $dbCount : 'N/A',
            'unit' => $dbCount > 0 ? 'queries' : '',
            'sub' => $dbSub,
        ];

        $cards[] = [
            'label' => 'Cache Hit Rate',
            'value' => $cacheData !== [] ? sprintf('%.1f', $cacheHitRate) : 'N/A',
            'unit' => $cacheData !== [] ? '%' : '',
            'sub' => '',
        ];

        $httpSub = $httpCount > 0 ? $fmt->ms($httpTime) . ' total' : '';
        $cards[] = [
            'label' => 'HTTP Client',
            'value' => $httpCount > 0 ? (string) $httpCount : 'N/A',
            'unit' => $httpCount > 0 ? 'requests' : '',
            'sub' => $httpSub,
        ];

        $cards[] = [
            'label' => 'Hook Firings',
            'value' => $eventData !== [] ? number_format($hookFirings) : 'N/A',
            'unit' => '',
            'sub' => '',
        ];

        return $cards;
    }

    /**
     * @param array<string, mixed> $timeData
     * @param array<string, mixed> $cacheData
     * @param array<string, mixed> $mailData
     * @return list<array{label: string, time: float, color: string}>
     */
    private function buildSegments(
        float $totalTime,
        float $dbTime,
        float $httpTime,
        array $timeData,
        array $cacheData,
        array $mailData,
    ): array {
        if ($totalTime <= 0) {
            return [];
        }

        /** @var array<string, array{name: string, category: string, duration: float}> $events */
        $events = $timeData['events'] ?? [];
        $customEvents = array_filter($events, static fn(array $e): bool => $e['category'] !== 'wordpress');

        $categoryTimes = [];
        foreach ($customEvents as $event) {
            $cat = $event['category'];
            $categoryTimes[$cat] = ($categoryTimes[$cat] ?? 0.0) + (float) $event['duration'];
        }

        $transientOps = $cacheData['transient_operations'] ?? [];
        $cacheTimeMs = 0.0;
        foreach ($transientOps as $op) {
            if (isset($op['time'])) {
                $cacheTimeMs += 0.5;
            }
        }

        $mailEmails = $mailData['emails'] ?? [];
        $mailTimeMs = 0.0;
        foreach ($mailEmails as $email) {
            $mailTimeMs += (float) ($email['duration'] ?? 0.0);
        }

        $customTotal = array_sum($categoryTimes) + $cacheTimeMs + $mailTimeMs;
        $phpTime = max(0.0, $totalTime - $dbTime - $httpTime - $customTotal);

        $segments = [];
        $segments[] = ['label' => 'PHP', 'time' => $phpTime, 'color' => 'var(--wpd-primary)'];
        $segments[] = ['label' => 'Database', 'time' => $dbTime, 'color' => '#f0c33c'];
        $segments[] = ['label' => 'HTTP Client', 'time' => $httpTime, 'color' => '#9b8afb'];

        if ($mailTimeMs > 0) {
            $segments[] = ['label' => 'Mail', 'time' => $mailTimeMs, 'color' => '#e65490'];
        }
        if ($cacheTimeMs > 0) {
            $segments[] = ['label' => 'Cache', 'time' => $cacheTimeMs, 'color' => '#4ab866'];
        }

        $dynamicColors = ['template' => '#e26f56', 'controller' => '#3fcf8e'];
        foreach ($categoryTimes as $cat => $catTime) {
            $segments[] = ['label' => ucfirst($cat), 'time' => $catTime, 'color' => $dynamicColors[$cat] ?? '#5b9fe6'];
        }

        return $segments;
    }

    /**
     * @param array<string, mixed> $timeData
     * @param array<string, mixed> $dbData
     * @param array<string, mixed> $cacheData
     * @param array<string, mixed> $httpData
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $mailData
     * @param array<string, mixed> $events
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>, list<array<string, mixed>>}
     */
    private function buildTimeline(
        TemplateFormatters $fmt,
        array $timeData,
        array $dbData,
        array $cacheData,
        array $httpData,
        array $eventData,
        array $mailData,
        float $totalTime,
        float $requestTimeFloat,
        array $events,
    ): array {
        $timelineEntries = [];

        // 1. Lifecycle phases
        $phases = $timeData['phases'] ?? [];
        $previousTime = 0.0;
        foreach ($phases as $phaseName => $phaseTime) {
            $duration = $phaseTime - $previousTime;
            $timelineEntries[] = [
                'name' => $phaseName,
                'start' => $previousTime,
                'duration' => $duration,
                'category' => 'wordpress',
                'title' => $phaseName . "\n" . $fmt->ms($previousTime) . ' → ' . $fmt->ms($phaseTime) . ' (' . $fmt->ms($duration) . ')',
            ];
            $previousTime = $phaseTime;
        }

        // 2. DB queries
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
                'title' => $sql . "\n" . $fmt->ms($durationMs) . ' — ' . $query['caller'],
            ];
        }
        if ($dbBars !== []) {
            $dbLabel = sprintf('Database (%d queries)', count($dbBars));
            $timelineEntries[] = [
                'name' => $dbLabel, 'start' => $dbBars[0]['start'], 'duration' => 0.0,
                'category' => 'database',
                'title' => $dbLabel . ' — ' . $fmt->ms($dbTotalMs) . ' total',
                'value' => $dbTotalMs, 'bars' => $dbBars,
            ];
        }

        // 3. Transient operations
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
                'name' => $cacheLabel, 'start' => $cacheBars[0]['start'], 'duration' => 0.0,
                'category' => 'cache', 'title' => $cacheLabel, 'bars' => $cacheBars,
            ];
        }

        // 4. HTTP Client requests
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
                'title' => $req['method'] . ' ' . $req['url'] . "\n" . $fmt->ms($durationMs) . ' — ' . $req['status_code'],
            ];
        }
        if ($httpBars !== []) {
            $httpLabel = sprintf('HTTP Client (%d requests)', count($httpBars));
            $timelineEntries[] = [
                'name' => $httpLabel, 'start' => $httpBars[0]['start'], 'duration' => 0.0,
                'category' => 'http',
                'title' => $httpLabel . ' — ' . $fmt->ms($httpTotalMs) . ' total',
                'value' => $httpTotalMs, 'bars' => $httpBars,
            ];
        }

        // 5. Custom stopwatch events
        $customEvents = array_filter($events, static fn(array $e): bool => $e['category'] !== 'wordpress');
        foreach ($customEvents as $event) {
            $eventStart = (float) $event['start_time'];
            $eventDuration = (float) $event['duration'];
            $timelineEntries[] = [
                'name' => $event['name'],
                'start' => $eventStart,
                'duration' => $eventDuration,
                'category' => $event['category'],
                'title' => $event['name'] . "\n+" . $fmt->ms($eventStart) . ' — ' . $fmt->ms($eventDuration),
            ];
        }

        // 6. Plugin hook processing bars
        $hookTimings = $eventData['hook_timings'] ?? [];
        $hookOffsets = [];
        $pluginTimelineEntries = $this->buildPluginTimeline($fmt, $events, $hookTimings, $hookOffsets);

        // 7. Theme hook processing bars
        $themeTimelineEntries = $this->buildThemeTimeline($fmt, $hookTimings, $hookOffsets);

        // 8. Widget sidebar render bars
        $widgetData = $this->getCollectorData('widget');
        $sidebarTimings = $widgetData['sidebar_timings'] ?? [];
        if ($sidebarTimings !== []) {
            $widgetBars = [];
            $widgetTotalTime = 0.0;
            foreach ($sidebarTimings as $timing) {
                $startMs = ((float) $timing['start'] - $requestTimeFloat) * 1000;
                $durationMs = (float) $timing['duration'];
                $widgetTotalTime += $durationMs;
                $widgetBars[] = [
                    'start' => $startMs,
                    'duration' => max($durationMs, 0.5),
                    'title' => $timing['name'] . "\n" . $fmt->ms($durationMs),
                ];
            }
            $widgetLabel = sprintf('Widgets (%d sidebars)', count($sidebarTimings));
            $timelineEntries[] = [
                'name' => $widgetLabel, 'start' => $widgetBars[0]['start'], 'duration' => 0.0,
                'category' => 'widget',
                'title' => $widgetLabel . ' — ' . $fmt->ms($widgetTotalTime),
                'value' => $widgetTotalTime, 'bars' => $widgetBars,
            ];
        }

        // 9. Shortcode execution bars
        $shortcodeData = $this->getCollectorData('shortcode');
        $shortcodeExecutions = $shortcodeData['executions'] ?? [];
        if ($shortcodeExecutions !== []) {
            $shortcodeBars = [];
            $shortcodeTotalTime = 0.0;
            foreach ($shortcodeExecutions as $exec) {
                $startMs = ((float) $exec['start'] - $requestTimeFloat) * 1000;
                $durationMs = (float) $exec['duration'];
                $shortcodeTotalTime += $durationMs;
                $shortcodeBars[] = [
                    'start' => $startMs,
                    'duration' => max($durationMs, 0.5),
                    'title' => '[' . $exec['tag'] . "]\n" . $fmt->ms($durationMs),
                ];
            }
            $shortcodeLabel = sprintf('Shortcodes (%d executions)', count($shortcodeExecutions));
            $timelineEntries[] = [
                'name' => $shortcodeLabel, 'start' => $shortcodeBars[0]['start'], 'duration' => 0.0,
                'category' => 'shortcode',
                'title' => $shortcodeLabel . ' — ' . $fmt->ms($shortcodeTotalTime),
                'value' => $shortcodeTotalTime, 'bars' => $shortcodeBars,
            ];
        }

        // 10. Mail send bars
        $mailEmails = $mailData['emails'] ?? [];
        $mailBars = [];
        $mailTotalMs = 0.0;
        foreach ($mailEmails as $email) {
            if (!isset($email['start'])) {
                continue;
            }
            $startMs = ((float) $email['start'] - $requestTimeFloat) * 1000;
            $durationMs = (float) ($email['duration'] ?? 0.0);
            $mailTotalMs += $durationMs;
            $statusLabel = $email['status'] === 'sent' ? 'sent' : $email['status'];
            $mailBars[] = [
                'start' => $startMs,
                'duration' => max($durationMs, 0.5),
                'title' => $email['subject'] . ' (' . $statusLabel . ")\n" . $fmt->ms($durationMs),
            ];
        }
        if ($mailBars !== []) {
            $mailLabel = sprintf('Mail (%d emails)', count($mailBars));
            $timelineEntries[] = [
                'name' => $mailLabel, 'start' => $mailBars[0]['start'], 'duration' => 0.0,
                'category' => 'mail',
                'title' => $mailLabel . ' — ' . $fmt->ms($mailTotalMs) . ' total',
                'value' => $mailTotalMs, 'bars' => $mailBars,
            ];
        }

        // Sort main timeline by start time
        usort($timelineEntries, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

        // Sort plugin entries by value descending
        usort($pluginTimelineEntries, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);

        return [$timelineEntries, $pluginTimelineEntries, $themeTimelineEntries];
    }

    /**
     * @param array<string, mixed> $events
     * @param array<string, mixed> $hookTimings
     * @param array<string, float> $hookOffsets
     * @param-out array<string, float> $hookOffsets
     * @return list<array<string, mixed>>
     */
    private function buildPluginTimeline(TemplateFormatters $fmt, array $events, array $hookTimings, array &$hookOffsets): array
    {
        $pluginData = $this->getCollectorData('plugin');
        $pluginEntries = $pluginData['plugins'] ?? [];
        $loadOrder = $pluginData['load_order'] ?? [];

        // plugins_loaded phase start
        $pluginsLoadedStart = 0.0;
        foreach ($events as $event) {
            if ($event['name'] === 'plugins_loaded') {
                $pluginsLoadedStart = (float) $event['start_time'];
                break;
            }
        }

        // Pre-calculate load offsets
        $pluginLoadStarts = [];
        $loadOffset = 0.0;
        foreach ($loadOrder as $pluginFile) {
            $pluginLoadStarts[$pluginFile] = $loadOffset;
            $loadOffset += (float) ($pluginEntries[$pluginFile]['load_time'] ?? 0.0);
        }

        $result = [];
        foreach ($pluginEntries as $slug => $info) {
            $pluginHooks = $info['hooks'] ?? [];
            $pluginBars = [];

            $loadTime = (float) ($info['load_time'] ?? 0.0);
            if ($loadTime > 0 && $pluginsLoadedStart > 0) {
                $loadStart = $pluginsLoadedStart + ($pluginLoadStarts[$slug] ?? 0.0);
                $pluginBars[] = [
                    'start' => $loadStart,
                    'duration' => $loadTime,
                    'title' => "load\n" . $fmt->ms($loadTime),
                ];
            }

            foreach ($pluginHooks as $hookInfo) {
                $hookName = (string) $hookInfo['hook'];
                $hookTiming = $hookTimings[$hookName] ?? null;
                if ($hookTiming !== null && $hookTiming['start'] > 0) {
                    $offset = $hookOffsets[$hookName] ?? 0.0;
                    $duration = max((float) $hookInfo['time'], 0.5);
                    $pluginBars[] = [
                        'start' => (float) $hookTiming['start'] + $offset,
                        'duration' => $duration,
                        'title' => $hookName . "\n" . $fmt->ms((float) $hookInfo['time']) . ' (' . $hookInfo['listeners'] . ' listeners)',
                    ];
                    $hookOffsets[$hookName] = $offset + $duration;
                }
            }

            if ($pluginBars !== []) {
                $hookTime = (float) ($info['hook_time'] ?? 0.0);
                $result[] = [
                    'name' => (string) ($info['name'] ?? $slug),
                    'start' => $pluginBars[0]['start'],
                    'duration' => 0.0,
                    'category' => 'plugin',
                    'title' => (string) ($info['name'] ?? $slug) . ' — ' . $fmt->ms($hookTime),
                    'value' => $hookTime,
                    'bars' => $pluginBars,
                ];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $hookTimings
     * @param array<string, float> $hookOffsets
     * @param-out array<string, float> $hookOffsets
     * @return list<array<string, mixed>>
     */
    private function buildThemeTimeline(TemplateFormatters $fmt, array $hookTimings, array &$hookOffsets): array
    {
        $themeData = $this->getCollectorData('theme');
        $themeHooks = $themeData['hooks'] ?? [];

        if ($themeHooks === []) {
            return [];
        }

        $themeName = (string) ($themeData['name'] ?? 'Theme');
        $themeBars = [];
        foreach ($themeHooks as $hookInfo) {
            $hookName = (string) $hookInfo['hook'];
            $hookTiming = $hookTimings[$hookName] ?? null;
            if ($hookTiming !== null && $hookTiming['start'] > 0) {
                $offset = $hookOffsets[$hookName] ?? 0.0;
                $duration = max((float) $hookInfo['time'], 0.5);
                $themeBars[] = [
                    'start' => (float) $hookTiming['start'] + $offset,
                    'duration' => $duration,
                    'title' => $hookName . "\n" . $fmt->ms((float) $hookInfo['time']) . ' (' . $hookInfo['listeners'] . ' listeners)',
                ];
                $hookOffsets[$hookName] = $offset + $duration;
            }
        }

        if ($themeBars === []) {
            return [];
        }

        $themeHookTime = (float) ($themeData['hook_time'] ?? 0.0);

        return [[
            'name' => $themeName,
            'start' => $themeBars[0]['start'],
            'duration' => 0.0,
            'category' => 'theme_hooks',
            'title' => $themeName . ' — ' . $fmt->ms($themeHookTime),
            'value' => $themeHookTime,
            'bars' => $themeBars,
        ]];
    }
}
