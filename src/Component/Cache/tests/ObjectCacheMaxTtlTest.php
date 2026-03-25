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

namespace WpPack\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\Cache\ObjectCacheConfig;
use WpPack\Component\Cache\Tests\Adapter\InMemoryAdapter;

final class ObjectCacheMaxTtlTest extends TestCase
{
    public int $lastTtl = 0;

    private function createSpyAdapter(): AdapterInterface
    {
        $test = $this;
        $inner = new InMemoryAdapter();

        return new class ($inner, $test) implements AdapterInterface {
            public function __construct(
                private readonly InMemoryAdapter $inner,
                private readonly ObjectCacheMaxTtlTest $test,
            ) {}

            public function getName(): string
            {
                return $this->inner->getName();
            }

            public function get(string $key): ?string
            {
                return $this->inner->get($key);
            }

            public function getMultiple(array $keys): array
            {
                return $this->inner->getMultiple($keys);
            }

            public function set(string $key, string $value, int $ttl = 0): bool
            {
                $this->test->lastTtl = $ttl;

                return $this->inner->set($key, $value, $ttl);
            }

            public function setMultiple(array $values, int $ttl = 0): array
            {
                $this->test->lastTtl = $ttl;

                return $this->inner->setMultiple($values, $ttl);
            }

            public function add(string $key, string $value, int $ttl = 0): bool
            {
                $this->test->lastTtl = $ttl;

                return $this->inner->add($key, $value, $ttl);
            }

            public function delete(string $key): bool
            {
                return $this->inner->delete($key);
            }

            public function deleteMultiple(array $keys): array
            {
                return $this->inner->deleteMultiple($keys);
            }

            public function increment(string $key, int $offset = 1): ?int
            {
                return $this->inner->increment($key, $offset);
            }

            public function decrement(string $key, int $offset = 1): ?int
            {
                return $this->inner->decrement($key, $offset);
            }

            public function flush(string $prefix = ''): bool
            {
                return $this->inner->flush($prefix);
            }

            public function isAvailable(): bool
            {
                return $this->inner->isAvailable();
            }

            public function close(): void
            {
                $this->inner->close();
            }
        };
    }

    #[Test]
    public function maxTtlClampsZeroExpiration(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->set('key', 'value', 'default', 0);

        self::assertSame(3600, $this->lastTtl);
    }

    #[Test]
    public function maxTtlClampsExcessiveExpiration(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->set('key', 'value', 'default', 86400);

        self::assertSame(3600, $this->lastTtl);
    }

    #[Test]
    public function maxTtlDoesNotAffectSmallerTtl(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->set('key', 'value', 'default', 1800);

        self::assertSame(1800, $this->lastTtl);
    }

    #[Test]
    public function maxTtlNullDoesNotClamp(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:'));

        $cache->set('key', 'value', 'default', 0);

        self::assertSame(0, $this->lastTtl);
    }

    #[Test]
    public function maxTtlZeroDoesNotClamp(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 0));

        $cache->set('key', 'value', 'default', 0);

        self::assertSame(0, $this->lastTtl);
    }

    #[Test]
    public function maxTtlClampsSetMultiple(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->setMultiple(['k1' => 'v1', 'k2' => 'v2'], 'default', 0);

        self::assertSame(3600, $this->lastTtl);
    }

    #[Test]
    public function maxTtlClampsAdd(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->add('key', 'value', 'default', 86400);

        self::assertSame(3600, $this->lastTtl);
    }

    #[Test]
    public function maxTtlClampsReplace(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        // First set, then replace
        $cache->set('key', 'old', 'default', 1800);
        $cache->replace('key', 'new', 'default', 86400);

        // replace delegates to set(), so lastTtl should be clamped
        self::assertSame(3600, $this->lastTtl);
    }

    #[Test]
    public function maxTtlDoesNotAffectNegativeTtl(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->set('key', 'value', 'default', -1);

        self::assertSame(-1, $this->lastTtl);
    }

    #[Test]
    public function maxTtlNegativeDoesNotClamp(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: -100));

        $cache->set('key', 'value', 'default', 300);

        self::assertSame(300, $this->lastTtl);
    }

    #[Test]
    public function maxTtlDoesNotAffectEqualTtl(): void
    {
        $adapter = $this->createSpyAdapter();
        $cache = new ObjectCache($adapter, new ObjectCacheConfig(prefix: 'wp:', maxTtl: 3600));

        $cache->set('key', 'value', 'default', 3600);

        self::assertSame(3600, $this->lastTtl);
    }
}
