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

namespace WPPack\Component\Cache\Bridge\Redis\Adapter;

use WPPack\Component\Cache\Adapter\AbstractHashableAdapter;

/**
 * Base adapter for ext-redis and ext-relay standalone connections.
 *
 * Subclasses only need to implement getName() and createConnection().
 *
 * phpredis / Relay stubs type many methods as `bool|Redis|Relay\Relay`
 * because the client is returned when chaining inside MULTI / pipeline
 * mode. In this adapter we never enter MULTI mode on the shared
 * connection, so runtime returns are always scalar. Call sites narrow
 * explicitly and treat any object return as an invariant violation.
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
    ) {
        $this->asyncFlush = (bool) ($connectionParams['async_flush'] ?? false);
    }

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

        if (!\is_array($values)) {
            return \array_fill_keys($keys, null);
        }

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
        $result = $ttl > 0
            ? $connection->setex($key, $ttl, $value)
            : $connection->set($key, $value);

        return \is_bool($result) && $result;
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        if ($ttl < 0) {
            $keys = array_keys($values);

            if ($keys !== []) {
                $connection = $this->getConnection();
                $pipeline = $this->openPipeline($connection);

                foreach ($keys as $key) {
                    $pipeline->del($key);
                }

                $pipeline->exec();
            }

            return array_fill_keys($keys, true);
        }

        $connection = $this->getConnection();
        $pipeline = $this->openPipeline($connection);

        foreach ($values as $key => $value) {
            if ($ttl > 0) {
                $pipeline->setex($key, $ttl, $value);
            } else {
                $pipeline->set($key, $value);
            }
        }

        $pipelineResults = self::asList($pipeline->exec());

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
        $conn = $this->getConnection();

        return ($this->asyncFlush ? $conn->unlink($key) : $conn->del($key)) >= 0;
    }

    protected function doDeleteMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $connection = $this->getConnection();
        $pipeline = $this->openPipeline($connection);

        foreach ($keys as $key) {
            $this->asyncFlush ? $pipeline->unlink($key) : $pipeline->del($key);
        }

        $pipelineResults = self::asList($pipeline->exec());
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

        $result = $connection->incrby($key, $offset);

        return \is_int($result) ? $result : null;
    }

    protected function doDecrement(string $key, int $offset = 1): ?int
    {
        $connection = $this->getConnection();

        if (!$connection->exists($key)) {
            return null;
        }

        $result = $connection->decrby($key, $offset);

        return \is_int($result) ? $result : null;
    }

    protected function doHashGetAll(string $key): array
    {
        $result = $this->getConnection()->hGetAll($key);

        if (!\is_array($result) || $result === []) {
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

        $result = $this->getConnection()->hMSet($key, $fields);

        return \is_bool($result) && $result;
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
            $result = $connection->flushdb();

            return \is_bool($result) && $result;
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

    /**
     * Configure the compressor option on the native connection.
     *
     * @param \Redis|\Relay\Relay $connection
     * @param class-string        $constantClass \Redis or \Relay\Relay
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
     * Open a pipeline on the given connection, asserting that the client
     * returned itself (non-MULTI invariant for this adapter).
     *
     * @param \Redis|\Relay\Relay $connection
     * @return \Redis|\Relay\Relay
     */
    private function openPipeline(object $connection): object
    {
        $pipeline = $connection->pipeline();

        if (!$pipeline instanceof \Redis && !$pipeline instanceof \Relay\Relay) {
            throw new \RuntimeException('Redis pipeline() did not return the client');
        }

        return $pipeline;
    }

    /**
     * Narrow a pipeline `exec()` return to a positional result list.
     *
     * @return list<mixed>
     */
    private static function asList(mixed $result): array
    {
        return \is_array($result) ? \array_values($result) : [];
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

            if (\is_array($keys) && $keys !== []) {
                $this->asyncFlush ? $connection->unlink(...$keys) : $connection->del(...$keys);
            }
        } while ($cursor > 0);

        return true;
    }
}
