<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapter;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapterFactory;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisClusterAdapter;
use WpPack\Component\Cache\Exception\UnsupportedSchemeException;

final class RedisAdapterFactoryTest extends TestCase
{
    private RedisAdapterFactory $factory;

    protected function setUp(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $this->factory = new RedisAdapterFactory();
    }

    #[Test]
    public function supportsRedisSchemes(): void
    {
        self::assertTrue($this->factory->supports(Dsn::fromString('redis://localhost')));
        self::assertTrue($this->factory->supports(Dsn::fromString('rediss://localhost')));
        self::assertTrue($this->factory->supports(Dsn::fromString('valkey://localhost')));
        self::assertTrue($this->factory->supports(Dsn::fromString('valkeys://localhost')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        self::assertFalse($this->factory->supports(Dsn::fromString('memcached://localhost')));
    }

    #[Test]
    public function createsRedisAdapterForStandalone(): void
    {
        $adapter = $this->factory->create(Dsn::fromString('redis://127.0.0.1:6379'));

        self::assertInstanceOf(RedisAdapter::class, $adapter);
        self::assertSame('redis', $adapter->getName());
    }

    #[Test]
    public function createsRedisClusterAdapterForCluster(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1'),
        );

        self::assertInstanceOf(RedisClusterAdapter::class, $adapter);
        self::assertSame('redis-cluster', $adapter->getName());
    }

    #[Test]
    public function throwsForUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);

        $this->factory->create(Dsn::fromString('memcached://localhost'));
    }

    #[Test]
    public function passesOptionsToAdapter(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['timeout' => 5, 'read_timeout' => 3],
        );

        self::assertInstanceOf(RedisAdapter::class, $adapter);
    }
}
