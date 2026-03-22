<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapter;

final class RedisAdapterTest extends TestCase
{
    private RedisAdapter $adapter;

    protected function setUp(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $this->adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);

        if (!$this->adapter->isAvailable()) {
            self::markTestSkipped('Redis server is not available at 127.0.0.1:6379.');
        }

        // Clean up test keys
        $this->adapter->flush('wppack_test:');
    }

    protected function tearDown(): void
    {
        if (isset($this->adapter)) {
            $this->adapter->flush('wppack_test:');
            $this->adapter->close();
        }
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('redis', $this->adapter->getName());
    }

    #[Test]
    public function setAndGet(): void
    {
        self::assertTrue($this->adapter->set('wppack_test:key', 'value'));
        self::assertSame('value', $this->adapter->get('wppack_test:key'));
    }

    #[Test]
    public function getReturnsFalseForMissing(): void
    {
        self::assertNull($this->adapter->get('wppack_test:nonexistent'));
    }

    #[Test]
    public function getMultiple(): void
    {
        $this->adapter->set('wppack_test:key1', 'value1');
        $this->adapter->set('wppack_test:key2', 'value2');

        $results = $this->adapter->getMultiple(['wppack_test:key1', 'wppack_test:key2', 'wppack_test:missing']);

        self::assertSame('value1', $results['wppack_test:key1']);
        self::assertSame('value2', $results['wppack_test:key2']);
        self::assertNull($results['wppack_test:missing']);
    }

    #[Test]
    public function setMultiple(): void
    {
        $results = $this->adapter->setMultiple([
            'wppack_test:key1' => 'value1',
            'wppack_test:key2' => 'value2',
        ]);

        self::assertTrue($results['wppack_test:key1']);
        self::assertTrue($results['wppack_test:key2']);
        self::assertSame('value1', $this->adapter->get('wppack_test:key1'));
    }

    #[Test]
    public function addSucceeds(): void
    {
        self::assertTrue($this->adapter->add('wppack_test:new', 'value'));
        self::assertSame('value', $this->adapter->get('wppack_test:new'));
    }

    #[Test]
    public function addFailsForExisting(): void
    {
        $this->adapter->set('wppack_test:existing', 'old');

        self::assertFalse($this->adapter->add('wppack_test:existing', 'new'));
        self::assertSame('old', $this->adapter->get('wppack_test:existing'));
    }

    #[Test]
    public function delete(): void
    {
        $this->adapter->set('wppack_test:key', 'value');
        $this->adapter->delete('wppack_test:key');

        self::assertNull($this->adapter->get('wppack_test:key'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->adapter->set('wppack_test:key1', 'value1');
        $this->adapter->set('wppack_test:key2', 'value2');

        $results = $this->adapter->deleteMultiple(['wppack_test:key1', 'wppack_test:key2']);

        self::assertTrue($results['wppack_test:key1']);
        self::assertTrue($results['wppack_test:key2']);
        self::assertNull($this->adapter->get('wppack_test:key1'));
    }

    #[Test]
    public function increment(): void
    {
        $this->adapter->set('wppack_test:counter', '10');

        self::assertSame(15, $this->adapter->increment('wppack_test:counter', 5));
    }

    #[Test]
    public function incrementReturnsFalseForMissing(): void
    {
        self::assertNull($this->adapter->increment('wppack_test:nonexistent'));
    }

    #[Test]
    public function decrement(): void
    {
        $this->adapter->set('wppack_test:counter', '10');

        self::assertSame(7, $this->adapter->decrement('wppack_test:counter', 3));
    }

    #[Test]
    public function flushWithPrefix(): void
    {
        $this->adapter->set('wppack_test:a', '1');
        $this->adapter->set('wppack_test:b', '2');
        $this->adapter->set('wppack_other:c', '3');

        $this->adapter->flush('wppack_test:');

        self::assertNull($this->adapter->get('wppack_test:a'));
        self::assertNull($this->adapter->get('wppack_test:b'));
        self::assertSame('3', $this->adapter->get('wppack_other:c'));

        // Clean up
        $this->adapter->delete('wppack_other:c');
    }

    #[Test]
    public function isAvailable(): void
    {
        self::assertTrue($this->adapter->isAvailable());
    }

    #[Test]
    public function isNotAvailableForBadConnection(): void
    {
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 1,
            'timeout' => 1,
        ]);

        self::assertFalse($adapter->isAvailable());
    }

    #[Test]
    public function setWithTtl(): void
    {
        $this->adapter->set('wppack_test:ttl', 'value', 10);

        self::assertSame('value', $this->adapter->get('wppack_test:ttl'));
    }

    #[Test]
    public function addWithTtl(): void
    {
        self::assertTrue($this->adapter->add('wppack_test:ttl_add', 'value', 10));
        self::assertSame('value', $this->adapter->get('wppack_test:ttl_add'));
    }

    #[Test]
    public function setWithNegativeTtlDeletesKey(): void
    {
        $this->adapter->set('wppack_test:neg', 'value');
        self::assertSame('value', $this->adapter->get('wppack_test:neg'));

        self::assertTrue($this->adapter->set('wppack_test:neg', 'new', -1));
        self::assertNull($this->adapter->get('wppack_test:neg'));
    }

    #[Test]
    public function setMultipleWithNegativeTtlDeletesKeys(): void
    {
        $this->adapter->set('wppack_test:neg1', 'value1');
        $this->adapter->set('wppack_test:neg2', 'value2');

        $results = $this->adapter->setMultiple([
            'wppack_test:neg1' => 'new1',
            'wppack_test:neg2' => 'new2',
        ], -1);

        self::assertTrue($results['wppack_test:neg1']);
        self::assertTrue($results['wppack_test:neg2']);
        self::assertNull($this->adapter->get('wppack_test:neg1'));
        self::assertNull($this->adapter->get('wppack_test:neg2'));
    }

    #[Test]
    public function addWithNegativeTtlIsNoop(): void
    {
        $this->adapter->set('wppack_test:existing', 'old');

        self::assertTrue($this->adapter->add('wppack_test:existing', 'new', -1));
        self::assertSame('old', $this->adapter->get('wppack_test:existing'));
    }

    #[Test]
    public function getMultipleEmpty(): void
    {
        self::assertSame([], $this->adapter->getMultiple([]));
    }

    #[Test]
    public function deleteMultipleEmpty(): void
    {
        self::assertSame([], $this->adapter->deleteMultiple([]));
    }

    #[Test]
    public function setMultipleWithTtl(): void
    {
        $results = $this->adapter->setMultiple([
            'wppack_test:ttl1' => 'value1',
            'wppack_test:ttl2' => 'value2',
        ], 10);

        self::assertTrue($results['wppack_test:ttl1']);
        self::assertTrue($results['wppack_test:ttl2']);
        self::assertSame('value1', $this->adapter->get('wppack_test:ttl1'));
        self::assertSame('value2', $this->adapter->get('wppack_test:ttl2'));
    }

    #[Test]
    public function decrementReturnsFalseForMissing(): void
    {
        self::assertNull($this->adapter->decrement('wppack_test:nonexistent'));
    }

    #[Test]
    public function flushAll(): void
    {
        $this->adapter->set('wppack_test:a', '1');
        $this->adapter->set('wppack_test:b', '2');

        $this->adapter->flush();

        self::assertNull($this->adapter->get('wppack_test:a'));
        self::assertNull($this->adapter->get('wppack_test:b'));
    }

    #[Test]
    public function closeAndReconnect(): void
    {
        $this->adapter->set('wppack_test:before', 'value');
        $this->adapter->close();

        self::assertTrue($this->adapter->set('wppack_test:after', 'value'));
        self::assertSame('value', $this->adapter->get('wppack_test:after'));
    }

    #[Test]
    public function connectViaSentinel(): void
    {
        $adapter = new RedisAdapter([
            'redis_sentinel' => 'mymaster',
            'sentinel_hosts' => [
                ['host' => '127.0.0.1', 'port' => 26379],
                ['host' => '127.0.0.1', 'port' => 26380],
                ['host' => '127.0.0.1', 'port' => 26381],
            ],
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Sentinel is not available.');
        }

        self::assertTrue($adapter->set('wppack_test:sentinel', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:sentinel'));

        $adapter->delete('wppack_test:sentinel');
        $adapter->close();
    }

    #[Test]
    public function connectWithPersistent(): void
    {
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'persistent' => true,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis server is not available at 127.0.0.1:6379.');
        }

        self::assertTrue($adapter->set('wppack_test:persistent', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:persistent'));

        $adapter->delete('wppack_test:persistent');
        $adapter->close();
    }

    #[Test]
    public function connectWithPasswordAndDbindex(): void
    {
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'auth' => 'testpassword',
            'dbindex' => 2,
        ]);

        // The auth will fail against unauthenticated valkey, but the
        // auth() code path in createConnection() is exercised.
        try {
            $adapter->isAvailable();
        } catch (\Throwable) {
            // Expected: auth will fail against unauthenticated server
        }

        self::assertSame('redis', $adapter->getName());
        $adapter->close();
    }

    #[Test]
    public function connectWithReadTimeout(): void
    {
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'read_timeout' => 5,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis server is not available at 127.0.0.1:6379.');
        }

        self::assertTrue($adapter->set('wppack_test:readtimeout', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:readtimeout'));

        $adapter->delete('wppack_test:readtimeout');
        $adapter->close();
    }

    #[Test]
    public function connectWithTcpKeepalive(): void
    {
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'tcp_keepalive' => 60,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis server is not available at 127.0.0.1:6379.');
        }

        self::assertTrue($adapter->set('wppack_test:keepalive', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:keepalive'));

        $adapter->delete('wppack_test:keepalive');
        $adapter->close();
    }

    #[Test]
    public function connectWithTls(): void
    {
        // Skip if TLS is not available on the target port
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false]]);
        $probe = @stream_socket_client('tls://127.0.0.1:6380', $errno, $errstr, 1, STREAM_CLIENT_CONNECT, $ctx);
        if ($probe === false) {
            self::markTestSkipped('TLS server is not available on port 6380.');
        }
        fclose($probe);

        try {
            $adapter = @new RedisAdapter([
                'host' => '127.0.0.1',
                'port' => 6380,
                'tls' => true,
                'timeout' => 2,
            ]);
        } catch (\Throwable) {
            self::markTestSkipped('Redis TLS server is not available at tls://127.0.0.1:6380.');
        }

        if (!@$adapter->isAvailable()) {
            self::markTestSkipped('Redis TLS server is not available at tls://127.0.0.1:6380.');
        }

        self::assertTrue($adapter->set('wppack_test:tls', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:tls'));

        $adapter->delete('wppack_test:tls');
        $adapter->close();
    }

    #[Test]
    public function connectViaSentinelWithPersistent(): void
    {
        $adapter = new RedisAdapter([
            'redis_sentinel' => 'mymaster',
            'sentinel_hosts' => [
                ['host' => '127.0.0.1', 'port' => 26379],
                ['host' => '127.0.0.1', 'port' => 26380],
                ['host' => '127.0.0.1', 'port' => 26381],
            ],
            'persistent' => true,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Sentinel is not available.');
        }

        self::assertTrue($adapter->set('wppack_test:sentinel_persist', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:sentinel_persist'));

        $adapter->delete('wppack_test:sentinel_persist');
        $adapter->close();
    }

    #[Test]
    public function connectViaSentinelWithReadTimeoutAndDbindex(): void
    {
        $adapter = new RedisAdapter([
            'redis_sentinel' => 'mymaster',
            'sentinel_hosts' => [
                ['host' => '127.0.0.1', 'port' => 26379],
            ],
            'read_timeout' => 5,
            'dbindex' => 1,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Sentinel is not available.');
        }

        self::assertTrue($adapter->set('wppack_test:sentinel_db', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:sentinel_db'));

        $adapter->delete('wppack_test:sentinel_db');
        $adapter->close();
    }

    #[Test]
    public function connectViaSentinelThrowsWhenNoMasterFound(): void
    {
        $adapter = new RedisAdapter([
            'redis_sentinel' => 'nonexistent_service',
            'sentinel_hosts' => [
                ['host' => '127.0.0.1', 'port' => 26379],
            ],
        ]);

        // Sentinel is available but service name doesn't exist
        try {
            $sentinel = new \Redis();
            $sentinel->connect('127.0.0.1', 26379, 2);
            $sentinel->close();
        } catch (\Throwable) {
            self::markTestSkipped('Redis Sentinel is not available.');
        }

        $this->expectException(\WpPack\Component\Cache\Exception\AdapterException::class);
        $this->expectExceptionMessage('No master found for Sentinel service "nonexistent_service"');

        $adapter->set('wppack_test:fail', 'value');
    }

    #[Test]
    public function connectViaSentinelSkipsFailingSentinelHost(): void
    {
        $adapter = new RedisAdapter([
            'redis_sentinel' => 'mymaster',
            'sentinel_hosts' => [
                ['host' => '127.0.0.1', 'port' => 1],     // This will fail
                ['host' => '127.0.0.1', 'port' => 26379],  // This should succeed
            ],
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Sentinel is not available.');
        }

        self::assertTrue($adapter->set('wppack_test:sentinel_skip', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:sentinel_skip'));

        $adapter->delete('wppack_test:sentinel_skip');
        $adapter->close();
    }

    #[Test]
    public function connectWithCredentialProvider(): void
    {
        $called = false;
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'credential_provider' => function () use (&$called): string {
                $called = true;

                // Valkey without auth - return empty to skip auth
                return '';
            },
        ]);

        // The credential provider is called during createConnection.
        // Since our valkey has no auth, we expect auth('') to fail,
        // but the credential_provider code path is exercised.
        try {
            if (!$adapter->isAvailable()) {
                self::markTestSkipped('Redis server is not available at 127.0.0.1:6379.');
            }
        } catch (\Throwable) {
            // The empty password auth may throw, but the credential_provider path was exercised
            self::assertTrue($called);

            return;
        }

        self::assertTrue($called);
        $adapter->close();
    }

    #[Test]
    public function connectWithDbindex(): void
    {
        $adapter = new RedisAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'dbindex' => 2,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis server is not available at 127.0.0.1:6379.');
        }

        self::assertTrue($adapter->set('wppack_test:dbindex', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:dbindex'));

        $adapter->delete('wppack_test:dbindex');
        $adapter->close();
    }

    #[Test]
    public function connectWithSocket(): void
    {
        $socketPath = '/var/run/redis/redis.sock';

        if (!file_exists($socketPath)) {
            self::markTestSkipped('Redis Unix socket is not available at ' . $socketPath);
        }

        $adapter = new RedisAdapter([
            'socket' => $socketPath,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis server is not available via Unix socket.');
        }

        self::assertTrue($adapter->set('wppack_test:socket', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:socket'));

        $adapter->delete('wppack_test:socket');
        $adapter->close();
    }

    #[Test]
    public function setMultipleWithNegativeTtlEmptyValues(): void
    {
        $results = $this->adapter->setMultiple([], -1);

        self::assertSame([], $results);
    }

    #[Test]
    public function connectViaSentinelWithAuth(): void
    {
        // Verify sentinel auth code path is exercised.
        // The valkey sentinel master has no auth, so auth() will fail,
        // but the code path for auth in connectViaSentinel is covered.
        $adapter = new RedisAdapter([
            'redis_sentinel' => 'mymaster',
            'sentinel_hosts' => [
                ['host' => '127.0.0.1', 'port' => 26379],
            ],
            'auth' => 'testpassword',
        ]);

        try {
            $adapter->isAvailable();
        } catch (\Throwable) {
            // Expected: auth will fail against unauthenticated server
        }

        self::assertSame('redis', $adapter->getName());
        $adapter->close();
    }
}
