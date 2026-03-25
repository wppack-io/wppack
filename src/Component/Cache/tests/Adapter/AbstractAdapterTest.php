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

namespace WpPack\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\AbstractAdapter;
use WpPack\Component\Cache\Exception\AdapterException;

#[CoversClass(AbstractAdapter::class)]
final class AbstractAdapterTest extends TestCase
{
    #[Test]
    public function getDelegatesToDoGet(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doGet'] = 'value1';

        self::assertSame('value1', $adapter->get('key1'));
        self::assertSame([['doGet', ['key1']]], $adapter->calls);
    }

    #[Test]
    public function setDelegatesToDoSet(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doSet'] = true;

        self::assertTrue($adapter->set('key1', 'val', 60));
        self::assertSame([['doSet', ['key1', 'val', 60]]], $adapter->calls);
    }

    #[Test]
    public function getMultipleDelegatesToDoGetMultiple(): void
    {
        $adapter = $this->createConcreteAdapter();
        $expected = ['k1' => 'v1', 'k2' => null];
        $adapter->returnValues['doGetMultiple'] = $expected;

        self::assertSame($expected, $adapter->getMultiple(['k1', 'k2']));
        self::assertSame([['doGetMultiple', [['k1', 'k2']]]], $adapter->calls);
    }

    #[Test]
    public function setMultipleDelegatesToDoSetMultiple(): void
    {
        $adapter = $this->createConcreteAdapter();
        $expected = ['k1' => true, 'k2' => true];
        $adapter->returnValues['doSetMultiple'] = $expected;

        $values = ['k1' => 'v1', 'k2' => 'v2'];
        self::assertSame($expected, $adapter->setMultiple($values, 120));
        self::assertSame([['doSetMultiple', [$values, 120]]], $adapter->calls);
    }

    #[Test]
    public function addDelegatesToDoAdd(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doAdd'] = true;

        self::assertTrue($adapter->add('key1', 'val', 30));
        self::assertSame([['doAdd', ['key1', 'val', 30]]], $adapter->calls);
    }

    #[Test]
    public function deleteDelegatesToDoDelete(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doDelete'] = true;

        self::assertTrue($adapter->delete('key1'));
        self::assertSame([['doDelete', ['key1']]], $adapter->calls);
    }

    #[Test]
    public function deleteMultipleDelegatesToDoDeleteMultiple(): void
    {
        $adapter = $this->createConcreteAdapter();
        $expected = ['k1' => true, 'k2' => false];
        $adapter->returnValues['doDeleteMultiple'] = $expected;

        self::assertSame($expected, $adapter->deleteMultiple(['k1', 'k2']));
        self::assertSame([['doDeleteMultiple', [['k1', 'k2']]]], $adapter->calls);
    }

    #[Test]
    public function incrementDelegatesToDoIncrement(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doIncrement'] = 5;

        self::assertSame(5, $adapter->increment('counter', 2));
        self::assertSame([['doIncrement', ['counter', 2]]], $adapter->calls);
    }

    #[Test]
    public function decrementDelegatesToDoDecrement(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doDecrement'] = 3;

        self::assertSame(3, $adapter->decrement('counter', 1));
        self::assertSame([['doDecrement', ['counter', 1]]], $adapter->calls);
    }

    #[Test]
    public function flushDelegatesToDoFlush(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doFlush'] = true;

        self::assertTrue($adapter->flush('prefix_'));
        self::assertSame([['doFlush', ['prefix_']]], $adapter->calls);
    }

    #[Test]
    public function executeWrapsNonAdapterExceptionInAdapterException(): void
    {
        $adapter = $this->createConcreteAdapter();
        $original = new \RuntimeException('connection lost');
        $adapter->throwOn['doGet'] = $original;

        try {
            $adapter->get('key1');
            self::fail('Expected AdapterException');
        } catch (AdapterException $e) {
            self::assertSame('connection lost', $e->getMessage());
            self::assertSame($original, $e->getPrevious());
        }
    }

    #[Test]
    public function executeRethrowsAdapterExceptionUnchanged(): void
    {
        $adapter = $this->createConcreteAdapter();
        $original = new AdapterException('adapter error');
        $adapter->throwOn['doSet'] = $original;

        try {
            $adapter->set('key1', 'val');
            self::fail('Expected AdapterException');
        } catch (AdapterException $e) {
            self::assertSame($original, $e);
            self::assertNull($e->getPrevious());
        }
    }

    private function createConcreteAdapter(): AbstractAdapter
    {
        return new class extends AbstractAdapter {
            /** @var list<array{string, list<mixed>}> */
            public array $calls = [];

            /** @var array<string, mixed> */
            public array $returnValues = [];

            /** @var array<string, \Throwable> */
            public array $throwOn = [];

            public function getName(): string
            {
                return 'test';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function close(): void {}

            protected function doGet(string $key): ?string
            {
                return $this->record('doGet', [$key]);
            }

            protected function doGetMultiple(array $keys): array
            {
                return $this->record('doGetMultiple', [$keys]);
            }

            protected function doSet(string $key, string $value, int $ttl = 0): bool
            {
                return $this->record('doSet', [$key, $value, $ttl]);
            }

            protected function doSetMultiple(array $values, int $ttl = 0): array
            {
                return $this->record('doSetMultiple', [$values, $ttl]);
            }

            protected function doAdd(string $key, string $value, int $ttl = 0): bool
            {
                return $this->record('doAdd', [$key, $value, $ttl]);
            }

            protected function doDelete(string $key): bool
            {
                return $this->record('doDelete', [$key]);
            }

            protected function doDeleteMultiple(array $keys): array
            {
                return $this->record('doDeleteMultiple', [$keys]);
            }

            protected function doIncrement(string $key, int $offset = 1): ?int
            {
                return $this->record('doIncrement', [$key, $offset]);
            }

            protected function doDecrement(string $key, int $offset = 1): ?int
            {
                return $this->record('doDecrement', [$key, $offset]);
            }

            protected function doFlush(string $prefix = ''): bool
            {
                return $this->record('doFlush', [$prefix]);
            }

            private function record(string $method, array $args): mixed
            {
                $this->calls[] = [$method, $args];

                if (isset($this->throwOn[$method])) {
                    throw $this->throwOn[$method];
                }

                return $this->returnValues[$method] ?? false;
            }
        };
    }
}
