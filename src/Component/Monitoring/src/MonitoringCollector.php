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

use Psr\Log\LoggerInterface;
use WPPack\Component\Transient\TransientManager;

final class MonitoringCollector
{
    private const CACHE_KEY = 'wppack_monitoring_metrics';

    /**
     * @param array<string, MetricProviderInterface> $bridges keyed by bridge name
     */
    public function __construct(
        private readonly MonitoringRegistry $registry,
        private readonly array $bridges,
        private readonly TransientManager $transients,
        private readonly int $cacheTtl = 300,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return list<MetricResult>
     */
    public function query(MetricTimeRange $range, bool $forceRefresh = false): array
    {
        // Round end timestamp to cacheTtl boundary so cache hits within the TTL window
        $roundedEnd = intdiv($range->end->getTimestamp(), $this->cacheTtl) * $this->cacheTtl;
        $rangeSeconds = $range->end->getTimestamp() - $range->start->getTimestamp();
        $cacheKey = self::CACHE_KEY . '_' . md5(serialize([
            $roundedEnd,
            $rangeSeconds,
            $range->periodSeconds,
        ]));

        if (!$forceRefresh) {
            $cached = $this->transients->get($cacheKey);
            if (\is_array($cached)) {
                $this->logger?->debug('Metrics cache hit for key "{key}".', ['key' => $cacheKey]);

                /** @var list<MetricResult> */
                return $cached;
            }
        }

        $results = [];
        $providerCount = 0;

        foreach ($this->registry->all() as $provider) {
            $bridge = $this->bridges[$provider->bridge] ?? null;
            if ($bridge === null || !$bridge->isAvailable()) {
                $this->logger?->warning('Bridge "{bridge}" not available for provider "{id}", skipping.', [
                    'bridge' => $provider->bridge,
                    'id' => $provider->id,
                ]);

                continue;
            }

            try {
                $results = [...$results, ...$bridge->query($provider, $range)];
                $providerCount++;
            } catch (\Throwable $e) {
                $this->logger?->error('Metric query failed for provider "{id}": {error}', [
                    'id' => $provider->id,
                    'bridge' => $provider->bridge,
                    'error' => $e->getMessage(),
                ]);

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

        $this->logger?->debug('Fetched {count} metric results for {providers} providers.', [
            'count' => \count($results),
            'providers' => $providerCount,
        ]);

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

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getBridgeMetadata(): array
    {
        $metadata = [];

        foreach ($this->bridges as $name => $bridge) {
            $formFields = $bridge->getFormFields();
            $metadata[$name] = [
                'name' => $name,
                'label' => $bridge->getLabel(),
                'formFields' => $formFields,
                'credentialFieldIds' => array_column($formFields, 'id'),
                'templates' => $bridge->getTemplates(),
                'dimensionLabels' => $bridge->getDimensionLabels(),
                'defaultSettings' => $bridge->getDefaultSettings(),
                'setupGuide' => $bridge->getSetupGuide(),
                'requiredFields' => array_values(array_filter(
                    array_column($formFields, 'id'),
                    static fn(string $id): bool => !str_contains($id, 'accessKeyId') && !str_contains($id, 'secretAccessKey'),
                )),
            ];
        }

        return $metadata;
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

        if (str_contains($message, 'Cloudflare API error')) {
            return $message;
        }

        return 'Metric query failed. Check provider configuration.';
    }
}
