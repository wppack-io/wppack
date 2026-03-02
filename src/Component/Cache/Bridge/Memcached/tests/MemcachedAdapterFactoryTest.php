<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Memcached\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Bridge\Memcached\MemcachedAdapter;
use WpPack\Component\Cache\Bridge\Memcached\MemcachedAdapterFactory;

final class MemcachedAdapterFactoryTest extends TestCase
{
    private MemcachedAdapterFactory $factory;

    protected function setUp(): void
    {
        if (!\extension_loaded('memcached')) {
            self::markTestSkipped('ext-memcached is not available.');
        }

        $this->factory = new MemcachedAdapterFactory();
    }

    #[Test]
    public function supportsMemcachedScheme(): void
    {
        self::assertTrue($this->factory->supports(Dsn::fromString('memcached://localhost')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        self::assertFalse($this->factory->supports(Dsn::fromString('redis://localhost')));
    }

    #[Test]
    public function createsMemcachedAdapter(): void
    {
        $adapter = $this->factory->create(Dsn::fromString('memcached://127.0.0.1:11211'));

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
        self::assertSame('memcached', $adapter->getName());
    }

    #[Test]
    public function createsAdapterWithMultiHostDsn(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithOptions(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211'),
            ['timeout' => 2000, 'retry_timeout' => 5],
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithSaslAuth(): void
    {
        if (!method_exists(\Memcached::class, 'setSaslAuthData')) {
            self::markTestSkipped('ext-memcached was not compiled with SASL support.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('memcached://user:password@127.0.0.1:11211'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithPersistentId(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211'),
            ['persistent_id' => 'my_app'],
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }
}
