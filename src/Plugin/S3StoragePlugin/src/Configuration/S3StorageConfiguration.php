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

namespace WpPack\Plugin\S3StoragePlugin\Configuration;

use WpPack\Component\Media\Storage\StorageConfiguration;

final readonly class S3StorageConfiguration
{
    public const OPTION_NAME = 'wppack_storage';

    public const MASKED_VALUE = '********';

    public function __construct(
        public string $bucket,
        public string $region,
        public string $prefix = 'uploads',
        public ?string $cdnUrl = null,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            bucket: self::requireEnv('S3_BUCKET'),
            region: self::getEnv('S3_REGION') ?? self::getEnv('AWS_REGION') ?? 'us-east-1',
            prefix: self::getEnv('S3_PREFIX') ?? 'uploads',
            cdnUrl: self::getEnv('CDN_URL'),
        );
    }

    public static function fromEnvironmentOrOptions(): self
    {
        // Constants take precedence
        if (self::hasConstantConfiguration()) {
            return self::fromEnvironment();
        }

        // Environment variables
        $envBucket = self::getEnvVar('S3_BUCKET');
        if ($envBucket !== null) {
            return self::fromEnvironment();
        }

        // wp_options: read primary storage
        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];
        $primary = $saved['primary'] ?? 'media';
        $storages = $saved['storages'] ?? [];

        if (isset($storages[$primary]) && \is_array($storages[$primary])) {
            $storage = $storages[$primary];
            $fields = $storage['fields'] ?? [];

            if (($storage['provider'] ?? '') === 's3' && isset($fields['bucket']) && $fields['bucket'] !== '') {
                return new self(
                    bucket: (string) $fields['bucket'],
                    region: (string) ($fields['region'] ?? 'us-east-1'),
                    prefix: (string) ($storage['prefix'] ?? 'uploads'),
                    cdnUrl: isset($storage['cdnUrl']) && $storage['cdnUrl'] !== '' ? (string) $storage['cdnUrl'] : null,
                );
            }
        }

        throw new \RuntimeException('S3 storage is not configured. Define S3_BUCKET constant, set environment variable, or configure via Storage Settings.');
    }

    /**
     * Check if any storage configuration exists (constant, env, or wp_options).
     */
    public static function hasConfiguration(): bool
    {
        if (self::hasConstantConfiguration()) {
            return true;
        }

        if (self::getEnvVar('S3_BUCKET') !== null) {
            return true;
        }

        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];
        $primary = $saved['primary'] ?? 'media';
        $storages = $saved['storages'] ?? [];

        if (isset($storages[$primary]) && \is_array($storages[$primary])) {
            $storage = $storages[$primary];
            $fields = $storage['fields'] ?? [];

            return ($storage['provider'] ?? '') !== '' && isset($fields['bucket']) && $fields['bucket'] !== '';
        }

        return false;
    }

    public function toStorageConfiguration(): StorageConfiguration
    {
        return new StorageConfiguration(
            protocol: 's3',
            bucket: $this->bucket,
            prefix: $this->prefix,
            cdnUrl: $this->cdnUrl,
        );
    }

    private static function hasConstantConfiguration(): bool
    {
        if (\defined('S3_BUCKET')) {
            $value = \constant('S3_BUCKET');

            return \is_string($value) && $value !== '';
        }

        return false;
    }

    private static function getEnvVar(string $name): ?string
    {
        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }

    private static function requireEnv(string $name): string
    {
        $value = self::getEnv($name);

        if ($value === null) {
            throw new \RuntimeException(sprintf('Required environment variable "%s" is not set.', $name));
        }

        return $value;
    }

    private static function getEnv(string $name): ?string
    {
        if (\defined($name)) {
            $value = \constant($name);

            return \is_string($value) && $value !== '' ? $value : null;
        }

        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
