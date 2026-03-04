<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

final class RedisClusterAdapter extends AbstractNativeClusterAdapter
{
    public function getName(): string
    {
        return 'redis-cluster';
    }

    protected function createConnection(): \RedisCluster
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
}
