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
use WPPack\Component\Option\OptionManager;

final class MonitoringStore implements MonitoringProviderInterface
{
    private const OPTION_NAME = 'wppack_monitoring_providers';
    private const MASKED_VALUE = '********';

    /** @var array<string, MetricProviderInterface> */
    private array $bridgeMap = [];

    /**
     * @param iterable<MetricProviderInterface> $bridges
     */
    public function __construct(
        private readonly OptionManager $options,
        iterable $bridges = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
        foreach ($bridges as $bridge) {
            $this->bridgeMap[$bridge->getName()] = $bridge;
        }
    }

    /**
     * @return list<MonitoringProvider>
     */
    public function getProviders(): array
    {
        $raw = $this->options->get(self::OPTION_NAME, []);

        if (!\is_array($raw)) {
            return [];
        }

        $providers = [];

        foreach ($raw as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $providers[] = $this->hydrateProvider($entry);
        }

        return $providers;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveProvider(array $data): void
    {
        $all = $this->loadRaw();
        $id = (string) ($data['id'] ?? '');

        if ($id === '') {
            return;
        }

        $existing = null;
        foreach ($all as $entry) {
            if (($entry['id'] ?? '') === $id) {
                $existing = $entry;
                break;
            }
        }

        // Preserve settings from existing provider when not provided or masked
        if ($existing !== null) {
            $existingSettings = $existing['settings'] ?? [];
            if (!\is_array($existingSettings)) {
                $existingSettings = [];
            }

            if (!isset($data['settings']) || !\is_array($data['settings']) || $data['settings'] === []) {
                // Settings not provided — keep existing entirely
                $data['settings'] = $existingSettings;
            } else {
                // Settings provided — restore masked/empty sensitive fields
                $settings = $data['settings'];
                $bridge = (string) ($data['bridge'] ?? '');
                $settingsClass = self::resolveSettingsClass($bridge);
                foreach ($settingsClass::sensitiveFields() as $field) {
                    $value = $settings[$field] ?? '';
                    if ($value === '' || $value === self::MASKED_VALUE) {
                        $settings[$field] = $existingSettings[$field] ?? '';
                    }
                }
                $data['settings'] = $settings;
            }
        }

        $updated = [];
        $found = false;

        foreach ($all as $entry) {
            if (($entry['id'] ?? '') === $id) {
                $updated[] = $data;
                $found = true;
            } else {
                $updated[] = $entry;
            }
        }

        if (!$found) {
            $updated[] = $data;
        }

        $this->options->update(self::OPTION_NAME, $updated);
    }

    public function deleteProvider(string $id): void
    {
        $all = $this->loadRaw();
        $filtered = array_values(array_filter(
            $all,
            static fn(array $entry): bool => ($entry['id'] ?? '') !== $id,
        ));

        $this->options->update(self::OPTION_NAME, $filtered);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveMetric(string $providerId, array $data): void
    {
        $all = $this->loadRaw();
        $metricId = (string) ($data['id'] ?? '');

        if ($metricId === '') {
            return;
        }

        foreach ($all as &$entry) {
            if (($entry['id'] ?? '') !== $providerId) {
                continue;
            }

            $metrics = $entry['metrics'] ?? [];
            if (!\is_array($metrics)) {
                $metrics = [];
            }

            $updated = [];
            $found = false;

            foreach ($metrics as $m) {
                if (($m['id'] ?? '') === $metricId) {
                    $updated[] = $data;
                    $found = true;
                } else {
                    $updated[] = $m;
                }
            }

            if (!$found) {
                $updated[] = $data;
            }

            $entry['metrics'] = $updated;
            break;
        }

        $this->options->update(self::OPTION_NAME, $all);
    }

    public function deleteMetric(string $providerId, string $metricId): void
    {
        $all = $this->loadRaw();

        foreach ($all as &$entry) {
            if (($entry['id'] ?? '') !== $providerId) {
                continue;
            }

            $metrics = $entry['metrics'] ?? [];
            if (!\is_array($metrics)) {
                continue;
            }

            $entry['metrics'] = array_values(array_filter(
                $metrics,
                static fn(array $m): bool => ($m['id'] ?? '') !== $metricId,
            ));

            break;
        }

        $this->options->update(self::OPTION_NAME, $all);
    }

    /**
     * Sync a provider's metrics with a template definition.
     * Only updates metrics — settings are never touched.
     *
     * @param list<array{metricName: string, label: string, description: string, namespace: string, unit: string, stat: string, periodSeconds?: int, extraDimensions?: array<string, string>}> $templateMetrics
     *
     * @return bool True if metrics were updated
     */
    public function syncMetrics(string $providerId, array $templateMetrics): bool
    {
        $all = $this->loadRaw();

        foreach ($all as &$entry) {
            if (($entry['id'] ?? '') !== $providerId) {
                continue;
            }

            $existingMetrics = $entry['metrics'] ?? [];
            if (!\is_array($existingMetrics)) {
                $existingMetrics = [];
            }

            // Index existing metrics by metricName for lookup
            $existingByName = [];
            foreach ($existingMetrics as $m) {
                if (isset($m['metricName'])) {
                    $existingByName[$m['metricName']] = $m;
                }
            }

            // Get existing dimensions from first metric (shared across all metrics)
            $existingDimensions = [];
            if ($existingMetrics !== []) {
                $existingDimensions = $existingMetrics[0]['dimensions'] ?? [];
                if (!\is_array($existingDimensions)) {
                    $existingDimensions = [];
                }
            }

            // Build current and template metricName lists for comparison
            $currentNames = array_map(fn(array $m): string => (string) ($m['metricName'] ?? ''), $existingMetrics);
            $templateNames = array_map(fn(array $m): string => $m['metricName'], $templateMetrics);
            sort($currentNames);
            sort($templateNames);

            // Check if any label or description differs
            $labelsMatch = true;
            foreach ($templateMetrics as $tmpl) {
                $existing = $existingByName[$tmpl['metricName']] ?? null;
                if ($existing !== null && (
                    ($existing['label'] ?? '') !== $tmpl['label']
                    || ($existing['description'] ?? '') !== $tmpl['description']
                )) {
                    $labelsMatch = false;
                    break;
                }
            }

            if ($currentNames === $templateNames && $labelsMatch) {
                return false; // No change needed
            }

            // Build synced metrics
            $synced = [];
            foreach ($templateMetrics as $tmpl) {
                $existing = $existingByName[$tmpl['metricName']] ?? null;
                $extraDims = $tmpl['extraDimensions'] ?? [];

                $synced[] = [
                    'id' => $existing['id'] ?? $providerId . '.' . strtolower($tmpl['metricName']),
                    'label' => $tmpl['label'],
                    'description' => $tmpl['description'],
                    'namespace' => $tmpl['namespace'],
                    'metricName' => $tmpl['metricName'],
                    'unit' => $tmpl['unit'],
                    'stat' => $tmpl['stat'],
                    'dimensions' => array_merge($existingDimensions, $extraDims),
                    'periodSeconds' => $tmpl['periodSeconds'] ?? $existing['periodSeconds'] ?? 300,
                    'locked' => $existing['locked'] ?? false,
                ];
            }

            $entry['metrics'] = $synced;
            $this->options->update(self::OPTION_NAME, $all);

            $this->logger?->info('Synced metrics for provider "{id}": {added} added, {removed} removed.', [
                'id' => $providerId,
                'added' => \count(array_diff($templateNames, $currentNames)),
                'removed' => \count(array_diff($currentNames, $templateNames)),
            ]);

            return true;
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRaw(): array
    {
        $raw = $this->options->get(self::OPTION_NAME, []);

        if (!\is_array($raw)) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $raw;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function hydrateProvider(array $entry): MonitoringProvider
    {
        $settings = $entry['settings'] ?? [];
        if (!\is_array($settings)) {
            $settings = [];
        }

        $metrics = [];
        $rawMetrics = $entry['metrics'] ?? [];
        if (\is_array($rawMetrics)) {
            foreach ($rawMetrics as $m) {
                if (!\is_array($m)) {
                    continue;
                }
                $metrics[] = $this->hydrateMetric($m);
            }
        }

        $bridge = (string) ($entry['bridge'] ?? '');
        $settingsClass = self::resolveSettingsClass($bridge);

        return new MonitoringProvider(
            id: (string) ($entry['id'] ?? ''),
            label: (string) ($entry['label'] ?? ''),
            bridge: $bridge,
            settings: $settingsClass::fromArray($settings),
            metrics: $metrics,
            locked: (bool) ($entry['locked'] ?? false),
            templateId: isset($entry['templateId']) && \is_string($entry['templateId']) ? $entry['templateId'] : null,
        );
    }

    /**
     * @return class-string<ProviderSettings>
     */
    private function resolveSettingsClass(string $bridge): string
    {
        if (isset($this->bridgeMap[$bridge])) {
            return $this->bridgeMap[$bridge]->getSettingsClass();
        }

        return ProviderSettings::class;
    }

    /**
     * @param array<string, mixed> $m
     */
    private function hydrateMetric(array $m): MetricDefinition
    {
        $dimensions = $m['dimensions'] ?? [];
        if (!\is_array($dimensions)) {
            $dimensions = [];
        }

        return new MetricDefinition(
            id: (string) ($m['id'] ?? ''),
            label: (string) ($m['label'] ?? ''),
            description: (string) ($m['description'] ?? ''),
            namespace: (string) ($m['namespace'] ?? ''),
            metricName: (string) ($m['metricName'] ?? ''),
            unit: (string) ($m['unit'] ?? 'Count'),
            stat: (string) ($m['stat'] ?? 'Average'),
            dimensions: array_map(strval(...), $dimensions),
            periodSeconds: (int) ($m['periodSeconds'] ?? 300),
            locked: (bool) ($m['locked'] ?? false),
        );
    }
}
