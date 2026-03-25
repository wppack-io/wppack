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

namespace WpPack\Plugin\RedisCachePlugin\Configuration;

final readonly class RedisCacheConfiguration
{
    public function __construct(
        public string $dsn,
        public string $prefix = 'wp:',
        public ?int $maxTtl = null,
        public bool $hashAlloptions = false,
        public bool $asyncFlush = false,
        public string $compression = 'none',
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            dsn: self::requireEnv('WPPACK_CACHE_DSN'),
            prefix: self::getEnv('WPPACK_CACHE_PREFIX') ?? 'wp:',
            maxTtl: ($v = self::getEnv('WPPACK_CACHE_MAX_TTL')) !== null ? (int) $v : null,
            hashAlloptions: self::getBool('WPPACK_CACHE_HASH_ALLOPTIONS'),
            asyncFlush: self::getBool('WPPACK_CACHE_ASYNC_FLUSH'),
            compression: self::getEnv('WPPACK_CACHE_COMPRESSION') ?? 'none',
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
        if (defined($name)) {
            $value = constant($name);

            return \is_string($value) && $value !== '' ? $value : null;
        }

        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }

    private static function getBool(string $name): bool
    {
        if (defined($name)) {
            return (bool) constant($name);
        }

        $value = $_ENV[$name] ?? false;

        if ($value === false) {
            $value = getenv($name);
        }

        return $value !== false && \in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
