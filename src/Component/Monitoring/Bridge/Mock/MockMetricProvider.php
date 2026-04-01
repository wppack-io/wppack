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

namespace WpPack\Component\Monitoring\Bridge\Mock;

use WpPack\Component\Monitoring\MetricPoint;
use WpPack\Component\Monitoring\MetricProviderInterface;
use WpPack\Component\Monitoring\MetricResult;
use WpPack\Component\Monitoring\MetricTimeRange;
use WpPack\Component\Monitoring\MonitoringProvider;

/**
 * Mock metric provider that generates random data for development/testing.
 */
class MockMetricProvider implements MetricProviderInterface
{
    public function getName(): string
    {
        return 'mock';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function query(MonitoringProvider $provider, MetricTimeRange $range): array
    {
        $results = [];
        $now = new \DateTimeImmutable();

        foreach ($provider->metrics as $metric) {
            $datapoints = [];
            $intervalSeconds = $metric->periodSeconds > 0 ? $metric->periodSeconds : 300;
            $start = $range->start->getTimestamp();
            $end = $range->end->getTimestamp();

            $base = $this->baseValueForUnit($metric->unit);

            for ($t = $start; $t <= $end; $t += $intervalSeconds) {
                $variation = $base * 0.3;
                $value = $base + (mt_rand() / mt_getrandmax() * $variation * 2) - $variation;
                $datapoints[] = new MetricPoint(
                    timestamp: (new \DateTimeImmutable())->setTimestamp($t),
                    value: max(0.0, round($value, 2)),
                    stat: $metric->stat,
                );
            }

            $results[] = new MetricResult(
                sourceId: $metric->id,
                label: $metric->label,
                unit: $metric->unit,
                group: $provider->id,
                datapoints: $datapoints,
                fetchedAt: $now,
            );
        }

        return $results;
    }

    private function baseValueForUnit(string $unit): float
    {
        return match ($unit) {
            'Percent' => 35.0,
            'Bytes' => 1_073_741_824.0,
            'Seconds' => 0.005,
            default => 1200.0,
        };
    }
}
