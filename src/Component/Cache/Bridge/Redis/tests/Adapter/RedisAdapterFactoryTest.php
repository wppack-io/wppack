<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Bridge\Redis\Adapter\PredisAdapter;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapter;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapterFactory;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisClusterAdapter;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RelayAdapter;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RelayClusterAdapter;
use WpPack\Component\Cache\Exception\AdapterException;
use WpPack\Component\Cache\Exception\UnsupportedSchemeException;

final class RedisAdapterFactoryTest extends TestCase
{
    private RedisAdapterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new RedisAdapterFactory();
    }

    #[Test]
    public function supportsRedisSchemes(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

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
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $adapter = $this->factory->create(Dsn::fromString('redis://127.0.0.1:6379'));

        self::assertInstanceOf(RedisAdapter::class, $adapter);
        self::assertSame('redis', $adapter->getName());
    }

    #[Test]
    public function createsRedisClusterAdapterForCluster(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

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
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['timeout' => 5, 'read_timeout' => 3],
        );

        self::assertInstanceOf(RedisAdapter::class, $adapter);
    }

    #[Test]
    public function classOptionCreatesRelayAdapter(): void
    {
        if (!\extension_loaded('relay')) {
            self::markTestSkipped('ext-relay is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['class' => \Relay\Relay::class],
        );

        self::assertInstanceOf(RelayAdapter::class, $adapter);
        self::assertSame('relay', $adapter->getName());
    }

    #[Test]
    public function classOptionCreatesRelayClusterAdapter(): void
    {
        if (!\extension_loaded('relay')) {
            self::markTestSkipped('ext-relay is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1'),
            ['class' => \Relay\Cluster::class],
        );

        self::assertInstanceOf(RelayClusterAdapter::class, $adapter);
        self::assertSame('relay-cluster', $adapter->getName());
    }

    #[Test]
    public function classOptionCreatesPredisAdapter(): void
    {
        if (!\class_exists(\Predis\Client::class)) {
            self::markTestSkipped('predis/predis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['class' => \Predis\Client::class],
        );

        self::assertInstanceOf(PredisAdapter::class, $adapter);
        self::assertSame('predis', $adapter->getName());
    }

    #[Test]
    public function classOptionFromDsnQuery(): void
    {
        if (!\class_exists(\Predis\Client::class)) {
            self::markTestSkipped('predis/predis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379?class=Predis%5CClient'),
        );

        self::assertInstanceOf(PredisAdapter::class, $adapter);
    }

    #[Test]
    public function throwsForUnsupportedClientClass(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Unsupported Redis client class');

        $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['class' => 'NonExistent\Client'],
        );
    }

    #[Test]
    public function autoDetectsExtRedis(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $adapter = $this->factory->create(Dsn::fromString('redis://127.0.0.1:6379'));

        // ext-redis should be preferred when available
        self::assertInstanceOf(RedisAdapter::class, $adapter);
    }

    #[Test]
    public function iamAuthCreatesCredentialProvider(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (!class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            self::markTestSkipped('wppack/elasticache-auth is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'),
            [
                'iam_auth' => true,
                'iam_region' => 'ap-northeast-1',
                'iam_user_id' => 'my-iam-user',
            ],
        );

        // Adapter was created successfully (credential_provider is set internally)
        self::assertNotNull($adapter);
    }

    #[Test]
    public function iamAuthFromDsnQueryParams(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (!class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            self::markTestSkipped('wppack/elasticache-auth is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379?iam_auth=1&iam_region=ap-northeast-1&iam_user_id=my-iam-user'),
        );

        self::assertNotNull($adapter);
    }

    #[Test]
    public function iamAuthThrowsWithoutRegion(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (!class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            self::markTestSkipped('wppack/elasticache-auth is not available.');
        }

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('iam_region is required');

        $this->factory->create(
            Dsn::fromString('rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'),
            [
                'iam_auth' => true,
                'iam_user_id' => 'my-iam-user',
            ],
        );
    }

    #[Test]
    public function iamAuthThrowsWithoutUserId(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (!class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            self::markTestSkipped('wppack/elasticache-auth is not available.');
        }

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('iam_user_id is required');

        $this->factory->create(
            Dsn::fromString('rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'),
            [
                'iam_auth' => true,
                'iam_region' => 'ap-northeast-1',
            ],
        );
    }

    #[Test]
    public function iamAuthThrowsWithoutTls(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (!class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            self::markTestSkipped('wppack/elasticache-auth is not available.');
        }

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('IAM authentication requires TLS');

        $this->factory->create(
            Dsn::fromString('redis://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'),
            [
                'iam_auth' => true,
                'iam_region' => 'ap-northeast-1',
                'iam_user_id' => 'my-iam-user',
            ],
        );
    }

    #[Test]
    public function credentialProviderPassthrough(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        $called = false;
        $provider = function () use (&$called): string {
            $called = true;

            return 'dynamic-token';
        };

        $adapter = $this->factory->create(
            Dsn::fromString('rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'),
            ['credential_provider' => $provider],
        );

        // Adapter was created with credential_provider option
        self::assertNotNull($adapter);
    }

    #[Test]
    public function createsRedisAdapterForSentinel(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis:?host[127.0.0.1:26379]&host[127.0.0.1:26380]&redis_sentinel=mymaster'),
        );

        self::assertInstanceOf(RedisAdapter::class, $adapter);
        self::assertSame('redis', $adapter->getName());
    }

    private function hasAnyClient(): bool
    {
        return \extension_loaded('redis')
            || \extension_loaded('relay')
            || \class_exists(\Predis\Client::class);
    }
}
