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

use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;
use WpPack\Component\Monitoring\ProviderSettings;

final class RedisCacheMetricSourceProvider implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        $dimensions = $this->resolveDimensions();

        if ($dimensions === []) {
            return [];
        }

        $region = $this->resolveRegion();

        return [
            new MonitoringProvider(
                id: 'redis',
                label: 'ElastiCache Redis',
                bridge: 'cloudwatch',
                settings: new ProviderSettings(region: $region),
                metrics: [
                    new MetricDefinition(
                        id: 'redis.cache_hits',
                        label: 'Cache Hits',
                        namespace: 'AWS/ElastiCache',
                        metricName: 'CacheHits',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'redis.cache_misses',
                        label: 'Cache Misses',
                        namespace: 'AWS/ElastiCache',
                        metricName: 'CacheMisses',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'redis.curr_connections',
                        label: 'Current Connections',
                        namespace: 'AWS/ElastiCache',
                        metricName: 'CurrConnections',
                        unit: 'Count',
                        stat: 'Average',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'redis.engine_cpu_utilization',
                        label: 'Engine CPU Utilization',
                        namespace: 'AWS/ElastiCache',
                        metricName: 'EngineCPUUtilization',
                        unit: 'Percent',
                        stat: 'Average',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'redis.database_memory_usage_percentage',
                        label: 'Memory Usage',
                        namespace: 'AWS/ElastiCache',
                        metricName: 'DatabaseMemoryUsagePercentage',
                        unit: 'Percent',
                        stat: 'Average',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                ],
                locked: true,
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

    private function resolveRegion(): string
    {
        if (\defined('WPPACK_MONITORING_ELASTICACHE_REGION')) {
            return (string) \constant('WPPACK_MONITORING_ELASTICACHE_REGION');
        }

        return $_ENV['WPPACK_MONITORING_ELASTICACHE_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
    }
}
