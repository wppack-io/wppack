<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AbstractHashableAdapter;

/**
 * Base adapter for ext-redis and ext-relay standalone connections.
 *
 * Subclasses only need to implement getName() and createConnection().
 */
abstract class AbstractNativeAdapter extends AbstractHashableAdapter
{
    /** @var \Redis|\Relay\Relay|null */
    private ?object $connection = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        protected readonly array $connectionParams,
    ) {}

    /**
     * Create a new native connection to the Redis server.
     *
     * @return \Redis|\Relay\Relay
     */
    abstract protected function createConnection(): object;

    protected function doGet(string $key): ?string
    {
        $result = $this->getConnection()->get($key);

        return $result === false ? null : (string) $result;
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
            $results[$key] = $value === false ? null : (string) $value;
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            $this->getConnection()->del($key);

            return true;
        }

        $connection = $this->getConnection();

        if ($ttl > 0) {
            return $connection->setex($key, $ttl, $value);
        }

        return $connection->set($key, $value);
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        if ($ttl < 0) {
            $keys = array_keys($values);

            if ($keys !== []) {
                $connection = $this->getConnection();
                $pipeline = $connection->pipeline();

                foreach ($keys as $key) {
                    $pipeline->del($key);
                }

                $pipeline->exec();
            }

            return array_fill_keys($keys, true);
        }

        $connection = $this->getConnection();

        $pipeline = $connection->pipeline();

        foreach ($values as $key => $value) {
            if ($ttl > 0) {
                $pipeline->setex($key, $ttl, $value);
            } else {
                $pipeline->set($key, $value);
            }
        }

        $pipelineResults = $pipeline->exec();

        $results = [];
        $i = 0;
        foreach ($values as $key => $value) {
            $results[$key] = (bool) ($pipelineResults[$i] ?? false);
            ++$i;
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            return true;
        }

        $connection = $this->getConnection();

        if ($ttl > 0) {
            // SET NX with expiry
            $result = $connection->set($key, $value, ['nx', 'ex' => $ttl]);
        } else {
            $result = $connection->setnx($key, $value);
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

        $connection = $this->getConnection();
        $pipeline = $connection->pipeline();

        foreach ($keys as $key) {
            $pipeline->del($key);
        }

        $pipelineResults = $pipeline->exec();
        $results = [];

        foreach ($keys as $i => $key) {
            $results[$key] = ($pipelineResults[$i] ?? 0) >= 0;
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): ?int
    {
        $connection = $this->getConnection();

        if (!$connection->exists($key)) {
            return null;
        }

        return $connection->incrby($key, $offset);
    }

    protected function doDecrement(string $key, int $offset = 1): ?int
    {
        $connection = $this->getConnection();

        if (!$connection->exists($key)) {
            return null;
        }

        return $connection->decrby($key, $offset);
    }

    protected function doHashGetAll(string $key): array
    {
        $result = $this->getConnection()->hGetAll($key);

        if ($result === false || $result === []) {
            return [];
        }

        $fields = [];
        foreach ($result as $field => $value) {
            $fields[(string) $field] = (string) $value;
        }

        return $fields;
    }

    protected function doHashGet(string $key, string $field): ?string
    {
        $result = $this->getConnection()->hGet($key, $field);

        return $result === false ? null : (string) $result;
    }

    protected function doHashSetMultiple(string $key, array $fields): bool
    {
        if ($fields === []) {
            return true;
        }

        return $this->getConnection()->hMSet($key, $fields);
    }

    protected function doHashDeleteMultiple(string $key, array $fields): bool
    {
        if ($fields === []) {
            return true;
        }

        return $this->getConnection()->hDel($key, ...$fields) >= 0;
    }

    protected function doHashDelete(string $key): bool
    {
        return $this->getConnection()->del($key) >= 0;
    }

    protected function doFlush(string $prefix = ''): bool
    {
        $connection = $this->getConnection();

        if ($prefix === '') {
            return $connection->flushdb();
        }

        return $this->deleteByPrefix($connection, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $connection = $this->getConnection();

            return $connection->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->connection !== null) {
            try {
                $this->connection->close();
            } catch (\Throwable) {
                // Ignore close errors
            }
            $this->connection = null;
        }
    }

    /**
     * @return \Redis|\Relay\Relay
     */
    protected function getConnection(): object
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $this->connection = $this->createConnection();

        return $this->connection;
    }

    protected function resolvePassword(): ?string
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

    /**
     * @param \Redis|\Relay\Relay $connection
     */
    private function deleteByPrefix(object $connection, string $prefix): bool
    {
        $cursor = null;
        $pattern = $prefix . '*';

        do {
            $keys = $connection->scan($cursor, $pattern, 100);

            if ($keys !== false && $keys !== []) {
                $connection->del(...$keys);
            }
        } while ($cursor > 0);

        return true;
    }
}
