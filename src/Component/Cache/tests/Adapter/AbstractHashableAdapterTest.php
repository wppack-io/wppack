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

namespace WPPack\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Adapter\AbstractHashableAdapter;
use WPPack\Component\Cache\Adapter\HashableAdapterInterface;
use WPPack\Component\Cache\Exception\AdapterException;

#[CoversClass(AbstractHashableAdapter::class)]
final class AbstractHashableAdapterTest extends TestCase
{
    #[Test]
    public function hashOperationsDelegateToSubclassDoMethods(): void
    {
        $adapter = new InMemoryAbstractHashableAdapter();

        self::assertInstanceOf(HashableAdapterInterface::class, $adapter);

        $adapter->hashSetMultiple('user:42', ['name' => 'alice', 'role' => 'admin']);

        self::assertSame('alice', $adapter->hashGet('user:42', 'name'));
        self::assertSame('admin', $adapter->hashGet('user:42', 'role'));
        self::assertNull($adapter->hashGet('user:42', 'missing-field'));

        self::assertSame(['name' => 'alice', 'role' => 'admin'], $adapter->hashGetAll('user:42'));
    }

    #[Test]
    public function hashGetAllReturnsEmptyForUnknownKey(): void
    {
        self::assertSame([], (new InMemoryAbstractHashableAdapter())->hashGetAll('nope'));
    }

    #[Test]
    public function hashDeleteMultipleRemovesOnlyNamedFields(): void
    {
        $adapter = new InMemoryAbstractHashableAdapter();
        $adapter->hashSetMultiple('k', ['a' => '1', 'b' => '2', 'c' => '3']);

        $adapter->hashDeleteMultiple('k', ['a', 'c']);

        self::assertSame(['b' => '2'], $adapter->hashGetAll('k'));
    }

    #[Test]
    public function hashDeleteRemovesEntireKey(): void
    {
        $adapter = new InMemoryAbstractHashableAdapter();
        $adapter->hashSetMultiple('k', ['a' => '1']);

        self::assertTrue($adapter->hashDelete('k'));
        self::assertSame([], $adapter->hashGetAll('k'));
    }

    #[Test]
    public function throwableInDoMethodBecomesAdapterException(): void
    {
        $adapter = new ThrowingHashableAdapter();

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('backend down');

        $adapter->hashGet('k', 'f');
    }

    #[Test]
    public function existingAdapterExceptionPassesThrough(): void
    {
        $adapter = new ThrowingHashableAdapter(existingAdapterException: true);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('adapter-already');

        $adapter->hashGetAll('k');
    }
}

/**
 * @internal
 */
final class InMemoryAbstractHashableAdapter extends AbstractHashableAdapter
{
    /** @var array<string, array<string, string>> */
    private array $hashes = [];

    public function getName(): string
    {
        return 'in-memory-hashable-abstract';
    }

    protected function doHashGetAll(string $key): array
    {
        return $this->hashes[$key] ?? [];
    }

    protected function doHashGet(string $key, string $field): ?string
    {
        return $this->hashes[$key][$field] ?? null;
    }

    protected function doHashSetMultiple(string $key, array $fields): bool
    {
        foreach ($fields as $field => $value) {
            $this->hashes[$key][$field] = $value;
        }

        return true;
    }

    protected function doHashDeleteMultiple(string $key, array $fields): bool
    {
        foreach ($fields as $field) {
            unset($this->hashes[$key][$field]);
        }

        return true;
    }

    protected function doHashDelete(string $key): bool
    {
        unset($this->hashes[$key]);

        return true;
    }

    // Minimal AbstractAdapter stubs (unused here but required)
    protected function doGet(string $key): ?string { return null; }
    protected function doGetMultiple(array $keys): array { return []; }
    protected function doSet(string $key, string $value, int $ttl = 0): bool { return true; }
    protected function doSetMultiple(array $values, int $ttl = 0): array { return []; }
    protected function doAdd(string $key, string $value, int $ttl = 0): bool { return true; }
    protected function doDelete(string $key): bool { return true; }
    protected function doDeleteMultiple(array $keys): array { return []; }
    protected function doIncrement(string $key, int $offset = 1): ?int { return null; }
    protected function doDecrement(string $key, int $offset = 1): ?int { return null; }
    protected function doFlush(string $prefix = ''): bool { return true; }
    public function isAvailable(): bool { return true; }
    public function close(): void {}
}

/**
 * @internal
 */
final class ThrowingHashableAdapter extends AbstractHashableAdapter
{
    public function __construct(private readonly bool $existingAdapterException = false) {}

    public function getName(): string
    {
        return 'throwing-hashable';
    }

    protected function doHashGetAll(string $key): array
    {
        if ($this->existingAdapterException) {
            throw new AdapterException('adapter-already');
        }

        throw new \RuntimeException('backend down');
    }

    protected function doHashGet(string $key, string $field): ?string
    {
        throw new \RuntimeException('backend down');
    }

    protected function doHashSetMultiple(string $key, array $fields): bool { return true; }
    protected function doHashDeleteMultiple(string $key, array $fields): bool { return true; }
    protected function doHashDelete(string $key): bool { return true; }

    protected function doGet(string $key): ?string { return null; }
    protected function doGetMultiple(array $keys): array { return []; }
    protected function doSet(string $key, string $value, int $ttl = 0): bool { return true; }
    protected function doSetMultiple(array $values, int $ttl = 0): array { return []; }
    protected function doAdd(string $key, string $value, int $ttl = 0): bool { return true; }
    protected function doDelete(string $key): bool { return true; }
    protected function doDeleteMultiple(array $keys): array { return []; }
    protected function doIncrement(string $key, int $offset = 1): ?int { return null; }
    protected function doDecrement(string $key, int $offset = 1): ?int { return null; }
    protected function doFlush(string $prefix = ''): bool { return true; }
    public function isAvailable(): bool { return true; }
    public function close(): void {}
}
