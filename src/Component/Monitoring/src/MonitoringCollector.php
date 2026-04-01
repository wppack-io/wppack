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

use WpPack\Component\Transient\TransientManager;

class MonitoringCollector
{
    private const string CACHE_KEY = 'wppack_monitoring_metrics';

    /**
     * @param array<string, MetricProviderInterface> $providers
     */
    public function __construct(
        private readonly MonitoringRegistry $registry,
        private readonly array $providers,
        private readonly TransientManager $transients,
        private readonly int $cacheTtl = 300,
    ) {}

    /**
     * @return list<MetricResult>
     */
    public function query(MetricTimeRange $range, bool $forceRefresh = false): array
    {
        $cacheKey = self::CACHE_KEY . '_' . md5(serialize([
            $range->start->getTimestamp(),
            $range->end->getTimestamp(),
            $range->periodSeconds,
        ]));

        if (!$forceRefresh) {
            $cached = $this->transients->get($cacheKey);
            if (\is_array($cached)) {
                /** @var list<MetricResult> */
                return $cached;
            }
        }

        $results = [];

        foreach ($this->registry->providers() as $providerName) {
            $provider = $this->providers[$providerName] ?? null;
            if ($provider === null || !$provider->isAvailable()) {
                continue;
            }

            $sources = $this->registry->byProvider($providerName);
            if ($sources === []) {
                continue;
            }

            try {
                $results = [...$results, ...$provider->query($sources, $range)];
            } catch (\Throwable $e) {
                foreach ($sources as $source) {
                    $results[] = new MetricResult(
                        sourceId: $source->id,
                        label: $source->label,
                        unit: $source->unit,
                        group: $source->group,
                        error: $e->getMessage(),
                    );
                }
            }
        }

        $this->transients->set($cacheKey, $results, $this->cacheTtl);

        return $results;
    }

    /**
     * Run collect() on all CollectableMetricProviderInterface providers.
     */
    public function runCollectors(): void
    {
        foreach ($this->registry->providers() as $providerName) {
            $provider = $this->providers[$providerName] ?? null;
            if (!$provider instanceof CollectableMetricProviderInterface) {
                continue;
            }

            if (!$provider->isAvailable()) {
                continue;
            }

            $sources = $this->registry->byProvider($providerName);
            if ($sources !== []) {
                $provider->collect($sources);
            }
        }
    }

    public function invalidate(): void
    {
        $this->transients->delete(self::CACHE_KEY);
    }
}
