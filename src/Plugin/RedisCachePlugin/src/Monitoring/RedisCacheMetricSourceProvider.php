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

namespace WpPack\Plugin\RedisCachePlugin\Monitoring;

use WpPack\Component\Monitoring\MetricSource;
use WpPack\Component\Monitoring\MetricSourceProviderInterface;

final class RedisCacheMetricSourceProvider implements MetricSourceProviderInterface
{
    public function getSources(): array
    {
        $dimensions = $this->resolveDimensions();

        if ($dimensions === []) {
            return [];
        }

        return [
            new MetricSource(
                id: 'redis.cache_hits',
                label: 'Cache Hits',
                provider: 'cloudwatch',
                namespace: 'AWS/ElastiCache',
                metricName: 'CacheHits',
                unit: 'Count',
                stat: 'Sum',
                dimensions: $dimensions,
                group: 'redis',
            ),
            new MetricSource(
                id: 'redis.cache_misses',
                label: 'Cache Misses',
                provider: 'cloudwatch',
                namespace: 'AWS/ElastiCache',
                metricName: 'CacheMisses',
                unit: 'Count',
                stat: 'Sum',
                dimensions: $dimensions,
                group: 'redis',
            ),
            new MetricSource(
                id: 'redis.curr_connections',
                label: 'Current Connections',
                provider: 'cloudwatch',
                namespace: 'AWS/ElastiCache',
                metricName: 'CurrConnections',
                unit: 'Count',
                stat: 'Average',
                dimensions: $dimensions,
                group: 'redis',
            ),
            new MetricSource(
                id: 'redis.engine_cpu_utilization',
                label: 'Engine CPU Utilization',
                provider: 'cloudwatch',
                namespace: 'AWS/ElastiCache',
                metricName: 'EngineCPUUtilization',
                unit: 'Percent',
                stat: 'Average',
                dimensions: $dimensions,
                group: 'redis',
            ),
            new MetricSource(
                id: 'redis.database_memory_usage_percentage',
                label: 'Memory Usage',
                provider: 'cloudwatch',
                namespace: 'AWS/ElastiCache',
                metricName: 'DatabaseMemoryUsagePercentage',
                unit: 'Percent',
                stat: 'Average',
                dimensions: $dimensions,
                group: 'redis',
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveDimensions(): array
    {
        $clusterId = \defined('WPPACK_MONITORING_ELASTICACHE_CLUSTER_ID')
            ? (string) \constant('WPPACK_MONITORING_ELASTICACHE_CLUSTER_ID')
            : ($_ENV['WPPACK_MONITORING_ELASTICACHE_CLUSTER_ID'] ?? '');

        if ($clusterId === '') {
            return [];
        }

        return ['CacheClusterId' => $clusterId];
    }
}
