<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Adapter;

use WpPack\Component\Cache\Exception\AdapterException;

abstract class AbstractAdapter implements AdapterInterface
{
    abstract public function getName(): string;

    abstract protected function doGet(string $key): string|false;

    /**
     * @param list<string> $keys
     * @return array<string, string|false>
     */
    abstract protected function doGetMultiple(array $keys): array;

    abstract protected function doSet(string $key, string $value, int $ttl = 0): bool;

    /**
     * @param array<string, string> $values
     * @return array<string, bool>
     */
    abstract protected function doSetMultiple(array $values, int $ttl = 0): array;

    abstract protected function doAdd(string $key, string $value, int $ttl = 0): bool;

    abstract protected function doDelete(string $key): bool;

    /**
     * @param list<string> $keys
     * @return array<string, bool>
     */
    abstract protected function doDeleteMultiple(array $keys): array;

    abstract protected function doIncrement(string $key, int $offset = 1): int|false;

    abstract protected function doDecrement(string $key, int $offset = 1): int|false;

    abstract protected function doFlush(string $prefix = ''): bool;

    abstract public function isAvailable(): bool;

    abstract public function close(): void;

    public function get(string $key): string|false
    {
        return $this->execute(fn(): string|false => $this->doGet($key));
    }

    /** @return array<string, string|false> */
    public function getMultiple(array $keys): array
    {
        return $this->execute(fn(): array => $this->doGetMultiple($keys));
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        return $this->execute(fn(): bool => $this->doSet($key, $value, $ttl));
    }

    /** @return array<string, bool> */
    public function setMultiple(array $values, int $ttl = 0): array
    {
        return $this->execute(fn(): array => $this->doSetMultiple($values, $ttl));
    }

    public function add(string $key, string $value, int $ttl = 0): bool
    {
        return $this->execute(fn(): bool => $this->doAdd($key, $value, $ttl));
    }

    public function delete(string $key): bool
    {
        return $this->execute(fn(): bool => $this->doDelete($key));
    }

    /** @return array<string, bool> */
    public function deleteMultiple(array $keys): array
    {
        return $this->execute(fn(): array => $this->doDeleteMultiple($keys));
    }

    public function increment(string $key, int $offset = 1): int|false
    {
        return $this->execute(fn(): int|false => $this->doIncrement($key, $offset));
    }

    public function decrement(string $key, int $offset = 1): int|false
    {
        return $this->execute(fn(): int|false => $this->doDecrement($key, $offset));
    }

    public function flush(string $prefix = ''): bool
    {
        return $this->execute(fn(): bool => $this->doFlush($prefix));
    }

    /**
     * @template T
     * @param \Closure(): T $operation
     * @return T
     */
    protected function execute(\Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (AdapterException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AdapterException($e->getMessage(), 0, $e);
        }
    }
}
