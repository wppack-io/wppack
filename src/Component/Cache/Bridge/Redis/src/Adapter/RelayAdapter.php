<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Exception\AdapterException;

final class RelayAdapter extends AbstractNativeAdapter
{
    public function getName(): string
    {
        return 'relay';
    }

    protected function createConnection(): \Relay\Relay
    {
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

        $relay = new \Relay\Relay();

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
        $this->configureCompressor($relay, \Relay\Relay::class);

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
        $this->configureCompressor($relay, \Relay\Relay::class);

        return $relay;
    }
}
