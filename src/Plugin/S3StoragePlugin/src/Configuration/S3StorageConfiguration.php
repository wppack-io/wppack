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
        #[\SensitiveParameter]
        public string $dsn,
        public string $bucket,
        public string $region,
        public string $uploadsPath = 'wp-content/uploads',
        public ?string $cdnUrl = null,
        #[\SensitiveParameter]
        public ?string $accessKeyId = null,
        #[\SensitiveParameter]
        public ?string $secretAccessKey = null,
    ) {}

    /**
     * Parse a DSN string into its component parts.
     *
     * DSN format: s3://accessKey:secretKey@bucket?region=ap-northeast-1
     *
     * @return array{scheme: string, bucket: string, region: string, accessKeyId: string, secretAccessKey: string}
     */
    public static function parseDsn(#[\SensitiveParameter] string $dsn): array
    {
        $parsed = parse_url($dsn);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException('Invalid DSN format. Expected: s3://[accessKey:secretKey@]bucket?region=...');
        }

        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        return [
            'scheme' => $parsed['scheme'],
            'bucket' => $parsed['host'],
            'region' => isset($query['region']) && $query['region'] !== '' ? (string) $query['region'] : 'us-east-1',
            'accessKeyId' => isset($parsed['user']) ? urldecode($parsed['user']) : '',
            'secretAccessKey' => isset($parsed['pass']) ? urldecode($parsed['pass']) : '',
        ];
    }

    /**
     * Build a configuration from constant, environment variable, or wp_options.
     *
     * Priority:
     * 1. STORAGE_DSN constant or environment variable
     * 2. wp_options primary storage DSN
     */
    public static function fromEnvironmentOrOptions(): self
    {
        // 1. STORAGE_DSN constant or environment variable
        $dsn = self::getEnv('STORAGE_DSN');
        if ($dsn !== null) {
            $uploadsPath = self::getEnv('WPPACK_STORAGE_UPLOADS_PATH') ?? 'wp-content/uploads';
            $parts = self::parseDsn($dsn);

            return new self(
                dsn: $dsn,
                bucket: $parts['bucket'],
                region: $parts['region'],
                uploadsPath: $uploadsPath,
                accessKeyId: $parts['accessKeyId'] !== '' ? $parts['accessKeyId'] : null,
                secretAccessKey: $parts['secretAccessKey'] !== '' ? $parts['secretAccessKey'] : null,
            );
        }

        // 2. wp_options: read primary storage
        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];
        $primaryUri = $saved['primary'] ?? '';
        $uploadsPath = $saved['uploadsPath'] ?? 'wp-content/uploads';
        $storages = $saved['storages'] ?? [];

        if ($primaryUri !== '' && isset($storages[$primaryUri]) && \is_array($storages[$primaryUri])) {
            $storage = $storages[$primaryUri];
            $storageDsn = $storage['dsn'] ?? '';

            if ($storageDsn !== '') {
                $parts = self::parseDsn($storageDsn);

                return new self(
                    dsn: $storageDsn,
                    bucket: $parts['bucket'],
                    region: $parts['region'],
                    uploadsPath: $uploadsPath,
                    cdnUrl: isset($storage['cdnUrl']) && $storage['cdnUrl'] !== '' ? (string) $storage['cdnUrl'] : null,
                    accessKeyId: $parts['accessKeyId'] !== '' ? $parts['accessKeyId'] : null,
                    secretAccessKey: $parts['secretAccessKey'] !== '' ? $parts['secretAccessKey'] : null,
                );
            }
        }

        throw new \RuntimeException('S3 storage is not configured. Define STORAGE_DSN constant, set environment variable, or configure via Storage Settings.');
    }

    /**
     * Check if any storage configuration exists (constant, env, or wp_options).
     */
    public static function hasConfiguration(): bool
    {
        if (self::getEnv('STORAGE_DSN') !== null) {
            return true;
        }

        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];
        $primaryUri = $saved['primary'] ?? '';
        $storages = $saved['storages'] ?? [];

        if ($primaryUri !== '' && isset($storages[$primaryUri]) && \is_array($storages[$primaryUri])) {
            $storage = $storages[$primaryUri];

            return isset($storage['dsn']) && $storage['dsn'] !== '';
        }

        return false;
    }

    public function toStorageConfiguration(): StorageConfiguration
    {
        return new StorageConfiguration(
            protocol: 's3',
            bucket: $this->bucket,
            prefix: $this->uploadsPath,
            cdnUrl: $this->cdnUrl,
        );
    }

    /**
     * Build a URI for display (without credentials).
     *
     * e.g. "s3://my-bucket"
     */
    public static function buildUri(string $bucket): string
    {
        return 's3://' . $bucket;
    }

    /**
     * Mask credentials in a DSN string for API responses.
     */
    public static function maskDsn(#[\SensitiveParameter] string $dsn): string
    {
        $parsed = parse_url($dsn);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return $dsn;
        }

        $masked = $parsed['scheme'] . '://';

        if (isset($parsed['user']) && $parsed['user'] !== '') {
            $masked .= self::MASKED_VALUE;
            if (isset($parsed['pass']) && $parsed['pass'] !== '') {
                $masked .= ':' . self::MASKED_VALUE;
            }
            $masked .= '@';
        }

        $masked .= $parsed['host'];

        if (isset($parsed['query']) && $parsed['query'] !== '') {
            $masked .= '?' . $parsed['query'];
        }

        return $masked;
    }

    /**
     * Read a value from constant or environment variable.
     *
     * Constants take precedence over environment variables.
     */
    private static function getEnv(string $name): ?string
    {
        if (\defined($name)) {
            $value = \constant($name);

            return \is_string($value) && $value !== '' ? $value : null;
        }

        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
