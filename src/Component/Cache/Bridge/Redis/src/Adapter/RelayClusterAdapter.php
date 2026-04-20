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

        // Relay emits a benign informational warning — 'Both name and
        // seeds provided, will use name' — every time its constructor
        // receives both arguments, even though bootstrapping a fresh
        // persistent handle requires both. Filter that one specific
        // message and let every other warning propagate.
        $relay = self::silenceRelaySeedsWarning(
            static fn(): \Relay\Cluster => new \Relay\Cluster(
                name: $persistent ? 'wppack' : null,
                seeds: $seeds,
                connect_timeout: $timeout,
                command_timeout: $readTimeout,
                persistent: $persistent,
                auth: $password,
            ),
        );

        $relay->setOption(\Relay\Cluster::OPT_SLAVE_FAILOVER, $failover);
        $relay->setOption(\Relay\Relay::OPT_SERIALIZER, self::resolveRelaySerializer($this->connectionParams['serializer'] ?? 'none'));
        $this->configureCompressor($relay, \Relay\Relay::class);

        return $relay;
    }

    /**
     * @param callable(): \Relay\Cluster $build
     */
    private static function silenceRelaySeedsWarning(callable $build): \Relay\Cluster
    {
        $previous = set_error_handler(static function (int $severity, string $message) use (&$previous): bool {
            if ($severity === \E_WARNING && str_contains($message, 'Both name and seeds provided')) {
                return true;
            }

            return \is_callable($previous) ? (bool) $previous(...\func_get_args()) : false;
        });

        try {
            return $build();
        } finally {
            restore_error_handler();
        }
    }

    private static function resolveRelaySerializer(string $name): int
    {
        return match ($name) {
            'php' => \Relay\Relay::SERIALIZER_PHP,
            'igbinary' => \Relay\Relay::SERIALIZER_IGBINARY,
            'msgpack' => \Relay\Relay::SERIALIZER_MSGPACK,
            default => \Relay\Relay::SERIALIZER_NONE,
        };
    }
}
