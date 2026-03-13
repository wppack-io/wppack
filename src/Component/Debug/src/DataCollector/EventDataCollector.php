<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'event', priority: 135)]
final class EventDataCollector extends AbstractDataCollector
{
    /** @var array<string, int> */
    private array $hookCounts = [];

    private int $totalFirings = 0;

    private float $lastHookStart = 0.0;

    private string $lastHookName = '';

    /** @var array<string, array{count: int, total_time: float, start: float}> */
    private array $hookTimings = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'event';
    }

    public function getLabel(): string
    {
        return 'Events';
    }

    public function captureHookFired(): void
    {
        if (!function_exists('current_filter')) {
            return;
        }

        $now = microtime(true);
        $hook = current_filter();

        if ($hook === false) {
            return;
        }

        // Record the previous hook's execution time
        if ($this->lastHookName !== '' && $this->lastHookStart > 0) {
            $durationMs = ($now - $this->lastHookStart) * 1000;
            if (!isset($this->hookTimings[$this->lastHookName])) {
                $this->hookTimings[$this->lastHookName] = ['count' => 0, 'total_time' => 0.0, 'start' => 0.0];
            }
            $this->hookTimings[$this->lastHookName]['total_time'] += $durationMs;
        }

        // Start timing the new hook
        $this->lastHookName = $hook;
        $this->lastHookStart = $now;

        $requestTimeFloat = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0.0);
        $startMs = $requestTimeFloat > 0 ? ($now - $requestTimeFloat) * 1000 : 0.0;

        if (!isset($this->hookTimings[$hook])) {
            $this->hookTimings[$hook] = ['count' => 0, 'total_time' => 0.0, 'start' => $startMs];
        }
        $this->hookTimings[$hook]['count']++;

        $this->hookCounts[$hook] = ($this->hookCounts[$hook] ?? 0) + 1;
        $this->totalFirings++;
    }

    public function collect(): void
    {
        // Count listeners from $wp_filter global
        global $wp_filter;

        $registeredHooks = 0;
        $listenerCounts = [];

        if (isset($wp_filter) && is_array($wp_filter)) {
            foreach ($wp_filter as $hookName => $hookObj) {
                if (is_object($hookObj) && isset($hookObj->callbacks)) {
                    $count = 0;
                    $callbacks = $hookObj->callbacks;
                    foreach ($callbacks as $priority => $funcs) {
                        $count += count($funcs);
                    }
                    $listenerCounts[$hookName] = $count;
                    $registeredHooks++;
                }
            }
        }

        // Find orphan hooks (fired with zero registered listeners)
        $orphanCount = 0;
        foreach ($this->hookCounts as $hook => $count) {
            if (!isset($listenerCounts[$hook]) || $listenerCounts[$hook] === 0) {
                $orphanCount++;
            }
        }

        // Top 20 most-fired hooks
        arsort($this->hookCounts);
        $topHooks = array_slice($this->hookCounts, 0, 20, true);

        // Build component attribution data
        $componentHooks = [];
        $componentSummary = [];

        if (isset($wp_filter) && is_array($wp_filter)) {
            foreach ($wp_filter as $hookName => $hookObj) {
                if (!is_object($hookObj) || !isset($hookObj->callbacks)) {
                    continue;
                }

                foreach ($hookObj->callbacks as $priority => $funcs) {
                    foreach ($funcs as $func) {
                        $resolved = $this->resolveCallback($func['function'] ?? null);
                        $component = $resolved['component'];
                        $componentType = $resolved['component_type'];

                        if ($component === '') {
                            continue;
                        }

                        // component_hooks: component => [hook => listener_count]
                        $componentHooks[$component][$hookName] = ($componentHooks[$component][$hookName] ?? 0) + 1;

                        // component_summary: aggregate
                        if (!isset($componentSummary[$component])) {
                            $componentSummary[$component] = [
                                'type' => $componentType,
                                'hooks' => [],
                                'listeners' => 0,
                                'total_time' => 0.0,
                            ];
                        }
                        $componentSummary[$component]['hooks'][$hookName] = true;
                        $componentSummary[$component]['listeners']++;
                    }
                }
            }
        }

        // Finalize component_summary: convert hooks set to count, attribute timing
        foreach ($componentSummary as $component => &$summary) {
            $hookCount = count($summary['hooks']);
            $summary['hooks'] = $hookCount;

            // Attribute hook timing proportionally
            $totalTime = 0.0;
            foreach ($componentHooks[$component] ?? [] as $hookName => $listenerCount) {
                $totalListenersForHook = $listenerCounts[$hookName] ?? 1;
                $hookTime = $this->hookTimings[$hookName]['total_time'] ?? 0.0;
                if ($totalListenersForHook > 0) {
                    $totalTime += $hookTime * ($listenerCount / $totalListenersForHook);
                }
            }
            $summary['total_time'] = round($totalTime, 2);
        }
        unset($summary);

        // Sort component_summary by total_time descending
        uasort($componentSummary, static fn(array $a, array $b): int => $b['total_time'] <=> $a['total_time']);

        $this->data = [
            'hooks' => $this->hookCounts,
            'total_firings' => $this->totalFirings,
            'unique_hooks' => count($this->hookCounts),
            'top_hooks' => $topHooks,
            'registered_hooks' => $registeredHooks,
            'orphan_hooks' => $orphanCount,
            'listener_counts' => $listenerCounts,
            'hook_timings' => $this->hookTimings,
            'component_hooks' => $componentHooks,
            'component_summary' => $componentSummary,
        ];
    }

    public function getIndicatorValue(): string
    {
        return (string) $this->totalFirings;
    }

    public function getIndicatorColor(): string
    {
        if ($this->totalFirings >= 1000) {
            return 'red';
        }

        if ($this->totalFirings >= 500) {
            return 'yellow';
        }

        return 'green';
    }

    public function reset(): void
    {
        parent::reset();
        $this->hookCounts = [];
        $this->totalFirings = 0;
        $this->lastHookStart = 0.0;
        $this->lastHookName = '';
        $this->hookTimings = [];
    }

    /**
     * Resolve a callback to determine its component and type.
     *
     * @return array{name: string, component: string, component_type: 'plugin'|'theme'|'core'|'unknown'}
     */
    private function resolveCallback(mixed $callback): array
    {
        $default = ['name' => '', 'component' => '', 'component_type' => 'unknown'];

        if ($callback === null) {
            return $default;
        }

        try {
            $fileName = $this->getCallbackFileName($callback);
        } catch (\ReflectionException) {
            return $default;
        }

        if ($fileName === null || $fileName === '') {
            return $default;
        }

        return $this->attributeFileToComponent($fileName, $callback);
    }

    private function getCallbackFileName(mixed $callback): ?string
    {
        if ($callback instanceof \Closure) {
            return (new \ReflectionFunction($callback))->getFileName() ?: null;
        }

        if (is_array($callback) && count($callback) === 2) {
            [$classOrObject, $method] = $callback;
            $className = is_object($classOrObject) ? $classOrObject::class : (string) $classOrObject;

            return (new \ReflectionMethod($className, (string) $method))->getFileName() ?: null;
        }

        if (is_string($callback) && function_exists($callback)) {
            return (new \ReflectionFunction($callback))->getFileName() ?: null;
        }

        return null;
    }

    /**
     * @return array{name: string, component: string, component_type: 'plugin'|'theme'|'core'|'unknown'}
     */
    private function attributeFileToComponent(string $fileName, mixed $callback): array
    {
        $callbackName = $this->getCallbackName($callback);

        // Check plugin directory
        $pluginDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '';
        $muPluginDir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : '';

        if ($pluginDir !== '' && str_starts_with($fileName, $pluginDir)) {
            $relative = substr($fileName, strlen($pluginDir) + 1);
            $parts = explode('/', $relative, 2);
            $slug = $parts[0];

            return ['name' => $callbackName, 'component' => $slug, 'component_type' => 'plugin'];
        }

        if ($muPluginDir !== '' && str_starts_with($fileName, $muPluginDir)) {
            $relative = substr($fileName, strlen($muPluginDir) + 1);
            $parts = explode('/', $relative, 2);
            $slug = $parts[0];

            return ['name' => $callbackName, 'component' => 'mu:' . $slug, 'component_type' => 'plugin'];
        }

        // Check theme directory
        $themeDir = defined('ABSPATH') ? (ABSPATH . 'wp-content/themes') : '';
        if ($themeDir !== '' && str_starts_with($fileName, $themeDir)) {
            $relative = substr($fileName, strlen($themeDir) + 1);
            $parts = explode('/', $relative, 2);
            $slug = $parts[0];

            return ['name' => $callbackName, 'component' => 'theme:' . $slug, 'component_type' => 'theme'];
        }

        // Check core
        $absPath = defined('ABSPATH') ? ABSPATH : '';
        if ($absPath !== '' && str_starts_with($fileName, $absPath . 'wp-includes')) {
            return ['name' => $callbackName, 'component' => 'core', 'component_type' => 'core'];
        }
        if ($absPath !== '' && str_starts_with($fileName, $absPath . 'wp-admin')) {
            return ['name' => $callbackName, 'component' => 'core', 'component_type' => 'core'];
        }

        return ['name' => $callbackName, 'component' => '', 'component_type' => 'unknown'];
    }

    private function getCallbackName(mixed $callback): string
    {
        if ($callback instanceof \Closure) {
            return 'Closure';
        }

        if (is_array($callback) && count($callback) === 2) {
            [$classOrObject, $method] = $callback;
            $className = is_object($classOrObject) ? $classOrObject::class : (string) $classOrObject;

            return $className . '::' . $method;
        }

        if (is_string($callback)) {
            return $callback;
        }

        return 'unknown';
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('all', [$this, 'captureHookFired']);
    }
}
