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

namespace WPPack\Component\Monitoring\Bridge\Cloudflare\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\Cloudflare\MockCloudflareMonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProviderInterface;

#[CoversClass(MockCloudflareMonitoringProvider::class)]
final class MockCloudflareMonitoringProviderTest extends TestCase
{
    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        self::assertInstanceOf(MonitoringProviderInterface::class, new MockCloudflareMonitoringProvider());
    }

    #[Test]
    public function sampleProvidersAreLockedMockCloudflare(): void
    {
        $providers = (new MockCloudflareMonitoringProvider())->getProviders();

        self::assertNotEmpty($providers);
        foreach ($providers as $provider) {
            self::assertInstanceOf(MonitoringProvider::class, $provider);
            self::assertTrue($provider->locked);
            self::assertSame('mock-cloudflare', $provider->bridge);
            self::assertStringStartsWith('mock-cf-', $provider->id);
            self::assertNotEmpty($provider->metrics);
        }
    }

    #[Test]
    public function everyMetricIsLocked(): void
    {
        foreach ((new MockCloudflareMonitoringProvider())->getProviders() as $provider) {
            foreach ($provider->metrics as $metric) {
                self::assertTrue($metric->locked, "{$provider->id}.{$metric->id} must be locked");
                self::assertStringStartsWith('Cloudflare/', $metric->namespace);
            }
        }
    }

    #[Test]
    public function exposesMockApiTokenNotRealCredentials(): void
    {
        foreach ((new MockCloudflareMonitoringProvider())->getProviders() as $provider) {
            /** @var \WPPack\Component\Monitoring\Bridge\Cloudflare\CloudflareProviderSettings $settings */
            $settings = $provider->settings;
            self::assertStringContainsString('mock', $settings->apiToken, 'settings must use a mock token, not real credentials');
        }
    }
}
