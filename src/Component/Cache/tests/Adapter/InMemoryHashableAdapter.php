<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Tests\Adapter;

use WpPack\Component\Cache\Adapter\HashableAdapterInterface;

final class InMemoryHashableAdapter implements HashableAdapterInterface
{
    /** @var array<string, string> */
    private array $data = [];

    /** @var array<string, array<string, string>> key => [field => value] */
    private array $hashes = [];

    public function getName(): string
    {
        return 'in-memory-hashable';
    }

    public function get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->data[$key] ?? null;
        }

        return $results;
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        $this->data[$key] = $value;

        return true;
    }

    public function setMultiple(array $values, int $ttl = 0): array
    {
        $results = [];

        foreach ($values as $key => $value) {
            $this->data[$key] = $value;
            $results[$key] = true;
        }

        return $results;
    }

    public function add(string $key, string $value, int $ttl = 0): bool
    {
        if (isset($this->data[$key])) {
            return false;
        }

        $this->data[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    public function deleteMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            unset($this->data[$key]);
            $results[$key] = true;
        }

        return $results;
    }

    public function increment(string $key, int $offset = 1): ?int
    {
        if (!isset($this->data[$key])) {
            return null;
        }

        $value = (int) unserialize($this->data[$key]) + $offset;
        $this->data[$key] = serialize($value);

        return $value;
    }

    public function decrement(string $key, int $offset = 1): ?int
    {
        if (!isset($this->data[$key])) {
            return null;
        }

        $value = (int) unserialize($this->data[$key]) - $offset;
        $this->data[$key] = serialize($value);

        return $value;
    }

    public function flush(string $prefix = ''): bool
    {
        if ($prefix === '') {
            $this->data = [];
            $this->hashes = [];

            return true;
        }

        foreach (array_keys($this->data) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->data[$key]);
            }
        }

        foreach (array_keys($this->hashes) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->hashes[$key]);
            }
        }

        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function close(): void
    {
        // No-op
    }

    public function hashGetAll(string $key): array
    {
        return $this->hashes[$key] ?? [];
    }

    public function hashGet(string $key, string $field): ?string
    {
        return $this->hashes[$key][$field] ?? null;
    }

    public function hashSetMultiple(string $key, array $fields): bool
    {
        if (!isset($this->hashes[$key])) {
            $this->hashes[$key] = [];
        }

        foreach ($fields as $field => $value) {
            $this->hashes[$key][$field] = $value;
        }

        return true;
    }

    public function hashDeleteMultiple(string $key, array $fields): bool
    {
        foreach ($fields as $field) {
            unset($this->hashes[$key][$field]);
        }

        return true;
    }

    public function hashDelete(string $key): bool
    {
        unset($this->hashes[$key]);

        return true;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getHashData(): array
    {
        return $this->hashes;
    }
}
