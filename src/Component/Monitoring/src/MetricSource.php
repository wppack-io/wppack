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

namespace WpPack\Component\Monitoring;

final readonly class MetricSource
{
    /**
     * @param array<string, string> $dimensions
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $provider,
        public string $namespace,
        public string $metricName,
        public string $unit = 'Count',
        public string $stat = 'Average',
        public array $dimensions = [],
        public int $periodSeconds = 300,
        public string $group = 'default',
    ) {}
}
