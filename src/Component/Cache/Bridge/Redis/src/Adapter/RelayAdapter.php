<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AbstractAdapter;
use WpPack\Component\Cache\Exception\AdapterException;

final class RelayAdapter extends AbstractAdapter
{
    private ?\Relay\Relay $relay = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string
    {
        return 'relay';
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
            $keys = array_keys($values);

            if ($keys !== []) {
                $relay = $this->getConnection();
                $pipeline = $relay->pipeline();

                foreach ($keys as $key) {
                    $pipeline->del($key);
                }

                $pipeline->exec();
            }

            return array_fill_keys($keys, true);
        }

        $relay = $this->getConnection();
        $results = [];

        $pipeline = $relay->pipeline();

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
        $pipeline = $relay->pipeline();

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
            return $relay->flushdb();
        }

        return $this->deleteByPrefix($relay, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $relay = $this->getConnection();

            return $relay->ping() !== false;
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

    private function getConnection(): \Relay\Relay
    {
        if ($this->relay !== null) {
            return $this->relay;
        }

        $this->relay = $this->connect();

        return $this->relay;
    }

    private function connect(): \Relay\Relay
    {
        $relay = new \Relay\Relay();

        $host = $this->connectionParams['host'] ?? '127.0.0.1';
        $port = (int) ($this->connectionParams['port'] ?? 6379);
        $timeout = (float) ($this->connectionParams['timeout'] ?? 30);
        $readTimeout = (float) ($this->connectionParams['read_timeout'] ?? 0);
        $persistent = (bool) ($this->connectionParams['persistent'] ?? false);
        $persistentId = $this->connectionParams['persistent_id'] ?? '';
        $retryInterval = (int) ($this->connectionParams['retry_interval'] ?? 0);
        $password = $this->resolvePassword();
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
            $relay->pconnect($connectHost, $connectPort, $timeout, $persistentId, $retryInterval);
        } else {
            $relay->connect($connectHost, $connectPort, $timeout, null, $retryInterval);
        }

        if ($readTimeout > 0) {
            $relay->setOption(\Relay\Relay::OPT_READ_TIMEOUT, $readTimeout);
        }

        if ($tcpKeepalive > 0) {
            $relay->setOption(\Relay\Relay::OPT_TCP_KEEPALIVE, $tcpKeepalive);
        }

        if ($password !== null && $password !== '') {
            $relay->auth($password);
        }

        if ($dbindex > 0) {
            $relay->select($dbindex);
        }

        $relay->setOption(\Relay\Relay::OPT_SERIALIZER, \Relay\Relay::SERIALIZER_NONE);

        return $relay;
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
    ): \Relay\Relay {
        $masterInfo = null;

        foreach ($sentinelHosts as $sentinelHost) {
            try {
                $sentinel = new \Relay\Relay();
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

        $relay = new \Relay\Relay();

        if ($persistent) {
            $relay->pconnect($masterInfo['host'], $masterInfo['port'], $timeout, '', $retryInterval);
        } else {
            $relay->connect($masterInfo['host'], $masterInfo['port'], $timeout, null, $retryInterval);
        }

        if ($readTimeout > 0) {
            $relay->setOption(\Relay\Relay::OPT_READ_TIMEOUT, $readTimeout);
        }

        if ($password !== null && $password !== '') {
            $relay->auth($password);
        }

        if ($dbindex > 0) {
            $relay->select($dbindex);
        }

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

    private function deleteByPrefix(\Relay\Relay $relay, string $prefix): bool
    {
        $cursor = null;
        $pattern = $prefix . '*';

        do {
            $keys = $relay->scan($cursor, $pattern, 100);

            if ($keys !== false && $keys !== []) {
                $relay->del(...$keys);
            }
        } while ($cursor > 0);

        return true;
    }
}
