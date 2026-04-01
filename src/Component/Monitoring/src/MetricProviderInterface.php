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

interface MetricProviderInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    /**
     * @param list<MetricSource> $sources
     * @return list<MetricResult>
     */
    public function query(array $sources, MetricTimeRange $range): array;
}
