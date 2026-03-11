<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'plugin', priority: 145)]
final class PluginDataCollector extends AbstractDataCollector
{
    /** @var array<string, float> */
    private array $pluginLoadTimes = [];

    /** @var list<string> */
    private array $loadOrder = [];

    private float $lastPluginLoadStart = 0.0;

    private string $lastPluginSlug = '';

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'plugin';
    }

    public function getLabel(): string
    {
        return 'Plugins';
    }

    /**
     * Capture individual plugin load time (WP 6.7+).
     */
    public function capturePluginLoaded(string $plugin): void
    {
        $now = microtime(true);

        // Record the previous plugin's load time
        if ($this->lastPluginSlug !== '' && $this->lastPluginLoadStart > 0) {
            $this->pluginLoadTimes[$this->lastPluginSlug] = ($now - $this->lastPluginLoadStart) * 1000;
        }

        $this->lastPluginSlug = $plugin;
        $this->lastPluginLoadStart = $now;
        $this->loadOrder[] = $plugin;
    }

    /**
     * Finalize timing for the last plugin loaded.
     */
    public function capturePluginsLoaded(): void
    {
        $now = microtime(true);
        if ($this->lastPluginSlug !== '' && $this->lastPluginLoadStart > 0) {
            $this->pluginLoadTimes[$this->lastPluginSlug] = ($now - $this->lastPluginLoadStart) * 1000;
        }
        $this->lastPluginSlug = '';
        $this->lastPluginLoadStart = 0.0;
    }

    public function collect(): void
    {
        if (!function_exists('get_plugins') && !function_exists('get_option')) {
            $this->data = [
                'plugins' => [],
                'total_plugins' => 0,
                'mu_plugins' => [],
                'dropins' => [],
                'load_order' => [],
                'slowest_plugin' => '',
                'total_hook_time' => 0.0,
            ];

            return;
        }

        global $wp_filter, $wpdb;

        $activePlugins = function_exists('get_option') ? (array) get_option('active_plugins', []) : [];
        $muPlugins = function_exists('get_mu_plugins') ? array_keys(get_mu_plugins()) : [];
        $dropins = function_exists('_get_dropins') ? array_keys(array_intersect_key(_get_dropins(), @get_plugins('/../'))) : [];
        $allPluginsData = function_exists('get_plugins') ? get_plugins() : [];

        // Build per-plugin hook attribution
        $pluginHooks = $this->buildPluginHookAttribution($wp_filter ?? []);

        // Build query attribution from $wpdb->queries
        $pluginQueries = $this->buildQueryAttribution($wpdb);

        // Build plugin data
        $plugins = [];
        $totalHookTime = 0.0;
        $slowestPlugin = '';
        $slowestTime = 0.0;

        foreach ($activePlugins as $pluginFile) {
            $pluginInfo = $allPluginsData[$pluginFile] ?? [];
            $slug = dirname($pluginFile);
            if ($slug === '.') {
                $slug = pathinfo($pluginFile, PATHINFO_FILENAME);
            }

            $hooks = $pluginHooks[$slug] ?? [];
            $hookTime = 0.0;
            $listenerCount = 0;
            foreach ($hooks as $hookInfo) {
                $hookTime += $hookInfo['time'];
                $listenerCount += $hookInfo['listeners'];
            }

            $queryInfo = $pluginQueries[$slug] ?? ['count' => 0, 'time' => 0.0];

            $pluginData = [
                'name' => $pluginInfo['Name'] ?? $slug,
                'version' => $pluginInfo['Version'] ?? '',
                'load_time' => round($this->pluginLoadTimes[$pluginFile] ?? 0.0, 2),
                'hook_count' => count($hooks),
                'listener_count' => $listenerCount,
                'hook_time' => round($hookTime, 2),
                'query_count' => $queryInfo['count'],
                'query_time' => round($queryInfo['time'], 2),
                'hooks' => $hooks,
            ];

            $plugins[$pluginFile] = $pluginData;
            $totalHookTime += $hookTime;

            if ($hookTime > $slowestTime) {
                $slowestTime = $hookTime;
                $slowestPlugin = $pluginFile;
            }
        }

        // Sort by hook_time descending
        uasort($plugins, static fn(array $a, array $b): int => $b['hook_time'] <=> $a['hook_time']);

        $this->data = [
            'plugins' => $plugins,
            'total_plugins' => count($activePlugins),
            'mu_plugins' => $muPlugins,
            'dropins' => $dropins,
            'load_order' => $this->loadOrder,
            'slowest_plugin' => $slowestPlugin,
            'total_hook_time' => round($totalHookTime, 2),
        ];
    }

    public function getBadgeValue(): string
    {
        $total = $this->data['total_plugins'] ?? 0;

        return $total > 0 ? (string) $total : '';
    }

    public function getBadgeColor(): string
    {
        $total = $this->data['total_plugins'] ?? 0;

        return match (true) {
            $total >= 40 => 'red',
            $total >= 20 => 'yellow',
            default => 'green',
        };
    }

    public function reset(): void
    {
        parent::reset();
        $this->pluginLoadTimes = [];
        $this->loadOrder = [];
        $this->lastPluginLoadStart = 0.0;
        $this->lastPluginSlug = '';
    }

    /**
     * Build hook attribution for plugins from $wp_filter.
     *
     * @param array<string, mixed> $wpFilter
     * @return array<string, list<array{hook: string, listeners: int, time: float, start: float}>>
     */
    private function buildPluginHookAttribution(array $wpFilter): array
    {
        $pluginDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '';
        if ($pluginDir === '') {
            return [];
        }

        $pluginHooks = [];

        foreach ($wpFilter as $hookName => $hookObj) {
            if (!is_object($hookObj) || !isset($hookObj->callbacks)) {
                continue;
            }

            $totalListeners = 0;
            $pluginListeners = [];

            foreach ($hookObj->callbacks as $priority => $funcs) {
                foreach ($funcs as $func) {
                    $totalListeners++;
                    $slug = $this->getPluginSlugFromCallback($func['function'] ?? null, $pluginDir);
                    if ($slug !== null) {
                        $pluginListeners[$slug] = ($pluginListeners[$slug] ?? 0) + 1;
                    }
                }
            }

            if ($pluginListeners === []) {
                continue;
            }

            foreach ($pluginListeners as $slug => $count) {
                $pluginHooks[$slug][] = [
                    'hook' => $hookName,
                    'listeners' => $count,
                    'time' => 0.0,
                    'start' => 0.0,
                ];
            }
        }

        return $pluginHooks;
    }

    private function getPluginSlugFromCallback(mixed $callback, string $pluginDir): ?string
    {
        try {
            $fileName = $this->getCallbackFileName($callback);
        } catch (\ReflectionException) {
            return null;
        }

        if ($fileName === null || !str_starts_with($fileName, $pluginDir)) {
            return null;
        }

        $relative = substr($fileName, strlen($pluginDir) + 1);
        $parts = explode('/', $relative, 2);

        return $parts[0];
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
     * Build query attribution from $wpdb->queries.
     *
     * @return array<string, array{count: int, time: float}>
     */
    private function buildQueryAttribution(mixed $wpdb): array
    {
        if (!is_object($wpdb) || !isset($wpdb->queries) || !is_array($wpdb->queries)) {
            return [];
        }

        $pluginDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '';
        if ($pluginDir === '') {
            return [];
        }

        $result = [];

        foreach ($wpdb->queries as $query) {
            if (!is_array($query) || count($query) < 3) {
                continue;
            }
            $caller = (string) ($query[2] ?? '');
            $time = (float) ($query[1] ?? 0.0);

            if (str_contains($caller, $pluginDir)) {
                // Extract plugin slug from caller trace
                $pos = strpos($caller, $pluginDir);
                if ($pos !== false) {
                    $relative = substr($caller, $pos + strlen($pluginDir) + 1);
                    $parts = explode('/', $relative, 2);
                    $slug = $parts[0];

                    $result[$slug] ??= ['count' => 0, 'time' => 0.0];
                    $result[$slug]['count']++;
                    $result[$slug]['time'] += $time * 1000; // Convert to ms
                }
            }
        }

        return $result;
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('plugin_loaded', [$this, 'capturePluginLoaded'], \PHP_INT_MIN, 1);
        add_action('plugins_loaded', [$this, 'capturePluginsLoaded'], \PHP_INT_MAX, 0);
    }
}
