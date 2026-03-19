<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Apcu\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\Apcu\ApcuAdapter;

final class ApcuAdapterTest extends TestCase
{
    private ApcuAdapter $adapter;

    protected function setUp(): void
    {
        if (!\function_exists('apcu_enabled') || !apcu_enabled()) {
            self::markTestSkipped('APCu is not available (ext-apcu required with apc.enable_cli=1).');
        }

        $this->adapter = new ApcuAdapter();
        apcu_clear_cache();
    }

    protected function tearDown(): void
    {
        if (\function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('apcu', $this->adapter->getName());
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
    }

    #[Test]
    public function flushAll(): void
    {
        $this->adapter->set('wppack_test:a', '1');
        $this->adapter->set('wppack_test:b', '2');

        $this->adapter->flush();

        self::assertFalse($this->adapter->get('wppack_test:a'));
        self::assertFalse($this->adapter->get('wppack_test:b'));
    }

    #[Test]
    public function isAvailable(): void
    {
        self::assertTrue($this->adapter->isAvailable());
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
    public function getMultipleReturnsAllFalseForMissing(): void
    {
        $results = $this->adapter->getMultiple(['wppack_test:missing1', 'wppack_test:missing2']);

        self::assertFalse($results['wppack_test:missing1']);
        self::assertFalse($results['wppack_test:missing2']);
    }

    #[Test]
    public function deleteMultipleEmpty(): void
    {
        $results = $this->adapter->deleteMultiple([]);

        self::assertSame([], $results);
    }

    #[Test]
    public function flushWithPrefixDeletesMatchingKeys(): void
    {
        $this->adapter->set('wppack_test:a', '1');
        $this->adapter->set('wppack_test:b', '2');
        $this->adapter->set('wppack_other:c', '3');

        $this->adapter->flush('wppack_test:');

        self::assertFalse($this->adapter->get('wppack_test:a'));
        self::assertFalse($this->adapter->get('wppack_test:b'));
        self::assertSame('3', $this->adapter->get('wppack_other:c'));
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
    public function getMultipleWithEmptyKeys(): void
    {
        $results = $this->adapter->getMultiple([]);

        self::assertSame([], $results);
    }

    #[Test]
    public function decrementReturnsFalseForMissing(): void
    {
        self::assertFalse($this->adapter->decrement('wppack_test:nonexistent'));
    }

    #[Test]
    public function closeDoesNotThrow(): void
    {
        $this->adapter->close();

        // close() is a no-op for APCu; verify adapter still works afterward
        self::assertTrue($this->adapter->set('wppack_test:after_close', 'value'));
        self::assertSame('value', $this->adapter->get('wppack_test:after_close'));
    }
}
