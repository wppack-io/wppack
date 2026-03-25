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

    public function toStorageConfiguration(): StorageConfiguration
    {
        return new StorageConfiguration(
            protocol: 's3',
            bucket: $this->bucket,
            prefix: $this->prefix,
            cdnUrl: $this->cdnUrl,
        );
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
        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
