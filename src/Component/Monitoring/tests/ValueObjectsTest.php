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

namespace WPPack\Component\Monitoring\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricPoint;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\ProviderSettings;

#[CoversClass(MetricDefinition::class)]
#[CoversClass(MetricPoint::class)]
#[CoversClass(MetricResult::class)]
#[CoversClass(MetricTimeRange::class)]
#[CoversClass(MonitoringProvider::class)]
#[CoversClass(ProviderSettings::class)]
final class ValueObjectsTest extends TestCase
{
    // ── MetricDefinition ─────────────────────────────────────────────

    #[Test]
    public function metricDefinitionAppliesDocumentedDefaults(): void
    {
        $metric = new MetricDefinition(id: 'cpu', label: 'CPU Utilisation');

        self::assertSame('cpu', $metric->id);
        self::assertSame('CPU Utilisation', $metric->label);
        self::assertSame('', $metric->description);
        self::assertSame('Count', $metric->unit);
        self::assertSame('Average', $metric->stat);
        self::assertSame([], $metric->dimensions);
        self::assertSame(300, $metric->periodSeconds);
        self::assertFalse($metric->locked);
    }

    #[Test]
    public function metricDefinitionKeepsAllExplicitFields(): void
    {
        $metric = new MetricDefinition(
            id: 'cpu',
            label: 'CPU',
            description: 'AWS EC2 CPU utilisation',
            namespace: 'AWS/EC2',
            metricName: 'CPUUtilization',
            unit: 'Percent',
            stat: 'Maximum',
            dimensions: ['InstanceId' => 'i-0123'],
            periodSeconds: 60,
            locked: true,
        );

        self::assertSame('AWS/EC2', $metric->namespace);
        self::assertSame('CPUUtilization', $metric->metricName);
        self::assertSame('Percent', $metric->unit);
        self::assertSame('Maximum', $metric->stat);
        self::assertSame(['InstanceId' => 'i-0123'], $metric->dimensions);
        self::assertSame(60, $metric->periodSeconds);
        self::assertTrue($metric->locked);
    }

    // ── MetricPoint ─────────────────────────────────────────────────

    #[Test]
    public function metricPointCarriesTimestampAndValue(): void
    {
        $ts = new \DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $point = new MetricPoint($ts, 42.5);

        self::assertSame($ts, $point->timestamp);
        self::assertSame(42.5, $point->value);
        self::assertSame('Average', $point->stat);
    }

    #[Test]
    public function metricPointAllowsCustomStat(): void
    {
        $point = new MetricPoint(new \DateTimeImmutable(), 0.0, stat: 'Maximum');

        self::assertSame('Maximum', $point->stat);
    }

    // ── MetricResult ────────────────────────────────────────────────

    #[Test]
    public function metricResultDefaultsEmptyDatapointsAndNullFetchedAt(): void
    {
        $result = new MetricResult(
            sourceId: 'cpu',
            label: 'CPU',
            unit: 'Percent',
            group: 'EC2',
        );

        self::assertSame([], $result->datapoints);
        self::assertNull($result->fetchedAt);
        self::assertNull($result->error);
    }

    #[Test]
    public function metricResultExposesDatapointsAndError(): void
    {
        $p = new MetricPoint(new \DateTimeImmutable(), 1.0);
        $fetchedAt = new \DateTimeImmutable();

        $result = new MetricResult(
            sourceId: 'cpu',
            label: 'CPU',
            unit: 'Percent',
            group: 'EC2',
            datapoints: [$p],
            fetchedAt: $fetchedAt,
            error: 'rate limited',
        );

        self::assertSame([$p], $result->datapoints);
        self::assertSame($fetchedAt, $result->fetchedAt);
        self::assertSame('rate limited', $result->error);
    }

    // ── MetricTimeRange ─────────────────────────────────────────────

    #[Test]
    public function metricTimeRangeDefaultsTo300Seconds(): void
    {
        $start = new \DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $end = new \DateTimeImmutable('2024-01-01T01:00:00+00:00');

        $range = new MetricTimeRange($start, $end);

        self::assertSame(300, $range->periodSeconds);
        self::assertSame($start, $range->start);
        self::assertSame($end, $range->end);
    }

    #[Test]
    public function metricTimeRangeTakesExplicitPeriod(): void
    {
        $range = new MetricTimeRange(
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            periodSeconds: 60,
        );

        self::assertSame(60, $range->periodSeconds);
    }

    // ── MonitoringProvider ──────────────────────────────────────────

    #[Test]
    public function monitoringProviderDefaultsEmptyMetricsAndUnlocked(): void
    {
        $provider = new MonitoringProvider(
            id: 'p1',
            label: 'My Provider',
            bridge: 'cloudwatch',
            settings: new ProviderSettings(),
        );

        self::assertSame([], $provider->metrics);
        self::assertFalse($provider->locked);
        self::assertNull($provider->templateId);
    }

    #[Test]
    public function monitoringProviderRetainsMetricsAndLock(): void
    {
        $metric = new MetricDefinition('cpu', 'CPU');
        $provider = new MonitoringProvider(
            id: 'p1',
            label: 'My Provider',
            bridge: 'cloudwatch',
            settings: new ProviderSettings(),
            metrics: [$metric],
            locked: true,
            templateId: 'aws-rds',
        );

        self::assertSame([$metric], $provider->metrics);
        self::assertTrue($provider->locked);
        self::assertSame('aws-rds', $provider->templateId);
    }

    // ── ProviderSettings base ────────────────────────────────────────

    #[Test]
    public function providerSettingsBaseDefaultsAreEmpty(): void
    {
        self::assertSame([], ProviderSettings::sensitiveFields());
        self::assertSame([], (new ProviderSettings())->toArray());
        self::assertSame([], ProviderSettings::fromArray([])->toArray());
    }
}
