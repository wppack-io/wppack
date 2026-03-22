<?php

declare(strict_types=1);

namespace WpPack\Component\Cache;

final class CacheManager
{
    public function get(string $key, string $group = ''): mixed
    {
        return wp_cache_get($key, $group);
    }

    public function set(string $key, mixed $data, string $group = '', int $expiration = 0): bool
    {
        return wp_cache_set($key, $data, $group, $expiration);
    }

    public function add(string $key, mixed $data, string $group = '', int $expiration = 0): bool
    {
        return wp_cache_add($key, $data, $group, $expiration);
    }

    public function replace(string $key, mixed $data, string $group = '', int $expiration = 0): bool
    {
        return wp_cache_replace($key, $data, $group, $expiration);
    }

    public function delete(string $key, string $group = ''): bool
    {
        return wp_cache_delete($key, $group);
    }

    public function flush(): bool
    {
        return wp_cache_flush();
    }

    public function flushGroup(string $group): bool
    {
        return wp_cache_flush_group($group);
    }

    public function increment(string $key, int $offset = 1, string $group = ''): ?int
    {
        $result = wp_cache_incr($key, $offset, $group);

        return $result === false ? null : $result;
    }

    public function decrement(string $key, int $offset = 1, string $group = ''): ?int
    {
        $result = wp_cache_decr($key, $offset, $group);

        return $result === false ? null : $result;
    }

    public function supports(string $feature): bool
    {
        return wp_cache_supports($feature);
    }
}
