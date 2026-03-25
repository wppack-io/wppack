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

    #[Test]
    public function createsAdapterWithMultiHostDefaultPort(): void
    {
        // Multi-host DSN without explicit port — defaults to 11211
        $adapter = $this->factory->create(
            Dsn::fromString('memcached:?host[10.0.0.1]&host[10.0.0.2]'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithUnixSocketDsn(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached:///var/run/memcached.sock'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithSaslAuthFromOptions(): void
    {
        if (!method_exists(\Memcached::class, 'setSaslAuthData')) {
            self::markTestSkipped('ext-memcached was not compiled with SASL support.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211'),
            ['username' => 'user', 'password' => 'secret'],
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithBooleanOptionsFromDsn(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211?tcp_nodelay=1&no_block=0'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithBooleanOptionsFromArray(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211'),
            [
                'tcp_nodelay' => true,
                'no_block' => false,
                'binary_protocol' => true,
                'libketama_compatible' => true,
            ],
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithTimeoutFromDsn(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211?timeout=5000&retry_timeout=10'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithPersistentIdFromDsn(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211?persistent_id=my_pool'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithWeightOption(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211?weight=10'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithMultiHostAndWeight(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]&weight=5'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithHostFromOptions(): void
    {
        // DSN with no host, host provided via options
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://127.0.0.1:11211'),
            ['host' => '10.0.0.1', 'port' => 11212],
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }

    #[Test]
    public function createsAdapterWithDefaultHostAndPort(): void
    {
        // DSN with just a path (Unix socket style) but host is present
        $adapter = $this->factory->create(
            Dsn::fromString('memcached://localhost'),
        );

        self::assertInstanceOf(MemcachedAdapter::class, $adapter);
    }
}
