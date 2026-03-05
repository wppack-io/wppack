<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RelayAdapter;

final class RelayAdapterTest extends TestCase
{
    private RelayAdapter $adapter;

    protected function setUp(): void
    {
        if (!\extension_loaded('relay')) {
            self::markTestSkipped('ext-relay is not available.');
        }

        $this->adapter = new RelayAdapter([
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
        self::assertSame('relay', $this->adapter->getName());
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
        self::assertFalse($this->adapter->get('wppack_test:nonexistent'));
    }

    #[Test]
    public function getMultiple(): void
    {
        $this->adapter->set('wppack_test:key1', 'value1');
        $this->adapter->set('wppack_test:key2', 'value2');

        $results = $this->adapter->getMultiple(['wppack_test:key1', 'wppack_test:key2', 'wppack_test:missing']);

        self::assertSame('value1', $results['wppack_test:key1']);
        self::assertSame('value2', $results['wppack_test:key2']);
        self::assertFalse($results['wppack_test:missing']);
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

        self::assertFalse($this->adapter->get('wppack_test:key'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->adapter->set('wppack_test:key1', 'value1');
        $this->adapter->set('wppack_test:key2', 'value2');

        $results = $this->adapter->deleteMultiple(['wppack_test:key1', 'wppack_test:key2']);

        self::assertTrue($results['wppack_test:key1']);
        self::assertTrue($results['wppack_test:key2']);
        self::assertFalse($this->adapter->get('wppack_test:key1'));
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
        self::assertFalse($this->adapter->increment('wppack_test:nonexistent'));
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

        self::assertFalse($this->adapter->get('wppack_test:a'));
        self::assertFalse($this->adapter->get('wppack_test:b'));
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
        $adapter = new RelayAdapter([
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
        self::assertFalse($this->adapter->get('wppack_test:neg'));
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
        self::assertFalse($this->adapter->get('wppack_test:neg1'));
        self::assertFalse($this->adapter->get('wppack_test:neg2'));
    }

    #[Test]
    public function addWithNegativeTtlIsNoop(): void
    {
        $this->adapter->set('wppack_test:existing', 'old');

        self::assertTrue($this->adapter->add('wppack_test:existing', 'new', -1));
        self::assertSame('old', $this->adapter->get('wppack_test:existing'));
    }

    #[Test]
    public function connectViaSentinel(): void
    {
        $adapter = new RelayAdapter([
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
        $adapter = new RelayAdapter([
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
        $adapter = new RelayAdapter([
            'host' => '127.0.0.1',
            'port' => 6379,
            'auth' => 'invalid_password_for_test',
            'dbindex' => 2,
        ]);

        try {
            if (!$adapter->isAvailable()) {
                self::markTestSkipped('Redis server with auth is not available.');
            }
        } catch (\Throwable) {
            self::markTestSkipped('Redis server does not support auth or connection failed.');
        }

        self::assertTrue($adapter->set('wppack_test:authdb', 'value'));
        self::assertSame('value', $adapter->get('wppack_test:authdb'));

        $adapter->delete('wppack_test:authdb');
        $adapter->close();
    }

    #[Test]
    public function connectWithReadTimeout(): void
    {
        $adapter = new RelayAdapter([
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
}
