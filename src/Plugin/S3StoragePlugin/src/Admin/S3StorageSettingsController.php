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

namespace WpPack\Plugin\S3StoragePlugin\Admin;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Storage\Adapter\LocalStorageAdapterFactory;
use WpPack\Component\Storage\Bridge\Azure\AzureStorageAdapterFactory;
use WpPack\Component\Storage\Bridge\Gcs\GcsStorageAdapterFactory;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapterFactory;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

#[RestRoute(namespace: 'wppack/v1/storage')]
#[IsGranted('manage_options')]
final class S3StorageSettingsController extends AbstractRestController
{
    /**
     * Provider definitions for the settings UI.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getProviderDefinitions(): array
    {
        $definitions = [];

        if (class_exists(S3StorageAdapterFactory::class)) {
            $definitions['s3'] = [
                'label' => 'Amazon S3',
                'scheme' => 's3',
                'fields' => [
                    ['name' => 'bucket', 'label' => 'Bucket'],
                    ['name' => 'region', 'label' => 'Region'],
                    ['name' => 'accessKey', 'label' => 'Access Key', 'sensitive' => true],
                    ['name' => 'secretKey', 'label' => 'Secret Key', 'sensitive' => true],
                ],
            ];
        }

        if (class_exists(AzureStorageAdapterFactory::class)) {
            $definitions['azure'] = [
                'label' => 'Azure Blob Storage',
                'scheme' => 'azure',
                'fields' => [
                    ['name' => 'account', 'label' => 'Account'],
                    ['name' => 'container', 'label' => 'Container'],
                    ['name' => 'accountKey', 'label' => 'Account Key', 'sensitive' => true],
                    ['name' => 'connectionString', 'label' => 'Connection String', 'sensitive' => true],
                ],
            ];
        }

        if (class_exists(GcsStorageAdapterFactory::class)) {
            $definitions['gcs'] = [
                'label' => 'Google Cloud Storage',
                'scheme' => 'gcs',
                'fields' => [
                    ['name' => 'bucket', 'label' => 'Bucket'],
                    ['name' => 'project', 'label' => 'Project'],
                    ['name' => 'keyFile', 'label' => 'Key File', 'sensitive' => true],
                ],
            ];
        }

        if (class_exists(LocalStorageAdapterFactory::class)) {
            $definitions['local'] = [
                'label' => 'Local Filesystem',
                'scheme' => 'local',
                'fields' => [
                    ['name' => 'rootDir', 'label' => 'Root Directory'],
                ],
            ];
        }

        return $definitions;
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        return $this->json($this->buildResponse());
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        return $this->json($this->buildResponse());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $raw = get_option(S3StorageConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $storages = $saved['storages'] ?? [];
        $primary = $saved['primary'] ?? '';
        $uploadsPath = $saved['uploadsPath'] ?? 'wp-content/uploads';

        // Source detection
        $source = 'default';
        $constantStorage = null;

        $constantDsn = $this->getConstantDsn();
        if ($constantDsn !== null) {
            $source = 'constant';
            $constantStorage = $this->buildConstantStorage($constantDsn);
            $constantUri = $constantStorage['uri'];

            // Inject constant storage and override primary
            $storages[$constantUri] = $constantStorage;
            if ($primary === '') {
                $primary = $constantUri;
            }

            // Read uploads path from constant/env if available
            $constUploadsPath = $this->getConstantUploadsPath();
            if ($constUploadsPath !== null) {
                $uploadsPath = $constUploadsPath;
            }
        } elseif (!empty($storages)) {
            $source = 'option';
        }

        // Mask sensitive fields in storages
        $maskedStorages = $this->maskStorages($storages);

        return [
            'storages' => $maskedStorages,
            'primary' => $primary,
            'uploadsPath' => $uploadsPath,
            'source' => $source,
            'definitions' => $this->getProviderDefinitions(),
        ];
    }

    /**
     * Get DSN from STORAGE_DSN constant or environment variable.
     */
    private function getConstantDsn(): ?string
    {
        if (\defined('STORAGE_DSN')) {
            $value = \constant('STORAGE_DSN');

            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->getEnvValue('STORAGE_DSN');
    }

    /**
     * Get uploads path from WPPACK_STORAGE_UPLOADS_PATH constant or environment variable.
     */
    private function getConstantUploadsPath(): ?string
    {
        if (\defined('WPPACK_STORAGE_UPLOADS_PATH')) {
            $value = \constant('WPPACK_STORAGE_UPLOADS_PATH');

            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->getEnvValue('WPPACK_STORAGE_UPLOADS_PATH');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConstantStorage(string $dsn): array
    {
        $parts = S3StorageConfiguration::parseDsn($dsn);
        $uri = S3StorageConfiguration::buildUri($parts['bucket']);

        return [
            'dsn' => $dsn,
            'cdnUrl' => null,
            'readonly' => true,
            'uri' => $uri,
        ];
    }

    /**
     * @param array<string, mixed> $storages
     * @return array<string, mixed>
     */
    private function maskStorages(array $storages): array
    {
        $masked = [];

        foreach ($storages as $uri => $storage) {
            $dsn = $storage['dsn'] ?? '';
            $maskedDsn = $dsn !== '' ? S3StorageConfiguration::maskDsn($dsn) : '';

            $masked[$uri] = [
                'dsn' => $maskedDsn,
                'cdnUrl' => $storage['cdnUrl'] ?? null,
                'readonly' => $storage['readonly'] ?? false,
                'uri' => $storage['uri'] ?? (string) $uri,
            ];
        }

        return $masked;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        $raw = get_option(S3StorageConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];
        $existingStorages = $saved['storages'] ?? [];

        $inputStorages = isset($input['storages']) && \is_array($input['storages']) ? $input['storages'] : [];
        $inputPrimary = isset($input['primary']) && \is_string($input['primary']) ? $input['primary'] : '';
        $inputUploadsPath = isset($input['uploadsPath']) && \is_string($input['uploadsPath']) ? $input['uploadsPath'] : 'wp-content/uploads';

        $newStorages = [];

        foreach ($inputStorages as $uri => $storage) {
            if (!\is_string($uri) || !\is_array($storage)) {
                continue;
            }

            // Skip constant-defined storages (they are readonly)
            if (isset($storage['readonly']) && $storage['readonly']) {
                continue;
            }

            $dsn = isset($storage['dsn']) && \is_string($storage['dsn']) ? $storage['dsn'] : '';
            $cdnUrl = isset($storage['cdnUrl']) && \is_string($storage['cdnUrl']) ? $storage['cdnUrl'] : '';

            // Restore masked DSN from existing saved data
            if ($dsn !== '' && $this->isMaskedDsn($dsn) && isset($existingStorages[$uri])) {
                $dsn = $existingStorages[$uri]['dsn'] ?? '';
            }

            // Build URI from DSN if possible
            $storageUri = (string) $uri;
            if ($dsn !== '' && !$this->isMaskedDsn($dsn)) {
                try {
                    $parts = S3StorageConfiguration::parseDsn($dsn);
                    $storageUri = S3StorageConfiguration::buildUri($parts['bucket']);
                } catch (\InvalidArgumentException) {
                    // Keep original URI if DSN is invalid
                }
            }

            $newStorages[$storageUri] = [
                'dsn' => $dsn,
                'cdnUrl' => $cdnUrl !== '' ? $cdnUrl : null,
                'readonly' => false,
                'uri' => $storageUri,
            ];
        }

        // Update primary if its URI changed due to DSN re-parsing
        $newPrimary = $inputPrimary;

        $saved['storages'] = $newStorages;
        $saved['primary'] = $newPrimary;
        $saved['uploadsPath'] = $inputUploadsPath;

        update_option(S3StorageConfiguration::OPTION_NAME, $saved);
    }

    /**
     * Check if a DSN string contains masked values.
     */
    private function isMaskedDsn(string $dsn): bool
    {
        return str_contains($dsn, S3StorageConfiguration::MASKED_VALUE);
    }

    private function getEnvValue(string $name): ?string
    {
        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
