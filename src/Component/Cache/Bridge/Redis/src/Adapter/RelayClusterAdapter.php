<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

final class RelayClusterAdapter extends AbstractNativeClusterAdapter
{
    public function getName(): string
    {
        return 'relay-cluster';
    }

    protected function createConnection(): \Relay\Cluster
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
}
