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

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AbstractHashableAdapter;

/**
 * Base adapter for ext-redis and ext-relay cluster connections.
 *
 * Subclasses only need to implement getName() and createConnection().
 */
abstract class AbstractNativeClusterAdapter extends AbstractHashableAdapter
{
    /** @var \RedisCluster|\Relay\Cluster|null */
    private ?object $connection = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        protected readonly array $connectionParams,
    ) {
        $this->asyncFlush = (bool) ($connectionParams['async_flush'] ?? false);
    }

    /**
     * Create a new native cluster connection.
     *
     * @return \RedisCluster|\Relay\Cluster
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
            $connection = $this->getConnection();

            foreach (array_keys($values) as $key) {
                $connection->del($key);
            }

            return array_fill_keys(array_keys($values), true);
        }

        $connection = $this->getConnection();
        $results = [];

        foreach ($values as $key => $value) {
            if ($ttl > 0) {
                $results[$key] = $connection->setex($key, $ttl, $value);
            } else {
                $results[$key] = $connection->set($key, $value);
            }
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
            $result = $connection->set($key, $value, ['nx', 'ex' => $ttl]);
        } else {
            $result = $connection->setnx($key, $value);
        }

        return (bool) $result;
    }

    protected function doDelete(string $key): bool
    {
        $conn = $this->getConnection();

        return ($this->asyncFlush ? $conn->unlink($key) : $conn->del($key)) >= 0;
    }

    protected function doDeleteMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $connection = $this->getConnection();
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = ($this->asyncFlush ? $connection->unlink($key) : $connection->del($key)) >= 0;
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
        $conn = $this->getConnection();

        return ($this->asyncFlush ? $conn->unlink($key) : $conn->del($key)) >= 0;
    }

    protected function doFlush(string $prefix = ''): bool
    {
        $connection = $this->getConnection();

        if ($prefix === '') {
            foreach ($connection->_masters() as $master) {
                $connection->flushdb($master);
            }

            return true;
        }

        return $this->deleteByPrefix($connection, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $connection = $this->getConnection();

            $masters = $connection->_masters();

            if ($masters === []) {
                return false;
            }

            $connection->ping($masters[0]);

            return true;
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
     * @return \RedisCluster|\Relay\Cluster
     */
    protected function getConnection(): object
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $this->connection = $this->createConnection();

        return $this->connection;
    }

    /**
     * Configure the compressor option on the native connection.
     *
     * @param \RedisCluster|\Relay\Cluster $connection
     * @param class-string                 $constantClass \Redis or \Relay\Relay
     */
    protected function configureCompressor(object $connection, string $constantClass): void
    {
        $compression = $this->connectionParams['compression'] ?? null;

        if ($compression === null || $compression === 'none') {
            return;
        }

        $compressorConst = $constantClass . '::COMPRESSOR_' . strtoupper($compression);
        $optConst = $constantClass . '::OPT_COMPRESSOR';

        if (!\defined($compressorConst) || !\defined($optConst)) {
            return;
        }

        $connection->setOption(\constant($optConst), \constant($compressorConst));
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
     * @param \RedisCluster|\Relay\Cluster $connection
     */
    private function deleteByPrefix(object $connection, string $prefix): bool
    {
        $pattern = $prefix . '*';

        foreach ($connection->_masters() as $master) {
            $cursor = null;

            do {
                $keys = $connection->scan($cursor, $master, $pattern, 100);

                if ($keys !== false && $keys !== []) {
                    foreach ($keys as $key) {
                        $this->asyncFlush ? $connection->unlink($key) : $connection->del($key);
                    }
                }
            } while ($cursor > 0);
        }

        return true;
    }
}
