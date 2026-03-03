<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AbstractAdapter;

final class RelayClusterAdapter extends AbstractAdapter
{
    private ?\Relay\Cluster $relay = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string
    {
        return 'relay-cluster';
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

        $relay = $this->getConnection();

        if ($ttl > 0) {
            return $relay->setex($key, $ttl, $value);
        }

        return $relay->set($key, $value);
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        if ($ttl < 0) {
            $relay = $this->getConnection();

            foreach (array_keys($values) as $key) {
                $relay->del($key);
            }

            return array_fill_keys(array_keys($values), true);
        }

        $relay = $this->getConnection();
        $results = [];

        foreach ($values as $key => $value) {
            if ($ttl > 0) {
                $results[$key] = $relay->setex($key, $ttl, $value);
            } else {
                $results[$key] = $relay->set($key, $value);
            }
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            return true;
        }

        $relay = $this->getConnection();

        if ($ttl > 0) {
            $result = $relay->set($key, $value, ['nx', 'ex' => $ttl]);
        } else {
            $result = $relay->setnx($key, $value);
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

        $relay = $this->getConnection();
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $relay->del($key) >= 0;
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): int|false
    {
        $relay = $this->getConnection();

        if (!$relay->exists($key)) {
            return false;
        }

        return $relay->incrby($key, $offset);
    }

    protected function doDecrement(string $key, int $offset = 1): int|false
    {
        $relay = $this->getConnection();

        if (!$relay->exists($key)) {
            return false;
        }

        return $relay->decrby($key, $offset);
    }

    protected function doFlush(string $prefix = ''): bool
    {
        $relay = $this->getConnection();

        if ($prefix === '') {
            foreach ($relay->_masters() as $master) {
                $relay->flushdb($master);
            }

            return true;
        }

        return $this->deleteByPrefix($relay, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $relay = $this->getConnection();

            $masters = $relay->_masters();

            if ($masters === []) {
                return false;
            }

            $relay->ping($masters[0]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->relay !== null) {
            try {
                $this->relay->close();
            } catch (\Throwable) {
                // Ignore close errors
            }
            $this->relay = null;
        }
    }

    private function getConnection(): \Relay\Cluster
    {
        if ($this->relay !== null) {
            return $this->relay;
        }

        $this->relay = $this->connect();

        return $this->relay;
    }

    private function connect(): \Relay\Cluster
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
            'error' => \Relay\Cluster::FAILOVER_ERROR,
            'distribute' => \Relay\Cluster::FAILOVER_DISTRIBUTE,
            'slaves' => \Relay\Cluster::FAILOVER_DISTRIBUTE_SLAVES,
            default => \Relay\Cluster::FAILOVER_NONE,
        };

        $relay = new \Relay\Cluster(
            name: $persistent ? 'wppack' : null,
            seeds: $seeds,
            connect_timeout: $timeout,
            command_timeout: $readTimeout,
            persistent: $persistent,
            auth: $password,
        );

        $relay->setOption(\Relay\Cluster::OPT_SLAVE_FAILOVER, $failover);
        $relay->setOption(\Relay\Relay::OPT_SERIALIZER, \Relay\Relay::SERIALIZER_NONE);

        return $relay;
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

    private function deleteByPrefix(\Relay\Cluster $relay, string $prefix): bool
    {
        $pattern = $prefix . '*';

        foreach ($relay->_masters() as $master) {
            $cursor = null;

            do {
                $keys = $relay->scan($cursor, $master, $pattern, 100);

                if ($keys !== false && $keys !== []) {
                    foreach ($keys as $key) {
                        $relay->del($key);
                    }
                }
            } while ($cursor > 0);
        }

        return true;
    }
}
