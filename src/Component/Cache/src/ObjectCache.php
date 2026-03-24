<?php

declare(strict_types=1);

namespace WpPack\Component\Cache;

use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\HashableAdapterInterface;
use WpPack\Component\Cache\Strategy\HashStrategyInterface;

final class ObjectCache
{
    /** @var array<string, array<string, mixed>> group => [key => value] */
    private array $runtime = [];

    /** @var array<string, true> */
    private array $globalGroups = [];

    /** @var array<string, true> */
    private array $nonPersistentGroups = [];

    private int $blogId = 0;

    private int $hits = 0;

    private int $misses = 0;

    /** @var array<string, array<string, string>> fullKey => [field => serialized value] */
    private array $hashState = [];

    private readonly ObjectCacheConfig $config;

    public function __construct(
        private readonly ?AdapterInterface $adapter,
        ObjectCacheConfig $config = new ObjectCacheConfig(),
    ) {
        $this->config = $config;
    }

    public function get(string $key, string $group = 'default', bool $force = false, bool &$found = false): mixed
    {
        $group = $this->normalizeGroup($group);
        $runtimeKey = $this->runtimeKey($key, $group);

        if (!$force && isset($this->runtime[$group][$runtimeKey])) {
            $found = true;
            ++$this->hits;

            return $this->runtime[$group][$runtimeKey];
        }

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKey = $this->buildKey($key, $group);
            $strategy = $this->findHashStrategy($key, $group);

            if ($strategy !== null) {
                /** @var HashableAdapterInterface $adapter */
                $adapter = $this->adapter;
                $fields = $adapter->hashGetAll($fullKey);

                if ($fields !== []) {
                    $data = $strategy->deserialize($fields);
                    $this->runtime[$group][$runtimeKey] = $data;
                    $this->hashState[$fullKey] = $fields;
                    $found = true;
                    ++$this->hits;

                    return $data;
                }
            } else {
                $value = $this->adapter->get($fullKey);

                if ($value !== null) {
                    $data = \unserialize($value);

                    $this->runtime[$group][$runtimeKey] = $data;
                    $found = true;
                    ++$this->hits;

                    return $data;
                }
            }
        }

        $found = false;
        ++$this->misses;

