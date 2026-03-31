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
                'provider' => 's3',
                'label' => 'Amazon S3',
                'fields' => [
                    ['name' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'required' => true],
                    ['name' => 'region', 'label' => 'Region', 'type' => 'text', 'required' => false, 'default' => 'us-east-1'],
                    ['name' => 'endpoint', 'label' => 'Endpoint', 'type' => 'text', 'required' => false, 'help' => 'Custom endpoint URL (for S3-compatible services like MinIO).'],
                    ['name' => 'accessKey', 'label' => 'Access Key', 'type' => 'text', 'required' => false, 'help' => 'Leave empty to use IAM role.'],
                    ['name' => 'secretKey', 'label' => 'Secret Key', 'type' => 'password', 'required' => false, 'help' => 'Leave empty to use IAM role.'],
                ],
            ];
        }

        if (class_exists(AzureStorageAdapterFactory::class)) {
            $definitions['azure'] = [
                'provider' => 'azure',
                'label' => 'Azure Blob Storage',
                'fields' => [
                    ['name' => 'account', 'label' => 'Account Name', 'type' => 'text', 'required' => true],
                    ['name' => 'container', 'label' => 'Container', 'type' => 'text', 'required' => true],
                    ['name' => 'accountKey', 'label' => 'Account Key', 'type' => 'password', 'required' => false, 'help' => 'Leave empty to use Managed Identity.'],
                    ['name' => 'connectionString', 'label' => 'Connection String', 'type' => 'password', 'required' => false, 'help' => 'If provided, takes precedence over account name + key.'],
                ],
            ];
        }

        if (class_exists(GcsStorageAdapterFactory::class)) {
            $definitions['gcs'] = [
                'provider' => 'gcs',
                'label' => 'Google Cloud Storage',
                'fields' => [
                    ['name' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'required' => true],
                    ['name' => 'project', 'label' => 'Project ID', 'type' => 'text', 'required' => false],
                    ['name' => 'keyFile', 'label' => 'Key File Path', 'type' => 'text', 'required' => false, 'help' => 'Path to service account JSON key file.'],
                ],
            ];
        }

        if (class_exists(LocalStorageAdapterFactory::class)) {
            $definitions['local'] = [
                'provider' => 'local',
                'label' => 'Local Filesystem',
                'fields' => [
                    ['name' => 'rootDir', 'label' => 'Root Directory', 'type' => 'text', 'required' => true, 'help' => 'Absolute path to the storage directory.'],
                    ['name' => 'publicUrl', 'label' => 'Public URL', 'type' => 'text', 'required' => false, 'help' => 'Base URL for publicly accessible files.'],
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

        // Source detection
        $source = 'default';
        $constantStorage = null;

        if (\defined('S3_BUCKET') && \constant('S3_BUCKET') !== '') {
            $source = 'constant';
            $constantStorage = $this->buildConstantStorage();
        } elseif ($this->hasEnvVar('S3_BUCKET')) {
            $source = 'constant';
            $constantStorage = $this->buildConstantStorage();
        } elseif (!empty($storages)) {
            $source = 'option';
        }

        // If constant-defined, inject as 'media' storage (readonly)
        if ($constantStorage !== null) {
            $storages['media'] = $constantStorage;
            if ($primary === '') {
                $primary = 'media';
            }
        }

        // Mask sensitive fields in storages
        $maskedStorages = $this->maskStorages($storages);

        // Detect AWS region from environment
        $awsRegion = \defined('AWS_DEFAULT_REGION') ? (string) \constant('AWS_DEFAULT_REGION') : '';
        if ($awsRegion === '') {
            $awsRegion = getenv('AWS_DEFAULT_REGION') ?: (getenv('AWS_REGION') ?: '');
        }

        return [
            'definitions' => $this->getProviderDefinitions(),
            'storages' => $maskedStorages,
            'primary' => $primary,
            'source' => $source,
            'awsRegion' => $awsRegion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConstantStorage(): array
    {
        $bucket = \defined('S3_BUCKET') ? (string) \constant('S3_BUCKET') : ($this->getEnvValue('S3_BUCKET') ?? '');
        $region = \defined('S3_REGION') ? (string) \constant('S3_REGION') : ($this->getEnvValue('S3_REGION') ?? $this->getEnvValue('AWS_REGION') ?? 'us-east-1');
        $prefix = \defined('S3_PREFIX') ? (string) \constant('S3_PREFIX') : ($this->getEnvValue('S3_PREFIX') ?? 'uploads');
        $cdnUrl = \defined('CDN_URL') ? (string) \constant('CDN_URL') : ($this->getEnvValue('CDN_URL') ?? '');

        return [
            'provider' => 's3',
            'fields' => [
                'bucket' => $bucket,
                'region' => $region,
            ],
            'prefix' => $prefix,
            'cdnUrl' => $cdnUrl,
            'readonly' => true,
        ];
    }

    /**
     * @param array<string, mixed> $storages
     * @return array<string, mixed>
     */
    private function maskStorages(array $storages): array
    {
        $definitions = $this->getProviderDefinitions();
        $masked = [];

        foreach ($storages as $name => $storage) {
            $provider = $storage['provider'] ?? '';
            $fields = $storage['fields'] ?? [];

            if (isset($definitions[$provider])) {
                foreach ($definitions[$provider]['fields'] as $fieldDef) {
                    if ($fieldDef['type'] === 'password' && isset($fields[$fieldDef['name']]) && $fields[$fieldDef['name']] !== '') {
                        $fields[$fieldDef['name']] = S3StorageConfiguration::MASKED_VALUE;
                    }
                }
            }

            $masked[$name] = [
                'provider' => $provider,
                'fields' => $fields,
                'prefix' => $storage['prefix'] ?? '',
                'cdnUrl' => $storage['cdnUrl'] ?? '',
                'readonly' => $storage['readonly'] ?? false,
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

        $definitions = $this->getProviderDefinitions();
        $newStorages = [];

        foreach ($inputStorages as $name => $storage) {
            if (!\is_string($name) || !\is_array($storage)) {
                continue;
            }

            // Skip constant-defined storages (they are readonly)
            if (isset($storage['readonly']) && $storage['readonly']) {
                continue;
            }

            $provider = isset($storage['provider']) && \is_string($storage['provider']) ? $storage['provider'] : '';
            $fields = isset($storage['fields']) && \is_array($storage['fields']) ? $storage['fields'] : [];
            $prefix = isset($storage['prefix']) && \is_string($storage['prefix']) ? $storage['prefix'] : '';
            $cdnUrl = isset($storage['cdnUrl']) && \is_string($storage['cdnUrl']) ? $storage['cdnUrl'] : '';

            // Restore masked password values from existing saved data
            if (isset($definitions[$provider])) {
                foreach ($definitions[$provider]['fields'] as $fieldDef) {
                    if ($fieldDef['type'] === 'password' && isset($fields[$fieldDef['name']]) && $fields[$fieldDef['name']] === S3StorageConfiguration::MASKED_VALUE) {
                        $fields[$fieldDef['name']] = $existingStorages[$name]['fields'][$fieldDef['name']] ?? '';
                    }
                }
            }

            $newStorages[$name] = [
                'provider' => $provider,
                'fields' => $fields,
                'prefix' => $prefix,
                'cdnUrl' => $cdnUrl,
            ];
        }

        $saved['storages'] = $newStorages;
        $saved['primary'] = $inputPrimary;

        update_option(S3StorageConfiguration::OPTION_NAME, $saved);
    }

    private function hasEnvVar(string $name): bool
    {
        return $this->getEnvValue($name) !== null;
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
