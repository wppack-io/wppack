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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\SesDiscovery;

final class SesDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['MAILER_DSN']);
    }

    #[Test]
    public function returnsEmptyWhenNoDsnConfigured(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        unset($_ENV['MAILER_DSN']);

        $discovery = new SesDiscovery();

        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function discoversSesDsn(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses://AKIA:secret@email.ap-northeast-1.amazonaws.com';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);

        $provider = $providers[0];
        self::assertSame('ses', $provider->id);
        self::assertSame('SES (Email)', $provider->label);
        self::assertSame('cloudwatch', $provider->bridge);
        self::assertSame('ap-northeast-1', $provider->settings->region);
        self::assertTrue($provider->locked);
    }

    #[Test]
    public function discoversSesPlusSchemeDsn(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses+https://AKIA:secret@email.us-west-2.amazonaws.com';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('us-west-2', $providers[0]->settings->region);
    }

    #[Test]
    public function returnsEmptyForNonSesDsn(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'smtp://localhost:587';

        $discovery = new SesDiscovery();

        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function returnsEmptyForEmptyDsn(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = '';

        $discovery = new SesDiscovery();

        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function fallsBackToDefaultRegionWhenHostNotAwsPattern(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses://AKIA:secret@default';
        $_ENV['AWS_DEFAULT_REGION'] = 'eu-central-1';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('eu-central-1', $providers[0]->settings->region);

        unset($_ENV['AWS_DEFAULT_REGION']);
    }

    #[Test]
    public function fallsBackToUsEast1WhenNoRegionHint(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses://AKIA:secret@default';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('us-east-1', $providers[0]->settings->region);
    }

    #[Test]
    public function metricsIncludeExpectedSesMetrics(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses://AKIA:secret@email.ap-northeast-1.amazonaws.com';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();
        $metrics = $providers[0]->metrics;

        $metricNames = array_map(fn($m) => $m->metricName, $metrics);

        self::assertContains('Send', $metricNames);
        self::assertContains('Delivery', $metricNames);
        self::assertContains('Bounce', $metricNames);
        self::assertContains('Complaint', $metricNames);
        self::assertContains('Reject', $metricNames);
    }

    #[Test]
    public function allMetricsAreLocked(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses://AKIA:secret@email.ap-northeast-1.amazonaws.com';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertTrue($metric->locked, "Metric {$metric->id} should be locked");
        }
    }

    #[Test]
    public function allMetricsUseSesNamespace(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'ses://AKIA:secret@email.ap-northeast-1.amazonaws.com';

        $discovery = new SesDiscovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertSame('AWS/SES', $metric->namespace, "Metric {$metric->id} should use AWS/SES namespace");
        }
    }
}
