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

namespace WPPack\Plugin\RedisCachePlugin\Configuration;

final readonly class RedisCacheConfiguration
{
    public const OPTION_NAME = 'wppack_redis_cache';

    public const MASKED_VALUE = '********';

    public function __construct(
        public string $dsn,
        public string $prefix = 'wp:',
        public ?int $maxTtl = null,
        public bool $hashAlloptions = false,
        public bool $asyncFlush = false,
        public string $compression = 'none',
        public string $serializer = 'none',
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            dsn: self::requireEnv('CACHE_DSN'),
            prefix: self::getEnv('WPPACK_CACHE_PREFIX') ?? 'wp:',
            maxTtl: ($v = self::getEnv('WPPACK_CACHE_MAX_TTL')) !== null ? (int) $v : null,
            hashAlloptions: self::getBool('WPPACK_CACHE_HASH_ALLOPTIONS'),
            asyncFlush: self::getBool('WPPACK_CACHE_ASYNC_FLUSH'),
            compression: self::getEnv('WPPACK_CACHE_COMPRESSION') ?? 'none',
            serializer: self::getEnv('WPPACK_CACHE_REDIS_SERIALIZER') ?? 'none',
        );
    }

    public static function fromEnvironmentOrOptions(): self
    {
        if (\defined('CACHE_DSN')) {
            $value = \constant('CACHE_DSN');

            if (\is_string($value) && $value !== '') {
                return self::buildFromDsn($value);
            }
        }

        $dsn = self::getEnvVar('CACHE_DSN');
        if ($dsn !== null) {
            return self::buildFromDsn($dsn);
        }

        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        if (isset($saved['dsn']) && $saved['dsn'] !== '') {
            return self::buildFromDsn(
                (string) $saved['dsn'],
                $saved['prefix'] ?? null,
                $saved['maxTtl'] ?? null,
                $saved['hashAlloptions'] ?? null,
                $saved['asyncFlush'] ?? null,
                $saved['compression'] ?? null,
                $saved['serializer'] ?? null,
            );
        }

        throw new \RuntimeException('CACHE_DSN is not configured.');
    }

    public static function hasConfiguration(): bool
    {
        if (\defined('CACHE_DSN') && \constant('CACHE_DSN') !== '') {
            return true;
        }

        if (self::getEnvVar('CACHE_DSN') !== null) {
            return true;
        }

        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        return isset($saved['dsn']) && $saved['dsn'] !== '';
    }

    private static function buildFromDsn(
        string $dsn,
        ?string $prefix = null,
        int|string|null $maxTtl = null,
        bool|string|null $hashAlloptions = null,
        bool|string|null $asyncFlush = null,
        ?string $compression = null,
        ?string $serializer = null,
    ): self {
        return new self(
            dsn: $dsn,
            prefix: $prefix ?? self::getEnv('WPPACK_CACHE_PREFIX') ?? 'wp:',
            maxTtl: ($v = ($maxTtl !== null ? (string) $maxTtl : self::getEnv('WPPACK_CACHE_MAX_TTL'))) !== null ? (int) $v : null,
            hashAlloptions: $hashAlloptions !== null ? (bool) $hashAlloptions : self::getBool('WPPACK_CACHE_HASH_ALLOPTIONS'),
            asyncFlush: $asyncFlush !== null ? (bool) $asyncFlush : self::getBool('WPPACK_CACHE_ASYNC_FLUSH'),
            compression: $compression ?? self::getEnv('WPPACK_CACHE_COMPRESSION') ?? 'none',
            serializer: $serializer ?? self::getEnv('WPPACK_CACHE_REDIS_SERIALIZER') ?? 'none',
        );
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
