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

namespace WpPack\Plugin\MonitoringPlugin\Template;

final readonly class MetricTemplate
{
    /**
     * @param list<array{metricName: string, label: string, description: string, namespace: string, unit: string, stat: string, periodSeconds?: int, extraDimensions?: array<string, string>}> $metrics
     */
    public function __construct(
        public string $id,
        public string $bridge,
        public string $namespace,
        public string $dimensionKey,
        public array $metrics,
    ) {}
}
