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

namespace WpPack\Component\Cache\Bridge\Memcached;

use WpPack\Component\Cache\Adapter\AbstractAdapter;

final class MemcachedAdapter extends AbstractAdapter
{
    public function __construct(
        private readonly \Memcached $client,
    ) {}

    public function getName(): string
    {
        return 'memcached';
    }

    protected function doGet(string $key): ?string
    {
        $result = $this->client->get($key);

        if ($this->client->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }

        return (string) $result;
    }

    protected function doGetMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = $this->client->getMulti($keys);

        if ($values === false) {
            return array_fill_keys($keys, null);
        }

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = isset($values[$key]) ? (string) $values[$key] : null;
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            $this->client->delete($key);

            return true;
        }

        return $this->client->set($key, $value, $ttl);
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        $keys = array_keys($values);

        if ($ttl < 0) {
            if ($keys !== []) {
                $this->client->deleteMulti($keys);
            }

            return array_fill_keys($keys, true);
        }

        $this->client->setMulti($values, $ttl);
        $success = $this->client->getResultCode() === \Memcached::RES_SUCCESS;

        return array_fill_keys($keys, $success);
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            return true;
        }

        return $this->client->add($key, $value, $ttl);
    }

    protected function doDelete(string $key): bool
    {
        $this->client->delete($key);

        return $this->client->getResultCode() === \Memcached::RES_SUCCESS
            || $this->client->getResultCode() === \Memcached::RES_NOTFOUND;
    }

    protected function doDeleteMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $deleteResults = $this->client->deleteMulti($keys);
        $results = [];

        foreach ($keys as $key) {
            $result = $deleteResults[$key] ?? false;
            // deleteMulti returns true on success, or Memcached::RES_NOTFOUND (int) on miss
            $results[$key] = $result === true || $result === \Memcached::RES_NOTFOUND;
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): ?int
    {
        $result = $this->client->increment($key, $offset);

        if ($result === false) {
            return null;
        }

        return (int) $result;
    }

    protected function doDecrement(string $key, int $offset = 1): ?int
    {
        $result = $this->client->decrement($key, $offset);

        if ($result === false) {
            return null;
        }

        return (int) $result;
    }

    protected function doFlush(string $prefix = ''): bool
    {
        if ($prefix === '') {
            return $this->client->flush();
        }

        $keys = $this->client->getAllKeys();

        if ($keys === false) {
            // getAllKeys() is unreliable on some Memcached configurations;
            // fall back to a full flush.
            return $this->client->flush();
        }

        $toDelete = array_filter($keys, static fn(string $key): bool => str_starts_with($key, $prefix));

        if ($toDelete !== []) {
            $this->client->deleteMulti(array_values($toDelete));
        }

        return true;
    }

    public function isAvailable(): bool
    {
        try {
            /** @var array<string, array<string, mixed>>|false $stats */
            $stats = $this->client->getStats();

            if (!\is_array($stats) || $stats === []) {
                return false;
            }

            foreach ($stats as $serverStats) {
                if (($serverStats['pid'] ?? -1) > 0) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        $this->client->quit();
    }
}
