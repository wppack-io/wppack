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

final readonly class MetricPoint
{
    public function __construct(
        public \DateTimeImmutable $timestamp,
        public float $value,
        public string $stat = 'Average',
    ) {}
}
