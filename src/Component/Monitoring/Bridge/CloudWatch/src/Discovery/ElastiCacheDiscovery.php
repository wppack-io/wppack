<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery;

use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Dsn\Exception\InvalidDsnException;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProviderInterface;

final class ElastiCacheDiscovery implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        $endpoint = $this->parseElastiCacheEndpoint();

        if ($endpoint === null) {
            return [];
        }

        $dimensions = ['CacheClusterId' => $endpoint['clusterId']];

        return [
            new MonitoringProvider(
                id: 'redis',
                label: 'ElastiCache Redis',
                bridge: 'cloudwatch',
                settings: new AwsProviderSettings(region: $endpoint['region']),
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
     * Parse ElastiCache endpoint from CACHE_DSN.
     *
     * Endpoint format: {cluster-id}.{hash}.{region}.cache.amazonaws.com
     * Cluster mode:    {cluster-id}.{hash}.clustercfg.{region}.cache.amazonaws.com
     *
     * @return array{clusterId: string, region: string}|null
     */
    private function parseElastiCacheEndpoint(): ?array
    {
        $dsnString = \defined('CACHE_DSN') ? (string) \constant('CACHE_DSN') : ($_ENV['CACHE_DSN'] ?? '');

        if ($dsnString === '') {
            return null;
        }

        try {
            $dsn = Dsn::fromString($dsnString);
        } catch (InvalidDsnException) {
            return null;
        }

        $host = $dsn->getHost();

        if ($host === null || !str_contains($host, '.cache.amazonaws.com')) {
            return null;
        }

        // {cluster-id}.{hash}.{region}.cache.amazonaws.com
        // or {cluster-id}.{hash}.clustercfg.{region}.cache.amazonaws.com (cluster mode)
        $parts = explode('.', $host);
        $cachePos = array_search('cache', $parts, true);

        if ($cachePos === false || $cachePos < 2) {
            return null;
        }

        $clusterId = $parts[0];
        // Region is right before "cache" (or before "clustercfg" then "cache")
        $regionPos = $cachePos - 1;
        if ($parts[$regionPos] === 'clustercfg' && $regionPos > 1) {
            $regionPos--;
        }

        return [
            'clusterId' => $clusterId,
            'region' => $parts[$regionPos],
        ];
    }
}
