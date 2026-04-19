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

interface CollectableMetricProviderInterface extends MetricProviderInterface
{
    public function collect(MonitoringProvider $provider): void;

    public function getCollectInterval(): int;
}
