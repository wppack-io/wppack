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

namespace WPPack\Component\Cache\Bridge\Apcu;

use WPPack\Component\Cache\Adapter\AbstractAdapter;

final class ApcuAdapter extends AbstractAdapter
{
    public function getName(): string
    {
        return 'apcu';
    }

    protected function doGet(string $key): ?string
    {
        $result = apcu_fetch($key, $success);

        if ($success === false) {
            return null;
        }

        return (string) $result;
    }

    protected function doGetMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = apcu_fetch($keys, $success);
        $results = [];

        if (!\is_array($values)) {
            return array_fill_keys($keys, null);
        }

        foreach ($keys as $key) {
            $results[$key] = isset($values[$key]) ? (string) $values[$key] : null;
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            apcu_delete($key);

            return true;
        }

        return apcu_store($key, $value, max(0, $ttl));
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        $keys = array_keys($values);

        if ($ttl < 0) {
            foreach ($keys as $key) {
                apcu_delete($key);
            }

            return array_fill_keys($keys, true);
        }

        $results = [];

        foreach ($values as $key => $value) {
            $results[$key] = apcu_store($key, $value, max(0, $ttl));
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            return true;
        }

        return apcu_add($key, $value, max(0, $ttl));
    }

    protected function doDelete(string $key): bool
    {
        apcu_delete($key);

        return true;
    }

    protected function doDeleteMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $results = [];

        foreach ($keys as $key) {
            apcu_delete($key);
            $results[$key] = true;
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): ?int
    {
        $value = apcu_fetch($key, $success);

        if ($success === false) {
            return null;
        }

        $newValue = (int) $value + $offset;
        apcu_store($key, (string) $newValue);

        return $newValue;
    }

    protected function doDecrement(string $key, int $offset = 1): ?int
    {
        $value = apcu_fetch($key, $success);

        if ($success === false) {
            return null;
        }

        $newValue = (int) $value - $offset;
        apcu_store($key, (string) $newValue);

        return $newValue;
    }

    protected function doFlush(string $prefix = ''): bool
    {
        if ($prefix === '') {
            return apcu_clear_cache();
        }

        if (class_exists(\APCUIterator::class)) {
            return apcu_delete(new \APCUIterator(
                sprintf('/^%s/', preg_quote($prefix, '/')),
            )) !== false;
        }

        return apcu_clear_cache();
    }

    public function isAvailable(): bool
    {
        return \function_exists('apcu_enabled') && apcu_enabled();
    }

    public function close(): void
    {
        // APCu is shared memory; nothing to close.
    }
}
