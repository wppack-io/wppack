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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\MockCloudWatchMonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProviderInterface;

#[CoversClass(MockCloudWatchMonitoringProvider::class)]
final class MockCloudWatchMonitoringProviderTest extends TestCase
{
    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        self::assertInstanceOf(MonitoringProviderInterface::class, new MockCloudWatchMonitoringProvider());
    }

    #[Test]
    public function getProvidersReturnsLockedSampleProviders(): void
    {
        $providers = (new MockCloudWatchMonitoringProvider())->getProviders();

        self::assertNotEmpty($providers);
        self::assertContainsOnlyInstancesOf(MonitoringProvider::class, $providers);

        foreach ($providers as $provider) {
            self::assertTrue($provider->locked, "{$provider->id} must be locked so users can't edit mock fixtures");
            self::assertSame('mock-aws', $provider->bridge);
            self::assertStringStartsWith('mock-', $provider->id);
            self::assertNotEmpty($provider->metrics);
        }
    }

    #[Test]
    public function everyMetricIsLocked(): void
    {
        foreach ((new MockCloudWatchMonitoringProvider())->getProviders() as $provider) {
            foreach ($provider->metrics as $metric) {
                self::assertTrue($metric->locked, "{$provider->id}.{$metric->id} must be locked");
            }
        }
    }

    #[Test]
    public function coversTypicalWordPressStack(): void
    {
        $ids = array_map(
            static fn(MonitoringProvider $p): string => $p->id,
            (new MockCloudWatchMonitoringProvider())->getProviders(),
        );

        foreach (['mock-ec2', 'mock-rds', 'mock-s3', 'mock-sqs', 'mock-cloudfront', 'mock-aws-waf', 'mock-ses'] as $expected) {
            self::assertContains($expected, $ids, "missing sample provider: {$expected}");
        }
    }

    #[Test]
    public function sampleProvidersUseAwsRegionSetting(): void
    {
        foreach ((new MockCloudWatchMonitoringProvider())->getProviders() as $provider) {
            self::assertNotEmpty($provider->settings->region);
        }
    }
}
