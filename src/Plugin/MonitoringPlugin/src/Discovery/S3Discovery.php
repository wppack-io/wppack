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

namespace WpPack\Plugin\MonitoringPlugin\Discovery;

use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;
use WpPack\Component\Monitoring\AwsProviderSettings;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

final class S3Discovery implements MonitoringProviderInterface
{
    /**
     * S3 metrics use a 1-day (86400 seconds) reporting period.
     */
    private const PERIOD_SECONDS = 86400;

    public function getProviders(): array
    {
        $storage = $this->resolveStorage();

        if ($storage === null) {
            return [];
        }

        $dimensions = [
            'BucketName' => $storage['bucket'],
            'StorageType' => 'AllStorageTypes',
        ];

        return [
            new MonitoringProvider(
                id: 's3',
                label: 'S3 Storage',
                bridge: 'cloudwatch',
                settings: new AwsProviderSettings(region: $storage['region']),
                metrics: [
                    new MetricDefinition(
                        id: 's3.bucket_size_bytes',
                        label: 'Bucket Size',
                        description: 'Total bucket size',
                        namespace: 'AWS/S3',
                        metricName: 'BucketSizeBytes',
                        unit: 'Bytes',
                        stat: 'Average',
                        dimensions: $dimensions,
                        periodSeconds: self::PERIOD_SECONDS,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 's3.number_of_objects',
                        label: 'Object Count',
                        description: 'Total number of objects in the bucket',
                        namespace: 'AWS/S3',
                        metricName: 'NumberOfObjects',
                        unit: 'Count',
                        stat: 'Average',
                        dimensions: [
                            'BucketName' => $storage['bucket'],
                            'StorageType' => 'AllStorageTypes',
                        ],
                        periodSeconds: self::PERIOD_SECONDS,
                        locked: true,
                    ),
                ],
                locked: true,
            ),
        ];
    }

    /**
     * Resolve S3 storage configuration.
     *
     * Priority:
     * 1. STORAGE_DSN constant/env (if S3 scheme)
     * 2. wp_options primary storage (if S3 scheme)
     * 3. Legacy S3_BUCKET / WPPACK_S3_BUCKET constants
     *
     * @return array{bucket: string, region: string}|null
     */
    private function resolveStorage(): ?array
    {
        // 1-2. Storage plugin configuration (STORAGE_DSN or wp_options)
        if (class_exists(S3StorageConfiguration::class) && S3StorageConfiguration::hasConfiguration()) {
            try {
                $config = S3StorageConfiguration::fromEnvironmentOrOptions();
                // Only use S3 scheme storages
                $parts = S3StorageConfiguration::parseDsn($config->dsn);
                if ($parts['scheme'] === 's3') {
                    return [
                        'bucket' => $config->bucket,
                        'region' => $config->region,
                    ];
                }
            } catch (\Throwable) {
                // Fall through to legacy resolution
            }
        }

        // 3. Legacy constants
        $bucket = $this->resolveLegacyBucket();

        if ($bucket === '') {
            return null;
        }

        return [
            'bucket' => $bucket,
            'region' => $this->resolveLegacyRegion(),
        ];
    }

    private function resolveLegacyBucket(): string
    {
        if (\defined('S3_BUCKET') && \constant('S3_BUCKET') !== '') {
            return (string) \constant('S3_BUCKET');
        }

        if (\defined('WPPACK_S3_BUCKET') && \constant('WPPACK_S3_BUCKET') !== '') {
            return (string) \constant('WPPACK_S3_BUCKET');
        }

        return $_ENV['S3_BUCKET'] ?? $_ENV['WPPACK_S3_BUCKET'] ?? '';
    }

    private function resolveLegacyRegion(): string
    {
        if (\defined('S3_REGION') && \constant('S3_REGION') !== '') {
            return (string) \constant('S3_REGION');
        }

        if (\defined('WPPACK_S3_REGION') && \constant('WPPACK_S3_REGION') !== '') {
            return (string) \constant('WPPACK_S3_REGION');
        }

        return $_ENV['S3_REGION'] ?? $_ENV['WPPACK_S3_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
    }
}
