<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Tests\Adapter;

use WpPack\Component\Cache\Adapter\AdapterInterface;

final class InMemoryAdapter implements AdapterInterface
{
    /** @var array<string, string> */
    private array $data = [];

    public function getName(): string
    {
        return 'in-memory';
    }

    public function get(string $key): string|false
    {
        return $this->data[$key] ?? false;
    }

    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->data[$key] ?? false;
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

    public function increment(string $key, int $offset = 1): int|false
    {
        if (!isset($this->data[$key])) {
            return false;
        }

        $value = (int) unserialize($this->data[$key]) + $offset;
        $this->data[$key] = serialize($value);

        return $value;
    }

    public function decrement(string $key, int $offset = 1): int|false
    {
        if (!isset($this->data[$key])) {
            return false;
        }

        $value = (int) unserialize($this->data[$key]) - $offset;
        $this->data[$key] = serialize($value);

        return $value;
    }

    public function flush(string $prefix = ''): bool
    {
        if ($prefix === '') {
            $this->data = [];

            return true;
        }

        foreach (array_keys($this->data) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->data[$key]);
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
}
