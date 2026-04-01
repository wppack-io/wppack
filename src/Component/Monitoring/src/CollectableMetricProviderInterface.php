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

interface CollectableMetricProviderInterface extends MetricProviderInterface
{
    /**
     * @param list<MetricSource> $sources
     */
    public function collect(array $sources): void;

    public function getCollectInterval(): int;
}
