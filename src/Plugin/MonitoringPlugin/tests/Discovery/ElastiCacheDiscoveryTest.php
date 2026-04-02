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

namespace WpPack\Plugin\MonitoringPlugin\Tests\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\MonitoringPlugin\Discovery\ElastiCacheDiscovery;

final class ElastiCacheDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['WPPACK_CACHE_DSN']);
    }

    #[Test]
    public function returnsEmptyWhenNoDsnConfigured(): void
    {
        unset($_ENV['WPPACK_CACHE_DSN']);

        $discovery = new ElastiCacheDiscovery();

        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function discoversStandardElastiCacheEndpoint(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://prod-redis-001.abc123.ap-northeast-1.cache.amazonaws.com:6379';

        $discovery = new ElastiCacheDiscovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);

        $provider = $providers[0];
        self::assertSame('redis', $provider->id);
        self::assertSame('ElastiCache Redis', $provider->label);
        self::assertSame('cloudwatch', $provider->bridge);
        self::assertSame('ap-northeast-1', $provider->settings->region);
        self::assertTrue($provider->locked);

        // Verify dimensions contain the cluster ID
        self::assertNotEmpty($provider->metrics);
        $firstMetric = $provider->metrics[0];
        self::assertSame('prod-redis-001', $firstMetric->dimensions['CacheClusterId']);
    }

    #[Test]
    public function discoversClusterModeEndpoint(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://my-cluster.abc123.clustercfg.us-west-2.cache.amazonaws.com:6379';

        $discovery = new ElastiCacheDiscovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('us-west-2', $providers[0]->settings->region);

        $firstMetric = $providers[0]->metrics[0];
        self::assertSame('my-cluster', $firstMetric->dimensions['CacheClusterId']);
    }

    #[Test]
    public function returnsEmptyForNonElastiCacheHost(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://localhost:6379';

        $discovery = new ElastiCacheDiscovery();

        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function returnsEmptyForEmptyDsn(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = '';

        $discovery = new ElastiCacheDiscovery();

        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function metricsIncludeExpectedCloudWatchMetrics(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://test-001.abc123.eu-west-1.cache.amazonaws.com:6379';

        $discovery = new ElastiCacheDiscovery();
        $providers = $discovery->getProviders();
        $metrics = $providers[0]->metrics;

        $metricNames = array_map(fn($m) => $m->metricName, $metrics);

        self::assertContains('CacheHits', $metricNames);
        self::assertContains('CacheMisses', $metricNames);
        self::assertContains('CurrConnections', $metricNames);
        self::assertContains('EngineCPUUtilization', $metricNames);
        self::assertContains('DatabaseMemoryUsagePercentage', $metricNames);
    }

    #[Test]
    public function allMetricsAreLocked(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://test-001.abc123.eu-west-1.cache.amazonaws.com:6379';

        $discovery = new ElastiCacheDiscovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertTrue($metric->locked, "Metric {$metric->id} should be locked");
        }
    }

    #[Test]
    public function allMetricsUseElastiCacheNamespace(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://test-001.abc123.eu-west-1.cache.amazonaws.com:6379';

        $discovery = new ElastiCacheDiscovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertSame('AWS/ElastiCache', $metric->namespace, "Metric {$metric->id} should use AWS/ElastiCache namespace");
        }
    }
}
