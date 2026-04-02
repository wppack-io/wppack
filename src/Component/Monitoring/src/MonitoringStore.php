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

use WpPack\Component\Option\OptionManager;

final class MonitoringStore implements MonitoringProviderInterface
{
    private const OPTION_NAME = 'wppack_monitoring_providers';
    private const MASKED_VALUE = '********';

    public function __construct(
        private readonly OptionManager $options,
    ) {}

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

        // Preserve sensitive values: restore from existing when masked or missing
        if ($existing !== null) {
            $settings = $data['settings'] ?? [];
            if (!\is_array($settings) || $settings === []) {
                $settings = [];
            }
            $existingSettings = $existing['settings'] ?? [];
            if (\is_array($existingSettings)) {
                $bridge = (string) ($data['bridge'] ?? '');
                $settingsClass = self::resolveSettingsClass($bridge);
                foreach ($settingsClass::sensitiveFields() as $field) {
                    $value = $settings[$field] ?? '';
                    if ($value === '' || $value === self::MASKED_VALUE) {
                        $settings[$field] = $existingSettings[$field] ?? '';
                    }
                }
                // Preserve non-sensitive fields that are missing from the update
                foreach ($existingSettings as $key => $existingValue) {
                    if (!isset($settings[$key]) || $settings[$key] === '') {
                        $settings[$key] = $existingValue;
                    }
                }
            }
            $data['settings'] = $settings;
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
        );
    }

    /**
     * @return class-string<ProviderSettings>
     */
    private static function resolveSettingsClass(string $bridge): string
    {
        return match ($bridge) {
            'cloudwatch', 'mock-aws' => AwsProviderSettings::class,
            'cloudflare', 'mock-cloudflare' => CloudflareProviderSettings::class,
            default => AwsProviderSettings::class,
        };
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
