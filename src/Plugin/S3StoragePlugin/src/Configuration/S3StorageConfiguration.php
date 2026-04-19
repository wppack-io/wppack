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

namespace WPPack\Plugin\S3StoragePlugin\Configuration;

use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Dsn\Exception\InvalidDsnException;
use WPPack\Component\Media\Storage\StorageConfiguration;

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
        try {
            $parsed = Dsn::fromString($dsn);
        } catch (InvalidDsnException) {
            throw new \InvalidArgumentException('Invalid DSN format. Expected: s3://[accessKey:secretKey@]bucket?region=...');
        }

        $bucket = $parsed->getHost();
        if ($bucket === null) {
            throw new \InvalidArgumentException('Invalid DSN format. Expected: s3://[accessKey:secretKey@]bucket?region=...');
        }

        return [
            'scheme' => $parsed->getScheme(),
            'bucket' => $bucket,
            'region' => $parsed->getOption('region', 'us-east-1') ?? 'us-east-1',
            'accessKeyId' => $parsed->getUser() ?? '',
            'secretAccessKey' => $parsed->getPassword() ?? '',
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
        try {
            $parsed = Dsn::fromString($dsn);
        } catch (InvalidDsnException) {
            return $dsn;
        }

        $host = $parsed->getHost();
        if ($host === null) {
            return $dsn;
        }

        $masked = $parsed->getScheme() . '://';

        $user = $parsed->getUser();
        if ($user !== null && $user !== '') {
            $masked .= self::MASKED_VALUE;
            $password = $parsed->getPassword();
            if ($password !== null && $password !== '') {
                $masked .= ':' . self::MASKED_VALUE;
            }
            $masked .= '@';
        }

        $masked .= $host;

        $options = $parsed->getOptions();
        if ($options !== []) {
            $pairs = [];
            foreach ($options as $key => $value) {
                if (\is_array($value)) {
                    foreach ($value as $v) {
                        $pairs[] = rawurlencode((string) $key) . '[]=' . rawurlencode($v);
                    }
                } else {
                    $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode($value);
                }
            }
            $masked .= '?' . implode('&', $pairs);
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
