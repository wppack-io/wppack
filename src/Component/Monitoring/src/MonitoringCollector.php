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
     * @param array<string, MetricProviderInterface> $bridges keyed by bridge name
     */
    public function __construct(
        private readonly MonitoringRegistry $registry,
        private readonly array $bridges,
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

        foreach ($this->registry->all() as $provider) {
            $bridge = $this->bridges[$provider->bridge] ?? null;
            if ($bridge === null || !$bridge->isAvailable()) {
                continue;
            }

            try {
                $results = [...$results, ...$bridge->query($provider, $range)];
            } catch (\Throwable $e) {
                $errorMessage = $this->formatError($e);
                foreach ($provider->metrics as $metric) {
                    $results[] = new MetricResult(
                        sourceId: $metric->id,
                        label: $metric->label,
                        unit: $metric->unit,
                        group: $provider->id,
                        error: $errorMessage,
                    );
                }
            }
        }

        $this->transients->set($cacheKey, $results, $this->cacheTtl);

        return $results;
    }

    /**
     * Run collect() on all CollectableMetricProviderInterface bridges.
     */
    public function runCollectors(): void
    {
        foreach ($this->registry->all() as $provider) {
            $bridge = $this->bridges[$provider->bridge] ?? null;
            if (!$bridge instanceof CollectableMetricProviderInterface) {
                continue;
            }

            if (!$bridge->isAvailable()) {
                continue;
            }

            if ($provider->metrics !== []) {
                $bridge->collect($provider);
            }
        }
    }

    private function formatError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'MissingAuthenticationToken') || str_contains($message, 'InvalidSignature')) {
            return 'AWS authentication failed. Check credentials.';
        }

        if (str_contains($message, 'AccessDenied') || str_contains($message, 'UnauthorizedAccess')) {
            return 'Access denied. Check IAM permissions.';
        }

        if (str_contains($message, 'cURL error') || str_contains($message, 'Could not resolve host')) {
            return 'Connection failed. Check network.';
        }

        return mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '…' : $message;
    }

    public function invalidate(): void
    {
        $this->transients->delete(self::CACHE_KEY);
    }
}
