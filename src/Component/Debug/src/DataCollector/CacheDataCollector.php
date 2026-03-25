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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'cache', priority: 195)]
final class CacheDataCollector extends AbstractDataCollector
{
    private int $transientSets = 0;
    private int $transientDeletes = 0;

    /** @var list<array{name: string, operation: string, expiration: int, caller: string, time: float}> */
    private array $transientOperations = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'cache';
    }

    public function getLabel(): string
    {
        return 'Cache';
    }

    public function collect(): void
    {
        $hits = 0;
        $misses = 0;

        global $wp_object_cache;

        if (isset($wp_object_cache)) {
            if (isset($wp_object_cache->cache_hits) && is_numeric($wp_object_cache->cache_hits)) {
                $hits = (int) $wp_object_cache->cache_hits;
            }

            if (isset($wp_object_cache->cache_misses) && is_numeric($wp_object_cache->cache_misses)) {
                $misses = (int) $wp_object_cache->cache_misses;
            }
        }

        $total = $hits + $misses;
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;

        $this->data = [
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $hitRate,
            'transient_sets' => $this->transientSets,
            'transient_deletes' => $this->transientDeletes,
            'transient_operations' => $this->transientOperations,
            'object_cache_dropin' => $this->detectDropin(),
            'cache_groups' => $this->collectGroupStats(),
        ];
    }

    public function getIndicatorValue(): string
    {
        $hitRate = $this->data['hit_rate'] ?? 0.0;

        return sprintf('%.1f%%', $hitRate);
    }

    public function getIndicatorColor(): string
    {
        $hitRate = $this->data['hit_rate'] ?? 0.0;

        return match (true) {
            $hitRate >= 80 => 'green',
            $hitRate >= 50 => 'yellow',
            default => 'red',
        };
    }

    /**
     * Hook callback for set_transient / set_site_transient (WP 6.8+)
     * or setted_transient / setted_site_transient (WP < 6.8).
     */
    public function onTransientSet(string $transient = '', mixed $value = null, int $expiration = 0): void
    {
        $this->transientSets++;
        $this->transientOperations[] = [
            'name' => $transient,
            'operation' => 'set',
            'expiration' => $expiration,
            'caller' => $this->captureCaller(),
            'time' => $this->getElapsedMs(),
        ];
    }

    /**
     * Hook callback for deleted_transient / deleted_site_transient.
     */
    public function onTransientDeleted(string $transient = ''): void
    {
        $this->transientDeletes++;
        $this->transientOperations[] = [
            'name' => $transient,
            'operation' => 'delete',
            'expiration' => 0,
            'caller' => $this->captureCaller(),
            'time' => $this->getElapsedMs(),
        ];
    }

    public function reset(): void
    {
        parent::reset();
        $this->transientSets = 0;
        $this->transientDeletes = 0;
        $this->transientOperations = [];
    }

    private function getElapsedMs(): float
    {
        $requestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'] ?? 0.0;
        if ((float) $requestTimeFloat <= 0.0) {
            return 0.0;
        }

        return (microtime(true) - (float) $requestTimeFloat) * 1000;
    }

    private function captureCaller(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $skipClasses = [self::class, AbstractDataCollector::class];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            $function = $frame['function'];

            // Skip our own frames and WordPress hook internals
            if (in_array($class, $skipClasses, true)) {
                continue;
            }
            if ($function === 'do_action' || $function === 'apply_filters') {
                continue;
            }
            if (str_starts_with($function, 'call_user_func')) {
                continue;
            }

            if ($class !== '') {
                return $class . '::' . $function;
            }

            return $function;
        }

        return 'unknown';
    }

    private function detectDropin(): string
    {
        global $wp_object_cache;

        if (!isset($wp_object_cache) || !is_object($wp_object_cache)) {
            return 'none';
        }

        $class = $wp_object_cache::class;

        return match (true) {
            str_contains($class, 'Redis') => 'Redis',
            str_contains($class, 'Memcache') => 'Memcached',
            str_contains($class, 'Apcu'), str_contains($class, 'APCu') => 'APCu',
            str_contains($class, 'Relay') => 'Relay',
            $class === 'WP_Object_Cache' => 'Default (WordPress)',
            default => $class,
        };
    }

    /**
     * @return array<string, int>
     */
    private function collectGroupStats(): array
    {
        global $wp_object_cache;

        if (!isset($wp_object_cache) || !is_object($wp_object_cache)) {
            return [];
        }

        if (!property_exists($wp_object_cache, 'cache') || !is_array($wp_object_cache->cache)) {
            return [];
        }

        $stats = [];
        foreach ($wp_object_cache->cache as $group => $entries) {
            if (is_array($entries)) {
                $stats[(string) $group] = count($entries);
            }
        }

        arsort($stats);

        return $stats;
    }

    private function registerHooks(): void
    {
        // WP 6.8+ renamed 'setted_transient' → 'set_transient' (old hooks deprecated).
        // Use new hooks on 6.8+, fall back to old hooks on earlier versions.
        $useNewHooks = version_compare(get_bloginfo('version'), '6.8', '>=');
        $setHook = $useNewHooks ? 'set_transient' : 'setted_transient';
        $setSiteHook = $useNewHooks ? 'set_site_transient' : 'setted_site_transient';

        add_action($setHook, [$this, 'onTransientSet'], 10, 3);
        add_action('deleted_transient', [$this, 'onTransientDeleted'], 10, 1);
        add_action($setSiteHook, [$this, 'onTransientSet'], 10, 3);
        add_action('deleted_site_transient', [$this, 'onTransientDeleted'], 10, 1);
    }
}
