<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use Predis\Client;
use WpPack\Component\Cache\Adapter\AbstractAdapter;

final class PredisAdapter extends AbstractAdapter
{
    private ?Client $client = null;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string
    {
        return 'predis';
    }

    protected function doGet(string $key): string|false
    {
        $result = $this->getConnection()->get($key);

        return $result === null ? false : (string) $result;
    }

    protected function doGetMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = $this->getConnection()->mget($keys);
        $results = [];

        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? null;
            $results[$key] = $value === null ? false : (string) $value;
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        $client = $this->getConnection();

        if ($ttl > 0) {
            $response = $client->setex($key, $ttl, $value);
        } else {
            $response = $client->set($key, $value);
        }

        return (string) $response === 'OK';
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        $client = $this->getConnection();
        $results = [];

        $responses = $client->pipeline(function ($pipe) use ($values, $ttl): void {
            foreach ($values as $key => $value) {
                if ($ttl > 0) {
                    $pipe->setex($key, $ttl, $value);
                } else {
                    $pipe->set($key, $value);
                }
            }
        });

        $i = 0;
        foreach ($values as $key => $value) {
            $response = $responses[$i] ?? null;
            $results[$key] = $response !== null && (string) $response === 'OK';
            ++$i;
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        $client = $this->getConnection();

        if ($ttl > 0) {
            $response = $client->set($key, $value, 'EX', $ttl, 'NX');
        } else {
            $response = $client->setnx($key, $value);
        }

        return (bool) $response;
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

        $client = $this->getConnection();
        $results = [];

        $responses = $client->pipeline(function ($pipe) use ($keys): void {
            foreach ($keys as $key) {
                $pipe->del($key);
            }
        });

        foreach ($keys as $i => $key) {
            $results[$key] = ($responses[$i] ?? 0) >= 0;
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): int|false
    {
        $client = $this->getConnection();

        if (!$client->exists($key)) {
            return false;
        }

        return $client->incrby($key, $offset);
    }

    protected function doDecrement(string $key, int $offset = 1): int|false
    {
        $client = $this->getConnection();

        if (!$client->exists($key)) {
            return false;
        }

        return $client->decrby($key, $offset);
    }

    protected function doFlush(string $prefix = ''): bool
    {
        $client = $this->getConnection();

        if ($prefix === '') {
            $client->flushdb();

            return true;
        }

        return $this->deleteByPrefix($client, $prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $client = $this->getConnection();

            return (string) $client->ping() === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->client !== null) {
            try {
                $this->client->disconnect();
            } catch (\Throwable) {
                // Ignore close errors
            }
            $this->client = null;
        }
    }

    private function getConnection(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = $this->connect();

        return $this->client;
    }

    private function connect(): Client
    {
        $params = $this->connectionParams;
        $password = $this->resolvePassword();

        if ($password !== null) {
            $params['auth'] = $password;
        } else {
            unset($params['auth']);
        }

        $connectionParams = $this->buildPredisConnectionParams($params);
        $clientOptions = [];

        if (!empty($params['redis_cluster'])) {
            $clientOptions['cluster'] = 'redis';
        }

        if (!empty($params['redis_sentinel'])) {
            $clientOptions['replication'] = 'sentinel';
            $clientOptions['service'] = $params['redis_sentinel'];
        }

        return new Client($connectionParams, $clientOptions);
    }

    /**
     * Convert ext-redis style connection params to Predis format.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function buildPredisConnectionParams(array $params): array
    {
        $socket = $params['socket'] ?? null;
        $tls = (bool) ($params['tls'] ?? false);

        // Cluster mode: build array of connection params per node
        if (!empty($params['redis_cluster']) && !empty($params['hosts'])) {
            $nodes = [];
            foreach ($params['hosts'] as $hostSpec) {
                $parts = explode(':', $hostSpec);
                $node = [
                    'scheme' => $tls ? 'tls' : 'tcp',
                    'host' => $parts[0],
                    'port' => isset($parts[1]) ? (int) $parts[1] : 6379,
                ];
                if (isset($params['auth']) && $params['auth'] !== '') {
                    $node['password'] = $params['auth'];
                }
                $nodes[] = $node;
            }

            return $nodes;
        }

        // Sentinel mode: build array of sentinel node params
        if (!empty($params['redis_sentinel']) && !empty($params['sentinel_hosts'])) {
            $sentinels = [];
            foreach ($params['sentinel_hosts'] as $sentinelHost) {
                $sentinels[] = [
                    'scheme' => 'tcp',
                    'host' => $sentinelHost['host'],
                    'port' => $sentinelHost['port'],
                ];
            }

            return $sentinels;
        }

        // Standalone mode
        if ($socket !== null) {
            $connection = [
                'scheme' => 'unix',
                'path' => $socket,
            ];
        } else {
            $connection = [
                'scheme' => $tls ? 'tls' : 'tcp',
                'host' => $params['host'] ?? '127.0.0.1',
                'port' => (int) ($params['port'] ?? 6379),
            ];
        }

        if (isset($params['auth']) && $params['auth'] !== '') {
            $connection['password'] = $params['auth'];
        }

        $dbindex = (int) ($params['dbindex'] ?? 0);
        if ($dbindex > 0) {
            $connection['database'] = $dbindex;
        }

        $timeout = $params['timeout'] ?? null;
        if ($timeout !== null) {
            $connection['timeout'] = (float) $timeout;
        }

        $readTimeout = $params['read_timeout'] ?? null;
        if ($readTimeout !== null) {
            $connection['read_write_timeout'] = (float) $readTimeout;
        }

        if (!empty($params['persistent'])) {
            $connection['persistent'] = true;
        }

        return $connection;
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

    private function deleteByPrefix(Client $client, string $prefix): bool
    {
        $cursor = 0;
        $pattern = $prefix . '*';

        do {
            [$cursor, $keys] = $client->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);

            if ($keys !== []) {
                $client->del($keys);
            }
        } while ($cursor !== 0 && $cursor !== '0');

        return true;
    }
}
