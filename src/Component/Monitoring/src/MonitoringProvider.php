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

final readonly class MonitoringProvider
{
    /**
     * @param list<MetricDefinition> $metrics
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $bridge,
        public ProviderSettings $settings,
        public array $metrics = [],
        public bool $locked = false,
    ) {}
}
