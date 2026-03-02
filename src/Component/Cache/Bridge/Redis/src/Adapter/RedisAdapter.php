<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AbstractAdapter;
use WpPack\Component\Cache\Exception\AdapterException;

final class RedisAdapter extends AbstractAdapter
{
    private ?\Redis $redis = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string
    {
        return 'redis';
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

        $values = $this->getConnection()->mGet($keys);
        $results = [];

        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? false;
            $results[$key] = $value === false ? false : (string) $value;
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        $redis = $this->getConnection();

        if ($ttl > 0) {
            return $redis->setex($key, $ttl, $value);
        }

        return $redis->set($key, $value);
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        $redis = $this->getConnection();
        $results = [];

        $pipeline = $redis->pipeline();

        foreach ($values as $key => $value) {
            if ($ttl > 0) {
                $pipeline->setex($key, $ttl, $value);
            } else {
                $pipeline->set($key, $value);
            }
        }

        $pipelineResults = $pipeline->exec();

        $i = 0;
        foreach ($values as $key => $value) {
            $results[$key] = (bool) ($pipelineResults[$i] ?? false);
            ++$i;
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        $redis = $this->getConnection();

        if ($ttl > 0) {
            // SET NX with expiry
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
        $pipeline = $redis->pipeline();

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
            return $redis->flushDb();
        }

        return $this->deleteByPrefix($redis, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $redis = $this->getConnection();

            return $redis->ping() !== false;
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

    private function getConnection(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        $this->redis = $this->connect();

        return $this->redis;
    }

    private function connect(): \Redis
    {
        $redis = new \Redis();

        $host = $this->connectionParams['host'] ?? '127.0.0.1';
        $port = (int) ($this->connectionParams['port'] ?? 6379);
        $timeout = (float) ($this->connectionParams['timeout'] ?? 30);
        $readTimeout = (float) ($this->connectionParams['read_timeout'] ?? 0);
        $persistent = (bool) ($this->connectionParams['persistent'] ?? false);
        $persistentId = $this->connectionParams['persistent_id'] ?? '';
        $retryInterval = (int) ($this->connectionParams['retry_interval'] ?? 0);
        $password = $this->connectionParams['auth'] ?? null;
        $dbindex = (int) ($this->connectionParams['dbindex'] ?? 0);
        $tls = (bool) ($this->connectionParams['tls'] ?? false);
        $tcpKeepalive = (float) ($this->connectionParams['tcp_keepalive'] ?? 0);
        $socket = $this->connectionParams['socket'] ?? null;

        // Sentinel support
        $sentinelService = $this->connectionParams['redis_sentinel'] ?? null;
        $sentinelHosts = $this->connectionParams['sentinel_hosts'] ?? null;

        if ($sentinelService !== null && $sentinelHosts !== null) {
            return $this->connectViaSentinel(
                $sentinelHosts,
                $sentinelService,
                $password,
                $dbindex,
                $timeout,
                $readTimeout,
                $persistent,
                $retryInterval,
            );
        }

        $connectHost = $socket ?? ($tls ? 'tls://' . $host : $host);
        $connectPort = $socket !== null ? 0 : $port;

        if ($persistent) {
            $redis->pconnect($connectHost, $connectPort, $timeout, $persistentId, $retryInterval);
        } else {
            $redis->connect($connectHost, $connectPort, $timeout, null, $retryInterval);
        }

        if ($readTimeout > 0) {
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, $readTimeout);
        }

        if ($tcpKeepalive > 0) {
            $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $tcpKeepalive);
        }

        if ($password !== null && $password !== '') {
            $redis->auth($password);
        }

        if ($dbindex > 0) {
            $redis->select($dbindex);
        }

        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        return $redis;
    }

    /**
     * @param list<array{host: string, port: int}> $sentinelHosts
     */
    private function connectViaSentinel(
        array $sentinelHosts,
        string $service,
        ?string $password,
        int $dbindex,
        float $timeout,
        float $readTimeout,
        bool $persistent,
        int $retryInterval,
    ): \Redis {
        $masterInfo = null;

        foreach ($sentinelHosts as $sentinelHost) {
            try {
                $sentinel = new \Redis();
                $sentinel->connect($sentinelHost['host'], $sentinelHost['port'], $timeout);

                $result = $sentinel->rawCommand('SENTINEL', 'get-master-addr-by-name', $service);

                if (\is_array($result) && \count($result) >= 2) {
                    $masterInfo = ['host' => (string) $result[0], 'port' => (int) $result[1]];
                    $sentinel->close();
                    break;
                }

                $sentinel->close();
            } catch (\Throwable) {
                continue;
            }
        }

        if ($masterInfo === null) {
            throw new AdapterException(sprintf('No master found for Sentinel service "%s".', $service));
        }

        $redis = new \Redis();

        if ($persistent) {
            $redis->pconnect($masterInfo['host'], $masterInfo['port'], $timeout, '', $retryInterval);
        } else {
            $redis->connect($masterInfo['host'], $masterInfo['port'], $timeout, null, $retryInterval);
        }

        if ($readTimeout > 0) {
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, $readTimeout);
        }

        if ($password !== null && $password !== '') {
            $redis->auth($password);
        }

        if ($dbindex > 0) {
            $redis->select($dbindex);
        }

        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        return $redis;
    }

    private function deleteByPrefix(\Redis $redis, string $prefix): bool
    {
        $cursor = null;
        $pattern = $prefix . '*';

        do {
            $keys = $redis->scan($cursor, $pattern, 100);

            if ($keys !== false && $keys !== []) {
                $redis->del(...$keys);
            }
        } while ($cursor > 0);

        return true;
    }
}
