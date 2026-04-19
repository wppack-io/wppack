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

namespace WPPack\Component\Monitoring;

final readonly class MetricResult
{
    /**
     * @param list<MetricPoint> $datapoints
     */
    public function __construct(
        public string $sourceId,
        public string $label,
        public string $unit,
        public string $group,
        public array $datapoints = [],
        public ?\DateTimeImmutable $fetchedAt = null,
        public ?string $error = null,
    ) {}
}
