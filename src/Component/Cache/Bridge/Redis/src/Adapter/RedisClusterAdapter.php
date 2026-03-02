<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AbstractAdapter;

final class RedisClusterAdapter extends AbstractAdapter
{
    private ?\RedisCluster $redis = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string
    {
        return 'redis-cluster';
    }

    protected function doGet(string $key): string|false
    {
        $result = $this->getConnection()->get($key);

        return $result === false ? false : (string) $result;
    }

    protected function doGetMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = $this->getConnection()->mget($keys);
        $results = [];

        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? false;
            $results[$key] = $value === false ? false : (string) $value;
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            $this->getConnection()->del($key);

            return true;
        }

        $redis = $this->getConnection();

        if ($ttl > 0) {
            return $redis->setex($key, $ttl, $value);
        }

        return $redis->set($key, $value);
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        if ($ttl < 0) {
            $redis = $this->getConnection();

            foreach (array_keys($values) as $key) {
                $redis->del($key);
            }

            return array_fill_keys(array_keys($values), true);
        }

        $redis = $this->getConnection();
        $results = [];

        foreach ($values as $key => $value) {
            if ($ttl > 0) {
                $results[$key] = $redis->setex($key, $ttl, $value);
            } else {
                $results[$key] = $redis->set($key, $value);
            }
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            return true;
        }

        $redis = $this->getConnection();

        if ($ttl > 0) {
            $result = $redis->set($key, $value, ['nx', 'ex' => $ttl]);
        } else {
            $result = $redis->setnx($key, $value);
        }

        return (bool) $result;
    }

    protected function doDelete(string $key): bool
    {
        return $this->getConnection()->del($key) >= 0;
    }

    protected function doDeleteMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $redis = $this->getConnection();
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $redis->del($key) >= 0;
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): int|false
    {
        $redis = $this->getConnection();

        if (!$redis->exists($key)) {
            return false;
        }

        return $redis->incrBy($key, $offset);
    }

    protected function doDecrement(string $key, int $offset = 1): int|false
    {
        $redis = $this->getConnection();

        if (!$redis->exists($key)) {
            return false;
        }

        return $redis->decrBy($key, $offset);
    }

    protected function doFlush(string $prefix = ''): bool
    {
        $redis = $this->getConnection();

        if ($prefix === '') {
            // Flush all nodes
            foreach ($redis->_masters() as $master) {
                $redis->flushDB($master);
            }

            return true;
        }

        return $this->deleteByPrefix($redis, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $redis = $this->getConnection();

            return $redis->ping('pong') === 'pong';
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->close();
            } catch (\Throwable) {
                // Ignore close errors
            }
            $this->redis = null;
        }
    }

    private function getConnection(): \RedisCluster
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        $this->redis = $this->connect();

        return $this->redis;
    }

    private function connect(): \RedisCluster
    {
        /** @var list<string> $hosts */
        $hosts = $this->connectionParams['hosts'] ?? ['127.0.0.1:6379'];
        $timeout = (float) ($this->connectionParams['timeout'] ?? 30);
        $readTimeout = (float) ($this->connectionParams['read_timeout'] ?? 0);
        $persistent = (bool) ($this->connectionParams['persistent'] ?? false);
        $password = $this->resolvePassword();
        $tls = (bool) ($this->connectionParams['tls'] ?? false);

        $seeds = [];
        foreach ($hosts as $hostSpec) {
            if ($tls) {
                $seeds[] = 'tls://' . $hostSpec;
            } else {
                $seeds[] = $hostSpec;
            }
        }

        $failover = match ($this->connectionParams['failover'] ?? 'none') {
            'error' => \RedisCluster::FAILOVER_ERROR,
            'distribute' => \RedisCluster::FAILOVER_DISTRIBUTE,
            'slaves' => \RedisCluster::FAILOVER_DISTRIBUTE_SLAVES,
            default => \RedisCluster::FAILOVER_NONE,
        };

        $redis = new \RedisCluster(
            name: $persistent ? 'wppack' : null,
            seeds: $seeds,
            timeout: $timeout,
            read_timeout: $readTimeout,
            persistent: $persistent,
            auth: $password,
        );

        $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, $failover);
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        return $redis;
    }

    private function resolvePassword(): ?string
    {
        /** @var (\Closure(): string)|null $provider */
        $provider = $this->connectionParams['credential_provider'] ?? null;

        if ($provider !== null) {
            return $provider();
        }

        /** @var string|null $auth */
        $auth = $this->connectionParams['auth'] ?? null;

        return ($auth !== null && $auth !== '') ? $auth : null;
    }

    private function deleteByPrefix(\RedisCluster $redis, string $prefix): bool
    {
        $pattern = $prefix . '*';

        foreach ($redis->_masters() as $master) {
            $cursor = null;

            do {
                $keys = $redis->scan($cursor, $master, $pattern, 100);

                if ($keys !== false && $keys !== []) {
                    foreach ($keys as $key) {
                        $redis->del($key);
                    }
                }
            } while ($cursor > 0);
        }

        return true;
    }
}