        return false;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys, string $group = 'default', bool $force = false): array
    {
        $group = $this->normalizeGroup($group);
        $results = [];
        $keysToFetch = [];

        foreach ($keys as $key) {
            $runtimeKey = $this->runtimeKey($key, $group);

            if (!$force && isset($this->runtime[$group][$runtimeKey])) {
                $results[$key] = $this->runtime[$group][$runtimeKey];
                ++$this->hits;
            } else {
                $keysToFetch[] = $key;
                $results[$key] = false;
            }
        }

        if ($keysToFetch !== [] && $this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKeys = [];
            foreach ($keysToFetch as $key) {
                $fullKeys[$key] = $this->buildKey($key, $group);
            }

            $fetched = $this->adapter->getMultiple(array_values($fullKeys));

            foreach ($keysToFetch as $key) {
                $fullKey = $fullKeys[$key];
                $value = $fetched[$fullKey] ?? null;

                if ($value !== null) {
                    $data = \unserialize($value);

                    $runtimeKey = $this->runtimeKey($key, $group);
                    $this->runtime[$group][$runtimeKey] = $data;
                    $results[$key] = $data;
                    ++$this->hits;
                } else {
                    ++$this->misses;
                }
            }
        } elseif ($keysToFetch !== []) {
            $this->misses += \count($keysToFetch);
        }

        return $results;
    }

    public function set(string $key, mixed $data, string $group = 'default', int $expiration = 0): bool
    {
        $group = $this->normalizeGroup($group);
        $runtimeKey = $this->runtimeKey($key, $group);

        $this->runtime[$group][$runtimeKey] = $data;

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKey = $this->buildKey($key, $group);
            $strategy = $this->findHashStrategy($key, $group);

            if ($strategy !== null) {
                /** @var HashableAdapterInterface $adapter */
                $adapter = $this->adapter;

                $newFields = $strategy->serialize(\is_array($data) ? $data : []);
                $oldFields = $this->hashState[$fullKey] ?? null;

                if ($oldFields !== null) {
                    $toDelete = array_diff_key($oldFields, $newFields);
                    $toSet = array_diff_assoc($newFields, $oldFields);

                    if ($toDelete !== []) {
                        $adapter->hashDeleteMultiple($fullKey, array_keys($toDelete));
                    }

                    if ($toSet !== []) {
                        $adapter->hashSetMultiple($fullKey, $toSet);
                    }
                } else {
                    $adapter->hashDelete($fullKey);

                    if ($newFields !== []) {
                        $adapter->hashSetMultiple($fullKey, $newFields);
                    }
                }

                $this->hashState[$fullKey] = $newFields;

                return true;
            }

            return $this->adapter->set($fullKey, \serialize($data), $this->clampTtl($expiration));
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, bool>
     */
    public function setMultiple(array $data, string $group = 'default', int $expiration = 0): array
    {
        $group = $this->normalizeGroup($group);
        $results = [];

        foreach ($data as $key => $value) {
            $runtimeKey = $this->runtimeKey($key, $group);
            $this->runtime[$group][$runtimeKey] = $value;
        }

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $serialized = [];
            $keyMap = [];
            foreach ($data as $key => $value) {
                $fullKey = $this->buildKey($key, $group);
                $serialized[$fullKey] = \serialize($value);
                $keyMap[$fullKey] = $key;
            }

            $adapterResults = $this->adapter->setMultiple($serialized, $this->clampTtl($expiration));

            foreach ($adapterResults as $fullKey => $success) {
                $results[$keyMap[$fullKey]] = $success;
            }
        } else {
            foreach ($data as $key => $value) {
                $results[$key] = true;
            }
        }

        return $results;
    }

    public function add(string $key, mixed $data, string $group = 'default', int $expiration = 0): bool
    {
        $group = $this->normalizeGroup($group);
        $runtimeKey = $this->runtimeKey($key, $group);

        if (isset($this->runtime[$group][$runtimeKey])) {
            return false;
        }

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKey = $this->buildKey($key, $group);
            $result = $this->adapter->add($fullKey, \serialize($data), $this->clampTtl($expiration));

            if (!$result) {
                return false;
            }
        }

        $this->runtime[$group][$runtimeKey] = $data;

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, bool>
     */
    public function addMultiple(array $data, string $group = 'default', int $expiration = 0): array
    {
        $results = [];

        foreach ($data as $key => $value) {
            $results[$key] = $this->add($key, $value, $group, $expiration);
        }

        return $results;
    }

    public function replace(string $key, mixed $data, string $group = 'default', int $expiration = 0): bool
    {
        $group = $this->normalizeGroup($group);
        $runtimeKey = $this->runtimeKey($key, $group);

        $exists = isset($this->runtime[$group][$runtimeKey]);

        if (!$exists && $this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKey = $this->buildKey($key, $group);
            $exists = $this->adapter->get($fullKey) !== null;
        }

        if (!$exists) {
            return false;
        }

        return $this->set($key, $data, $group, $expiration);
    }

    public function delete(string $key, string $group = 'default'): bool
    {
        $group = $this->normalizeGroup($group);
        $runtimeKey = $this->runtimeKey($key, $group);

        unset($this->runtime[$group][$runtimeKey]);

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKey = $this->buildKey($key, $group);
            $strategy = $this->findHashStrategy($key, $group);

            if ($strategy !== null) {
                /** @var HashableAdapterInterface $adapter */
                $adapter = $this->adapter;
                unset($this->hashState[$fullKey]);

                return $adapter->hashDelete($fullKey);
            }

            return $this->adapter->delete($fullKey);
        }

        return true;
    }

    /**
     * @param list<string> $keys
     * @return array<string, bool>
     */
    public function deleteMultiple(array $keys, string $group = 'default'): array
    {
        $group = $this->normalizeGroup($group);
        $results = [];

        foreach ($keys as $key) {
            $runtimeKey = $this->runtimeKey($key, $group);
            unset($this->runtime[$group][$runtimeKey]);
        }

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKeys = [];
            $keyMap = [];
            foreach ($keys as $key) {
                $fullKey = $this->buildKey($key, $group);
                $fullKeys[] = $fullKey;
                $keyMap[$fullKey] = $key;
            }

            $adapterResults = $this->adapter->deleteMultiple($fullKeys);

            foreach ($adapterResults as $fullKey => $success) {
                $results[$keyMap[$fullKey]] = $success;
            }
        } else {
            foreach ($keys as $key) {
                $results[$key] = true;
            }
        }

        return $results;
    }

    public function increment(string $key, int $offset = 1, string $group = 'default'): int|false
    {
        $group = $this->normalizeGroup($group);
        $runtimeKey = $this->runtimeKey($key, $group);

        if (!isset($this->runtime[$group][$runtimeKey])) {
            if ($this->adapter !== null && !$this->isNonPersistent($group)) {
                $fullKey = $this->buildKey($key, $group);
                $value = $this->adapter->get($fullKey);

                if ($value !== null) {
                    $this->runtime[$group][$runtimeKey] = \unserialize($value);
                }
            }
        }

        if (!isset($this->runtime[$group][$runtimeKey]) || !\is_numeric($this->runtime[$group][$runtimeKey])) {
            return false;
        }

        $current = (int) $this->runtime[$group][$runtimeKey];
        $newValue = max(0, $current + $offset);
        $this->runtime[$group][$runtimeKey] = $newValue;

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $fullKey = $this->buildKey($key, $group);
            $this->adapter->set($fullKey, \serialize($newValue));
        }

        return $newValue;
    }

    public function decrement(string $key, int $offset = 1, string $group = 'default'): int|false
    {
        return $this->increment($key, -$offset, $group);
    }

    public function flush(): bool
    {
        $this->runtime = [];
        $this->hashState = [];

        if ($this->adapter !== null) {
            return $this->adapter->flush($this->config->prefix);
        }

        return true;
    }

    public function flushGroup(string $group): bool
    {
        $group = $this->normalizeGroup($group);
        unset($this->runtime[$group]);

        if ($this->adapter !== null && !$this->isNonPersistent($group)) {
            $groupPrefix = $this->buildGroupPrefix($group);

            return $this->adapter->flush($groupPrefix);
        }

        return true;
    }

    public function flushRuntime(): bool
    {
        $this->runtime = [];
        $this->hashState = [];

        return true;
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple',
            'flush_runtime', 'flush_group' => true,
            'hash_alloptions' => $this->config->hashStrategies !== []
                && $this->adapter instanceof HashableAdapterInterface,
            default => false,
        };
    }

    /** @param list<string> $groups */
    public function addGlobalGroups(array $groups): void
    {
        foreach ($groups as $group) {
            $this->globalGroups[$group] = true;
        }
    }

    /** @param list<string> $groups */
    public function addNonPersistentGroups(array $groups): void
    {
        foreach ($groups as $group) {
            $this->nonPersistentGroups[$group] = true;
        }
    }

    public function switchToBlog(int $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getMetrics(): ObjectCacheMetrics
    {
        return new ObjectCacheMetrics(
            hits: $this->hits,
            misses: $this->misses,
            adapterName: $this->adapter?->getName(),
        );
    }

    public function close(): void
    {
        $this->adapter?->close();
    }

    private function normalizeGroup(string $group): string
    {
        return $group === '' ? 'default' : $group;
    }

    private function isGlobal(string $group): bool
    {
        return isset($this->globalGroups[$group]);
    }

    private function isNonPersistent(string $group): bool
    {
        return isset($this->nonPersistentGroups[$group]);
    }

    private function buildKey(string $key, string $group): string
    {
        $blogId = $this->isGlobal($group) ? 0 : $this->blogId;

        return $this->config->prefix . $blogId . ':' . $group . ':' . $key;
    }

    private function buildGroupPrefix(string $group): string
    {
        $blogId = $this->isGlobal($group) ? 0 : $this->blogId;

        return $this->config->prefix . $blogId . ':' . $group . ':';
    }

    private function runtimeKey(string $key, string $group): string
    {
        $blogId = $this->isGlobal($group) ? 0 : $this->blogId;

        return $blogId . ':' . $key;
    }

    private function findHashStrategy(string $key, string $group): ?HashStrategyInterface
    {
        if (!$this->adapter instanceof HashableAdapterInterface) {
            return null;
        }

        foreach ($this->config->hashStrategies as $strategy) {
            if ($strategy->supports($key, $group)) {
                return $strategy;
            }
        }

        return null;
    }

    private function clampTtl(int $ttl): int
    {
        if ($this->config->maxTtl === null || $this->config->maxTtl <= 0) {
            return $ttl;
        }

        if ($ttl < 0) {
            return $ttl;
        }

        if ($ttl === 0 || $ttl > $this->config->maxTtl) {
            return $this->config->maxTtl;
        }

        return $ttl;
    }
}
