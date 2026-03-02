<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\Redis\Adapter\PredisAdapter;

final class PredisAdapterTest extends TestCase
{
    private PredisAdapter $adapter;

    protected function setUp(): void
    {
        if (!\class_exists(\Predis\Client::class)) {
            self::markTestSkipped('predis/predis is not available.');
        }

        $this->adapter = new PredisAdapter([
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
        self::assertSame('predis', $this->adapter->getName());
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
        $adapter = new PredisAdapter([
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
}
