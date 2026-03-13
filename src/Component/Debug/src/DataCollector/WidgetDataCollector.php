<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'widget', priority: 80)]
final class WidgetDataCollector extends AbstractDataCollector
{
    /** @var list<array{sidebar: string, start: float, duration: float}> */
    private array $sidebarTimings = [];

    private float $currentSidebarStart = 0.0;

    private string $currentSidebar = '';

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'widget';
    }

    public function getLabel(): string
    {
        return 'Widget';
    }

    /**
     * Capture sidebar render start (dynamic_sidebar_before).
     */
    public function captureSidebarBefore(string $index): void
    {
        $this->currentSidebar = $index;
        $this->currentSidebarStart = microtime(true);
    }

    /**
     * Capture sidebar render end (dynamic_sidebar_after).
     */
    public function captureSidebarAfter(string $index): void
    {
        if ($this->currentSidebar === $index && $this->currentSidebarStart > 0) {
            $this->sidebarTimings[] = [
                'sidebar' => $index,
                'start' => $this->currentSidebarStart,
                'duration' => (microtime(true) - $this->currentSidebarStart) * 1000,
            ];
        }
        $this->currentSidebar = '';
        $this->currentSidebarStart = 0.0;
    }

    public function collect(): void
    {
        if (!function_exists('wp_get_sidebars_widgets')) {
            $this->data = [
                'sidebars' => [],
                'total_widgets' => 0,
                'total_sidebars' => 0,
                'active_widgets' => 0,
                'render_time' => 0.0,
                'sidebar_timings' => [],
            ];

            return;
        }

        global $wp_registered_sidebars, $wp_registered_widgets;

        $sidebarsWidgets = wp_get_sidebars_widgets();
        $sidebars = [];
        $totalWidgets = 0;
        $activeSidebars = 0;

        foreach ($sidebarsWidgets as $sidebarId => $widgets) {
            if ($sidebarId === 'wp_inactive_widgets') {
                continue;
            }

            $widgetList = is_array($widgets) ? $widgets : [];
            $sidebarName = $wp_registered_sidebars[$sidebarId]['name'] ?? $sidebarId;
            $widgetNames = [];

            foreach ($widgetList as $widgetId) {
                $widgetNames[] = $wp_registered_widgets[$widgetId]['name'] ?? $widgetId;
            }

            $sidebars[$sidebarId] = [
                'name' => $sidebarName,
                'widgets' => $widgetNames,
            ];

            $count = count($widgetNames);
            $totalWidgets += $count;
            if ($count > 0) {
                $activeSidebars++;
            }
        }

        // Build sidebar timing data with names
        $renderTime = 0.0;
        $sidebarTimingData = [];
        foreach ($this->sidebarTimings as $timing) {
            $name = $wp_registered_sidebars[$timing['sidebar']]['name'] ?? $timing['sidebar'];
            $duration = round($timing['duration'], 2);
            $renderTime += $duration;
            $sidebarTimingData[] = [
                'sidebar' => $timing['sidebar'],
                'name' => $name,
                'start' => $timing['start'],
                'duration' => $duration,
            ];
        }

        $this->data = [
            'sidebars' => $sidebars,
            'total_widgets' => $totalWidgets,
            'total_sidebars' => count($sidebars),
            'active_widgets' => $totalWidgets,
            'render_time' => round($renderTime, 2),
            'sidebar_timings' => $sidebarTimingData,
        ];
    }

    public function getIndicatorValue(): string
    {
        $count = (int) ($this->data['active_widgets'] ?? 0);

        return $count > 0 ? (string) $count : '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->sidebarTimings = [];
        $this->currentSidebarStart = 0.0;
        $this->currentSidebar = '';
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('dynamic_sidebar_before', [$this, 'captureSidebarBefore'], \PHP_INT_MIN, 1);
        add_action('dynamic_sidebar_after', [$this, 'captureSidebarAfter'], \PHP_INT_MAX, 1);
    }
}
