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
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MockMetricProvider;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\ProviderSettings;

#[CoversClass(MockMetricProvider::class)]
final class MockMetricProviderTest extends TestCase
{
    private MockMetricProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new MockMetricProvider();
    }

    private function definition(string $id, string $unit = 'Count', int $period = 300): MetricDefinition
    {
        return new MetricDefinition(
            id: $id,
            label: $id,
            unit: $unit,
            periodSeconds: $period,
        );
    }

    private function providerWithMetrics(MetricDefinition ...$metrics): MonitoringProvider
    {
        return new MonitoringProvider(
            id: 'mock-provider',
            label: 'Mock Provider',
            bridge: 'mock',
            settings: new ProviderSettings(),
            metrics: $metrics,
        );
    }

    private function range(int $durationSeconds, int $periodSeconds = 300): MetricTimeRange
    {
        $end = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $start = $end->sub(new \DateInterval('PT' . $durationSeconds . 'S'));

        return new MetricTimeRange($start, $end, $periodSeconds);
    }

    // ── Metadata accessors ───────────────────────────────────────────

    #[Test]
    public function metadataAccessorsReturnSanitisedDefaults(): void
    {
        self::assertSame('mock', $this->provider->getName());
        self::assertSame('Mock', $this->provider->getLabel());
        self::assertTrue($this->provider->isAvailable());
        self::assertSame([], $this->provider->getFormFields());
        self::assertSame([], $this->provider->getTemplates());
        self::assertSame([], $this->provider->getDimensionLabels());
        self::assertSame([], $this->provider->getDefaultSettings());
        self::assertNull($this->provider->getSetupGuide());
        self::assertTrue($this->provider->validateSettings([]));
        self::assertSame(ProviderSettings::class, $this->provider->getSettingsClass());
    }

    // ── Determinism ──────────────────────────────────────────────────

    #[Test]
    public function sameMetricAndTimestampReturnsSameValueAcrossCalls(): void
    {
        $metric = $this->definition('cpu', 'Percent');
        $provider = $this->providerWithMetrics($metric);
        $range = $this->range(3600);

        $a = $this->provider->query($provider, $range);
        $b = $this->provider->query($provider, $range);

        self::assertCount(1, $a);
        self::assertCount(1, $b);
        self::assertSameSize($a[0]->datapoints, $b[0]->datapoints);

        foreach ($a[0]->datapoints as $i => $pointA) {
            $pointB = $b[0]->datapoints[$i];
            self::assertSame($pointA->value, $pointB->value, "Datapoint #{$i} should be deterministic");
            self::assertSame($pointA->timestamp->getTimestamp(), $pointB->timestamp->getTimestamp());
        }
    }

    // ── Result shape ─────────────────────────────────────────────────

    #[Test]
    public function queryProducesOneResultPerMetricPreservingIdentity(): void
    {
        $cpu = $this->definition('cpu', 'Percent');
        $mem = $this->definition('memory', 'Bytes');
        $provider = $this->providerWithMetrics($cpu, $mem);

        $results = $this->provider->query($provider, $this->range(3600));

        self::assertCount(2, $results);
        self::assertSame('cpu', $results[0]->sourceId);
        self::assertSame('Percent', $results[0]->unit);
        self::assertSame('memory', $results[1]->sourceId);
        self::assertSame('Bytes', $results[1]->unit);
        self::assertSame('mock-provider', $results[0]->group);
        self::assertInstanceOf(\DateTimeImmutable::class, $results[0]->fetchedAt);
    }

    #[Test]
    public function zeroOrNegativeMetricPeriodFallsBackTo300s(): void
    {
        $provider = $this->providerWithMetrics($this->definition('x', period: 0));

        // 1 hour range, period falls back to 300s → at most 13 points
        // (start, +300, +600, …, end inclusive).
        $results = $this->provider->query($provider, $this->range(3600));

        self::assertLessThanOrEqual(13, \count($results[0]->datapoints));
        self::assertGreaterThanOrEqual(12, \count($results[0]->datapoints));
    }

    // ── Period resolution tiers ──────────────────────────────────────

    #[Test]
    public function smallRangeUses60sFloor(): void
    {
        // 1h range → 60s minimum; 300s metric period wins.
        $metric = $this->definition('cpu', period: 60);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        // 3600 / 60 = 60 steps + endpoint = 61
        self::assertCount(61, $results[0]->datapoints);
    }

    #[Test]
    public function mediumRangeUses300sFloor(): void
    {
        // 12h range → 300s minimum; metric asks 60 but floor raises to 300.
        $metric = $this->definition('cpu', period: 60);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(43_200));

        // 43200 / 300 = 144 + 1 = 145
        self::assertCount(145, $results[0]->datapoints);
    }

    #[Test]
    public function longRangeUses900sFloor(): void
    {
        // 2 day range → 900s floor applies (21_600 < 43200 ≤ 259_200).
        $metric = $this->definition('cpu', period: 60);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(172_800));

        self::assertGreaterThan(0, \count($results[0]->datapoints));
        // Max interval should be at least 900s.
        $first = $results[0]->datapoints[0]->timestamp->getTimestamp();
        $second = $results[0]->datapoints[1]->timestamp->getTimestamp();
        self::assertGreaterThanOrEqual(900, $second - $first);
    }

    #[Test]
    public function veryLongRangeUses3600sFloor(): void
    {
        // 7 day range → 3600s floor.
        $metric = $this->definition('cpu', period: 60);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(604_800));

        $first = $results[0]->datapoints[0]->timestamp->getTimestamp();
        $second = $results[0]->datapoints[1]->timestamp->getTimestamp();
        self::assertSame(3600, $second - $first);
    }

    #[Test]
    public function metricPeriodOverridesFloorWhenLarger(): void
    {
        // 1h range, 60s floor, but metric asks 900s → 900 wins.
        $metric = $this->definition('cpu', period: 900);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        // 3600 / 900 = 4 + 1 = 5 datapoints
        self::assertCount(5, $results[0]->datapoints);
    }

    // ── Unit-specific base values ────────────────────────────────────

    #[Test]
    public function percentValuesStayRealistic(): void
    {
        // Base 35%, ±30% variation → roughly 24.5 – 45.5.
        $metric = $this->definition('cpu', 'Percent', 300);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        foreach ($results[0]->datapoints as $point) {
            self::assertGreaterThanOrEqual(0.0, $point->value);
            self::assertLessThan(100.0, $point->value);
        }
    }

    #[Test]
    public function secondsUnitProducesFractionalValues(): void
    {
        $metric = $this->definition('latency', 'Seconds', 300);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        $hasSubsecond = false;
        foreach ($results[0]->datapoints as $point) {
            if ($point->value < 1.0) {
                $hasSubsecond = true;
                break;
            }
        }
        self::assertTrue($hasSubsecond, 'Seconds unit should produce values below 1.0 with 0.005s base');
    }

    #[Test]
    public function bytesUnitProducesLargeValues(): void
    {
        $metric = $this->definition('rss', 'Bytes', 300);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        foreach ($results[0]->datapoints as $point) {
            // Base 1GiB, ±30% variation → no value should be under 512MiB.
            self::assertGreaterThan(500_000_000.0, $point->value);
        }
    }

    #[Test]
    public function valuesAreNonNegativeAndRoundedToTwoDecimals(): void
    {
        $metric = $this->definition('latency', 'Seconds', 300);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        foreach ($results[0]->datapoints as $point) {
            self::assertGreaterThanOrEqual(0.0, $point->value);
            // At most 2 decimal places — round($value, 2).
            self::assertSame($point->value, round($point->value, 2));
        }
    }

    #[Test]
    public function emptyMetricListEmitsEmptyResultsArray(): void
    {
        $provider = new MonitoringProvider(
            id: 'empty',
            label: 'Empty',
            bridge: 'mock',
            settings: new ProviderSettings(),
            metrics: [],
        );

        self::assertSame([], $this->provider->query($provider, $this->range(3600)));
    }

    #[Test]
    public function datapointStatMatchesMetricStat(): void
    {
        $metric = new MetricDefinition(id: 'cpu', label: 'CPU', stat: 'Maximum', periodSeconds: 300);
        $provider = $this->providerWithMetrics($metric);

        $results = $this->provider->query($provider, $this->range(3600));

        foreach ($results[0]->datapoints as $point) {
            self::assertInstanceOf(MetricPoint::class, $point);
            self::assertSame('Maximum', $point->stat);
        }
    }
}
