<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'cache', priority: 60)]
final class CacheDataCollector extends AbstractDataCollector
{
    private int $transientSets = 0;
    private int $transientDeletes = 0;

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
        ];
    }

    public function getBadgeValue(): string
    {
        $hitRate = $this->data['hit_rate'] ?? 0.0;

        return sprintf('%.1f%%', $hitRate);
    }

    public function getBadgeColor(): string
    {
        $hitRate = $this->data['hit_rate'] ?? 0.0;

        return match (true) {
            $hitRate >= 80 => 'green',
            $hitRate >= 50 => 'yellow',
            default => 'red',
        };
    }

    /**
     * Hook callback for setted_transient.
     */
    public function onTransientSet(): void
    {
        $this->transientSets++;
    }

    /**
     * Hook callback for deleted_transient.
     */
    public function onTransientDeleted(): void
    {
        $this->transientDeletes++;
    }

    public function reset(): void
    {
        parent::reset();
        $this->transientSets = 0;
        $this->transientDeletes = 0;
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('setted_transient', [$this, 'onTransientSet'], 10, 0);
        add_action('deleted_transient', [$this, 'onTransientDeleted'], 10, 0);
    }
}
