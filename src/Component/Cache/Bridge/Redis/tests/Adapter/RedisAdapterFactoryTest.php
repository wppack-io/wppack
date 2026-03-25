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

    #[Test]
    public function buildConnectionParamsWithSocketPath(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('redis:///var/run/redis.sock'), []);

        self::assertSame('/var/run/redis.sock', $params['socket']);
        self::assertArrayNotHasKey('host', $params);
    }

    #[Test]
    public function buildConnectionParamsWithAuthFromUser(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('redis://mypassword@localhost'), []);

        self::assertSame('mypassword', $params['auth']);
        self::assertSame('localhost', $params['host']);
    }

    #[Test]
    public function buildConnectionParamsWithDbindexFromPath(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('redis://localhost/3'), []);

        self::assertSame(3, $params['dbindex']);
        self::assertSame('localhost', $params['host']);
    }

    #[Test]
    public function buildConnectionParamsWithSocketFromSockPath(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('redis://localhost/var/run/redis.sock'), []);

        self::assertSame('/var/run/redis.sock', $params['socket']);
    }

    #[Test]
    public function buildConnectionParamsWithAuthFromQueryParam(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('redis://localhost?auth=mypassword'), []);

        self::assertSame('mypassword', $params['auth']);
    }

    #[Test]
    public function buildConnectionParamsWithNumericOptions(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost?timeout=5&read_timeout=3&retry_interval=100&tcp_keepalive=60&dbindex=2&persistent=1'),
            [],
        );

        self::assertSame('5', $params['timeout']);
        self::assertSame('3', $params['read_timeout']);
        self::assertSame('100', $params['retry_interval']);
        self::assertSame('60', $params['tcp_keepalive']);
        self::assertSame('2', $params['dbindex']);
        self::assertSame('1', $params['persistent']);
    }

    #[Test]
    public function buildConnectionParamsWithStringOptions(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost?persistent_id=myid&failover=distribute'),
            [],
        );

        self::assertSame('myid', $params['persistent_id']);
        self::assertSame('distribute', $params['failover']);
    }

    #[Test]
    public function buildConnectionParamsWithSentinelHosts(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis:?host[127.0.0.1:26379]&host[127.0.0.1:26380]&host[10.0.0.1]&redis_sentinel=mymaster'),
            [],
        );

        self::assertSame('mymaster', $params['redis_sentinel']);
        self::assertCount(3, $params['sentinel_hosts']);
        self::assertSame('127.0.0.1', $params['sentinel_hosts'][0]['host']);
        self::assertSame(26379, $params['sentinel_hosts'][0]['port']);
        self::assertSame('127.0.0.1', $params['sentinel_hosts'][1]['host']);
        self::assertSame(26380, $params['sentinel_hosts'][1]['port']);
        // Default sentinel port when not specified
        self::assertSame('10.0.0.1', $params['sentinel_hosts'][2]['host']);
        self::assertSame(26379, $params['sentinel_hosts'][2]['port']);
    }

    #[Test]
    public function buildConnectionParamsWithRedisCluster(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1'),
            [],
        );

        self::assertTrue($params['redis_cluster']);
        self::assertSame(['node1:6379', 'node2:6379'], $params['hosts']);
    }

    #[Test]
    public function buildConnectionParamsRedisClusterFalsyValues(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost?redis_cluster=0'),
            [],
        );

        self::assertArrayNotHasKey('redis_cluster', $params);

        $params2 = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost?redis_cluster=false'),
            [],
        );

        self::assertArrayNotHasKey('redis_cluster', $params2);
    }

    #[Test]
    public function buildConnectionParamsWithTlsScheme(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('rediss://localhost'), []);

        self::assertTrue($params['tls']);

        $params2 = $method->invoke($this->factory, Dsn::fromString('valkeys://localhost'), []);

        self::assertTrue($params2['tls']);
    }

    #[Test]
    public function buildConnectionParamsOptionsOverrideDsn(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost?timeout=5'),
            ['timeout' => 10, 'custom' => 'value'],
        );

        self::assertSame(10, $params['timeout']);
        self::assertSame('value', $params['custom']);
    }

    #[Test]
    public function buildConnectionParamsClassOptionIsNotPassedToParams(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost'),
            ['class' => 'Predis\\Client', 'timeout' => 5],
        );

        self::assertArrayNotHasKey('class', $params);
        self::assertSame(5, $params['timeout']);
    }

    #[Test]
    public function buildConnectionParamsWithIamAuthOptions(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('rediss://localhost?iam_auth=1&iam_region=us-east-1&iam_user_id=myuser'),
            [],
        );

        self::assertSame('1', $params['iam_auth']);
        self::assertSame('us-east-1', $params['iam_region']);
        self::assertSame('myuser', $params['iam_user_id']);
    }

    #[Test]
    public function buildConnectionParamsWithMultiHostAndPort(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost:6380'),
            [],
        );

        self::assertSame('localhost', $params['host']);
        self::assertSame(6380, $params['port']);
    }

    #[Test]
    public function iamAuthFalsyStringDoesNotEnableIam(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        // '0' should not trigger IAM auth
        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('rediss://localhost?iam_auth=0'),
            [],
        );

        self::assertArrayNotHasKey('credential_provider', $params);

        // 'false' should not trigger IAM auth
        $params2 = $method->invoke(
            $this->factory,
            Dsn::fromString('rediss://localhost?iam_auth=false'),
            [],
        );

        self::assertArrayNotHasKey('credential_provider', $params2);
    }

    #[Test]
    public function iamAuthThrowsWithoutHost(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (!class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            self::markTestSkipped('wppack/elasticache-auth is not available.');
        }

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Host is required');

        $this->factory->create(
            Dsn::fromString('rediss:?host[node1:6379]&host[node2:6379]&redis_cluster=1'),
            [
                'iam_auth' => true,
                'iam_region' => 'ap-northeast-1',
                'iam_user_id' => 'my-iam-user',
            ],
        );
    }

    #[Test]
    public function createForClientRedisClass(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['class' => 'Redis'],
        );

        self::assertInstanceOf(RedisAdapter::class, $adapter);
        self::assertSame('redis', $adapter->getName());
    }

    #[Test]
    public function createForClientRedisClusterClass(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis:?host[127.0.0.1:7010]&host[127.0.0.1:7011]&redis_cluster=1'),
            ['class' => 'RedisCluster'],
        );

        self::assertInstanceOf(RedisClusterAdapter::class, $adapter);
        self::assertSame('redis-cluster', $adapter->getName());
    }

    #[Test]
    public function createForClientPredisClientInterface(): void
    {
        if (!\class_exists(\Predis\Client::class)) {
            self::markTestSkipped('predis/predis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['class' => \Predis\ClientInterface::class],
        );

        self::assertInstanceOf(PredisAdapter::class, $adapter);
        self::assertSame('predis', $adapter->getName());
    }

    #[Test]
    public function classOptionFromDsnQueryWithLeadingBackslash(): void
    {
        if (!\class_exists(\Predis\Client::class)) {
            self::markTestSkipped('predis/predis is not available.');
        }

        $adapter = $this->factory->create(
            Dsn::fromString('redis://127.0.0.1:6379'),
            ['class' => '\\Predis\\Client'],
        );

        self::assertInstanceOf(PredisAdapter::class, $adapter);
    }

    #[Test]
    public function buildConnectionParamsParseHostsTrimsTrailingColon(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis:?host[/var/run/redis.sock:]&redis_cluster=1'),
            [],
        );

        self::assertSame(['/var/run/redis.sock'], $params['hosts']);
    }

    #[Test]
    public function buildConnectionParamsWithHostAndNoPort(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('redis://localhost'), []);

        self::assertSame('localhost', $params['host']);
        self::assertArrayNotHasKey('port', $params);
    }

    #[Test]
    public function buildConnectionParamsWithValkeyScheme(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke($this->factory, Dsn::fromString('valkey://localhost:6379'), []);

        self::assertFalse($params['tls']);
        self::assertSame('localhost', $params['host']);
        self::assertSame(6379, $params['port']);
    }

    #[Test]
    public function iamAuthWithoutElastiCachePackageThrows(): void
    {
        if (!$this->hasAnyClient()) {
            self::markTestSkipped('No Redis client library is available.');
        }

        if (class_exists(\WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator::class)) {
            // Cannot test "package not installed" error when it IS installed
            self::markTestSkipped('wppack/elasticache-auth is available - cannot test missing package error.');
        }

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('IAM authentication requires the wppack/elasticache-auth package');

        $this->factory->create(
            Dsn::fromString('rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'),
            [
                'iam_auth' => true,
                'iam_region' => 'ap-northeast-1',
                'iam_user_id' => 'my-iam-user',
            ],
        );
    }

    #[Test]
    public function buildConnectionParamsAuthFromQueryOverridesUserAuth(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://user_auth@localhost?auth=query_auth'),
            [],
        );

        // Auth from query param should override user auth
        self::assertSame('query_auth', $params['auth']);
    }

    #[Test]
    public function buildConnectionParamsEmptyAuthFromUserNotSet(): void
    {
        $method = new \ReflectionMethod(RedisAdapterFactory::class, 'buildConnectionParams');

        $params = $method->invoke(
            $this->factory,
            Dsn::fromString('redis://localhost'),
            [],
        );

        self::assertArrayNotHasKey('auth', $params);
    }

    private function hasAnyClient(): bool
    {
        return \extension_loaded('redis')
            || \extension_loaded('relay')
            || \class_exists(\Predis\Client::class);
    }
}
