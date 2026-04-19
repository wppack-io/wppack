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

namespace WPPack\Component\Cache\Bridge\Memcached\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Bridge\Memcached\MemcachedAdapter;

final class MemcachedAdapterTest extends TestCase
{
    private MemcachedAdapter $adapter;

    protected function setUp(): void
    {
        if (!\extension_loaded('memcached')) {
            self::markTestSkipped('ext-memcached is not available.');
        }

        $client = new \Memcached();
        $client->addServer('127.0.0.1', 11211);

        $this->adapter = new MemcachedAdapter($client);

        if (!$this->adapter->isAvailable()) {
            self::markTestSkipped('Memcached server is not available at 127.0.0.1:11211.');
        }

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
        self::assertSame('memcached', $this->adapter->getName());
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

        // Memcached's getAllKeys() requires the LRU crawler to index keys,
        // which takes a short time after writes.
        sleep(2);

        $this->adapter->flush('wppack_test:');

        self::assertNull($this->adapter->get('wppack_test:a'));
        self::assertNull($this->adapter->get('wppack_test:b'));
        self::assertSame('3', $this->adapter->get('wppack_other:c'));

        // Clean up
        $this->adapter->delete('wppack_other:c');
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
    public function isAvailable(): void
    {
        self::assertTrue($this->adapter->isAvailable());
    }

    #[Test]
    public function isNotAvailableForBadConnection(): void
    {
        $client = new \Memcached();
        $client->addServer('127.0.0.1', 1);

        $adapter = new MemcachedAdapter($client);

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
    public function getMultipleReturnsAllFalseWhenEmpty(): void
    {
        $results = $this->adapter->getMultiple([]);

        self::assertSame([], $results);
    }

    #[Test]
    public function deleteMultipleEmpty(): void
    {
        $results = $this->adapter->deleteMultiple([]);

        self::assertSame([], $results);
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
    public function decrementReturnsFalseForMissing(): void
    {
        self::assertNull($this->adapter->decrement('wppack_test:nonexistent'));
    }

    #[Test]
    public function getMultipleReturnsFalseForAllKeysWhenClientFails(): void
    {
        $client = $this->createMock(\Memcached::class);
        $client->method('getMulti')->willReturn(false);

        $adapter = new MemcachedAdapter($client);
        $results = $adapter->getMultiple(['key1', 'key2']);

        self::assertSame(['key1' => null, 'key2' => null], $results);
    }

    #[Test]
    public function flushWithPrefixFallsBackWhenGetAllKeysFails(): void
    {
        $client = $this->createMock(\Memcached::class);
        $client->method('getAllKeys')->willReturn(false);
        $client->expects(self::once())->method('flush')->willReturn(true);

        $adapter = new MemcachedAdapter($client);

        self::assertTrue($adapter->flush('some_prefix:'));
    }

    #[Test]
    public function isAvailableReturnsFalseWhenNoServerHasValidPid(): void
    {
        $client = $this->createMock(\Memcached::class);
        $client->method('getStats')->willReturn([
            '127.0.0.1:11211' => ['pid' => -1],
        ]);

        $adapter = new MemcachedAdapter($client);

        self::assertFalse($adapter->isAvailable());
    }

    #[Test]
    public function isAvailableReturnsFalseOnException(): void
    {
        $client = $this->createMock(\Memcached::class);
        $client->method('getStats')->willThrowException(new \RuntimeException('Connection failed'));

        $adapter = new MemcachedAdapter($client);

        self::assertFalse($adapter->isAvailable());
    }
}
