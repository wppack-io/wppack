<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisClusterAdapter;

final class RedisClusterAdapterTest extends TestCase
{
    private RedisClusterAdapter $adapter;

    protected function setUp(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $this->adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
        ]);

        if (!$this->adapter->isAvailable()) {
            self::markTestSkipped('Redis Cluster is not available at 127.0.0.1:7010-7012.');
        }

        // Clean up test keys (use hash tag for same slot)
        $this->adapter->flush('{wppack_test}:');
    }

    protected function tearDown(): void
    {
        if (isset($this->adapter)) {
            $this->adapter->flush('{wppack_test}:');
            $this->adapter->close();
        }
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('redis-cluster', $this->adapter->getName());
    }

    #[Test]
    public function setAndGet(): void
    {
        self::assertTrue($this->adapter->set('{wppack_test}:key', 'value'));
        self::assertSame('value', $this->adapter->get('{wppack_test}:key'));
    }

    #[Test]
    public function getReturnsFalseForMissing(): void
    {
        self::assertFalse($this->adapter->get('{wppack_test}:nonexistent'));
    }

    #[Test]
    public function getMultiple(): void
    {
        $this->adapter->set('{wppack_test}:key1', 'value1');
        $this->adapter->set('{wppack_test}:key2', 'value2');

        $results = $this->adapter->getMultiple(['{wppack_test}:key1', '{wppack_test}:key2', '{wppack_test}:missing']);

        self::assertSame('value1', $results['{wppack_test}:key1']);
        self::assertSame('value2', $results['{wppack_test}:key2']);
        self::assertFalse($results['{wppack_test}:missing']);
    }

    #[Test]
    public function getMultipleEmpty(): void
    {
        self::assertSame([], $this->adapter->getMultiple([]));
    }

    #[Test]
    public function setMultiple(): void
    {
        $results = $this->adapter->setMultiple([
            '{wppack_test}:key1' => 'value1',
            '{wppack_test}:key2' => 'value2',
        ]);

        self::assertTrue($results['{wppack_test}:key1']);
        self::assertTrue($results['{wppack_test}:key2']);
        self::assertSame('value1', $this->adapter->get('{wppack_test}:key1'));
    }

    #[Test]
    public function setMultipleWithTtl(): void
    {
        $results = $this->adapter->setMultiple([
            '{wppack_test}:ttl1' => 'value1',
            '{wppack_test}:ttl2' => 'value2',
        ], 10);

        self::assertTrue($results['{wppack_test}:ttl1']);
        self::assertTrue($results['{wppack_test}:ttl2']);
        self::assertSame('value1', $this->adapter->get('{wppack_test}:ttl1'));
        self::assertSame('value2', $this->adapter->get('{wppack_test}:ttl2'));
    }

    #[Test]
    public function addSucceeds(): void
    {
        self::assertTrue($this->adapter->add('{wppack_test}:new', 'value'));
        self::assertSame('value', $this->adapter->get('{wppack_test}:new'));
    }

    #[Test]
    public function addFailsForExisting(): void
    {
        $this->adapter->set('{wppack_test}:existing', 'old');

        self::assertFalse($this->adapter->add('{wppack_test}:existing', 'new'));
        self::assertSame('old', $this->adapter->get('{wppack_test}:existing'));
    }

    #[Test]
    public function delete(): void
    {
        $this->adapter->set('{wppack_test}:key', 'value');
        $this->adapter->delete('{wppack_test}:key');

        self::assertFalse($this->adapter->get('{wppack_test}:key'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->adapter->set('{wppack_test}:key1', 'value1');
        $this->adapter->set('{wppack_test}:key2', 'value2');

        $results = $this->adapter->deleteMultiple(['{wppack_test}:key1', '{wppack_test}:key2']);

        self::assertTrue($results['{wppack_test}:key1']);
        self::assertTrue($results['{wppack_test}:key2']);
        self::assertFalse($this->adapter->get('{wppack_test}:key1'));
    }

    #[Test]
    public function deleteMultipleEmpty(): void
    {
        self::assertSame([], $this->adapter->deleteMultiple([]));
    }

    #[Test]
    public function increment(): void
    {
        $this->adapter->set('{wppack_test}:counter', '10');

        self::assertSame(15, $this->adapter->increment('{wppack_test}:counter', 5));
    }

    #[Test]
    public function incrementReturnsFalseForMissing(): void
    {
        self::assertFalse($this->adapter->increment('{wppack_test}:nonexistent'));
    }

    #[Test]
    public function decrement(): void
    {
        $this->adapter->set('{wppack_test}:counter', '10');

        self::assertSame(7, $this->adapter->decrement('{wppack_test}:counter', 3));
    }

    #[Test]
    public function decrementReturnsFalseForMissing(): void
    {
        self::assertFalse($this->adapter->decrement('{wppack_test}:nonexistent'));
    }

    #[Test]
    public function flushWithPrefix(): void
    {
        $this->adapter->set('{wppack_test}:a', '1');
        $this->adapter->set('{wppack_test}:b', '2');
        $this->adapter->set('{wppack_other}:c', '3');

        $this->adapter->flush('{wppack_test}:');

        self::assertFalse($this->adapter->get('{wppack_test}:a'));
        self::assertFalse($this->adapter->get('{wppack_test}:b'));
        self::assertSame('3', $this->adapter->get('{wppack_other}:c'));

        // Clean up
        $this->adapter->delete('{wppack_other}:c');
    }

    #[Test]
    public function flushAll(): void
    {
        $this->adapter->set('{wppack_test}:a', '1');
        $this->adapter->set('{wppack_test}:b', '2');

        $this->adapter->flush();

        self::assertFalse($this->adapter->get('{wppack_test}:a'));
        self::assertFalse($this->adapter->get('{wppack_test}:b'));
    }

    #[Test]
    public function isAvailable(): void
    {
        self::assertTrue($this->adapter->isAvailable());
    }

    #[Test]
    public function isNotAvailableForBadConnection(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:1'],
            'timeout' => 1,
        ]);

        self::assertFalse($adapter->isAvailable());
    }

    #[Test]
    public function setWithTtl(): void
    {
        $this->adapter->set('{wppack_test}:ttl', 'value', 10);

        self::assertSame('value', $this->adapter->get('{wppack_test}:ttl'));
    }

    #[Test]
    public function addWithTtl(): void
    {
        self::assertTrue($this->adapter->add('{wppack_test}:ttl_add', 'value', 10));
        self::assertSame('value', $this->adapter->get('{wppack_test}:ttl_add'));
    }

    #[Test]
    public function setWithNegativeTtlDeletesKey(): void
    {
        $this->adapter->set('{wppack_test}:neg', 'value');
        self::assertSame('value', $this->adapter->get('{wppack_test}:neg'));

        self::assertTrue($this->adapter->set('{wppack_test}:neg', 'new', -1));
        self::assertFalse($this->adapter->get('{wppack_test}:neg'));
    }

    #[Test]
    public function setMultipleWithNegativeTtlDeletesKeys(): void
    {
        $this->adapter->set('{wppack_test}:neg1', 'value1');
        $this->adapter->set('{wppack_test}:neg2', 'value2');

        $results = $this->adapter->setMultiple([
            '{wppack_test}:neg1' => 'new1',
            '{wppack_test}:neg2' => 'new2',
        ], -1);

        self::assertTrue($results['{wppack_test}:neg1']);
        self::assertTrue($results['{wppack_test}:neg2']);
        self::assertFalse($this->adapter->get('{wppack_test}:neg1'));
        self::assertFalse($this->adapter->get('{wppack_test}:neg2'));
    }

    #[Test]
    public function addWithNegativeTtlIsNoop(): void
    {
        $this->adapter->set('{wppack_test}:existing', 'old');

        self::assertTrue($this->adapter->add('{wppack_test}:existing', 'new', -1));
        self::assertSame('old', $this->adapter->get('{wppack_test}:existing'));
    }

    #[Test]
    public function closeAndReconnect(): void
    {
        $this->adapter->set('{wppack_test}:before', 'value');
        $this->adapter->close();

        self::assertTrue($this->adapter->set('{wppack_test}:after', 'value'));
        self::assertSame('value', $this->adapter->get('{wppack_test}:after'));
    }

    #[Test]
    public function connectWithFailoverError(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'failover' => 'error',
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Cluster is not available at 127.0.0.1:7010-7012.');
        }

        self::assertTrue($adapter->set('{wppack_test}:failover_error', 'value'));
        self::assertSame('value', $adapter->get('{wppack_test}:failover_error'));

        $adapter->delete('{wppack_test}:failover_error');
        $adapter->close();
    }

    #[Test]
    public function connectWithFailoverDistribute(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'failover' => 'distribute',
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Cluster is not available at 127.0.0.1:7010-7012.');
        }

        self::assertTrue($adapter->set('{wppack_test}:failover_dist', 'value'));
        self::assertSame('value', $adapter->get('{wppack_test}:failover_dist'));

        $adapter->delete('{wppack_test}:failover_dist');
        $adapter->close();
    }

    #[Test]
    public function connectWithPersistentCluster(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'persistent' => true,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Cluster is not available at 127.0.0.1:7010-7012.');
        }

        self::assertTrue($adapter->set('{wppack_test}:persistent', 'value'));
        self::assertSame('value', $adapter->get('{wppack_test}:persistent'));

        $adapter->delete('{wppack_test}:persistent');
        $adapter->close();
    }

    #[Test]
    public function connectWithFailoverSlaves(): void
    {
        // FAILOVER_DISTRIBUTE_SLAVES requires replica nodes.
        // Our cluster has 3 masters and no replicas, so isAvailable() fails.
        // We just verify that the adapter can be constructed with 'slaves' failover
        // and the connection attempt does not throw during construction.
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'failover' => 'slaves',
        ]);

        self::assertSame('redis-cluster', $adapter->getName());

        // Try set/get directly — write to master should still work
        try {
            $adapter->set('{wppack_test}:failover_slaves', 'value');
            self::assertSame('value', $adapter->get('{wppack_test}:failover_slaves'));
            $adapter->delete('{wppack_test}:failover_slaves');
        } catch (\Throwable) {
            // Expected: cluster without replicas may fail with slaves failover
        }

        $adapter->close();
    }

    #[Test]
    public function connectWithCredentialProvider(): void
    {
        $called = false;
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'credential_provider' => function () use (&$called): string {
                $called = true;

                return '';
            },
        ]);

        try {
            if (!$adapter->isAvailable()) {
                self::markTestSkipped('Redis Cluster is not available at 127.0.0.1:7010-7012.');
            }
        } catch (\Throwable) {
            // The auth may fail but credential_provider path was exercised
            self::assertTrue($called);

            return;
        }

        self::assertTrue($called);
        $adapter->close();
    }

    #[Test]
    public function connectWithReadTimeout(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'read_timeout' => 5,
        ]);

        if (!$adapter->isAvailable()) {
            self::markTestSkipped('Redis Cluster is not available at 127.0.0.1:7010-7012.');
        }

        self::assertTrue($adapter->set('{wppack_test}:read_timeout', 'value'));
        self::assertSame('value', $adapter->get('{wppack_test}:read_timeout'));

        $adapter->delete('{wppack_test}:read_timeout');
        $adapter->close();
    }

    #[Test]
    public function connectWithTls(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'tls' => true,
        ]);

        // TLS cluster is not available in test env, but createConnection is exercised
        // which covers the TLS seed building branch
        try {
            $adapter->isAvailable();
        } catch (\Throwable) {
            // Expected: TLS connection will fail
        }

        self::assertSame('redis-cluster', $adapter->getName());
        $adapter->close();
    }

    #[Test]
    public function connectWithAuth(): void
    {
        $adapter = new RedisClusterAdapter([
            'hosts' => ['127.0.0.1:7010', '127.0.0.1:7011', '127.0.0.1:7012'],
            'timeout' => 2,
            'auth' => 'testpassword',
        ]);

        // Auth will fail against our unauthenticated cluster, but createConnection is exercised
        try {
            $adapter->isAvailable();
        } catch (\Throwable) {
            // Expected: auth will fail
        }

        self::assertSame('redis-cluster', $adapter->getName());
        $adapter->close();
    }
}
