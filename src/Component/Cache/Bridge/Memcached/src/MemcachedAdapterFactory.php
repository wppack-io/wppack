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

namespace WpPack\Component\Cache\Bridge\Memcached;

use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\Dsn;

final class MemcachedAdapterFactory implements AdapterFactoryInterface
{
    public function create(Dsn $dsn, array $options = []): AdapterInterface
    {
        $persistentId = $options['persistent_id'] ?? $dsn->getOption('persistent_id') ?? '';

        $client = new \Memcached($persistentId !== '' ? $persistentId : null);

        // Set default options (Symfony-compatible)
        $client->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
        $client->setOption(\Memcached::OPT_NO_BLOCK, true);
        $client->setOption(\Memcached::OPT_TCP_NODELAY, true);
        $client->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        // Apply user-provided Memcached options
        $this->applyOptions($client, $dsn, $options);

        // Add servers only if not already added (for persistent connections)
        if ($client->getServerList() === []) {
            $this->addServers($client, $dsn, $options);
        }

        // SASL authentication
        $username = $dsn->getUser() ?? $options['username'] ?? null;
        $password = $dsn->getPassword() ?? $options['password'] ?? null;

        if ($username !== null && $password !== null) {
            $client->setSaslAuthData($username, $password);
        }

        return new MemcachedAdapter($client);
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'memcached'
            && \extension_loaded('memcached');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function applyOptions(\Memcached $client, Dsn $dsn, array $options): void
    {
        $optionMap = [
            'timeout' => \Memcached::OPT_CONNECT_TIMEOUT,
            'retry_timeout' => \Memcached::OPT_RETRY_TIMEOUT,
        ];

        foreach ($optionMap as $key => $memcachedOption) {
            $value = $options[$key] ?? $dsn->getOption($key);

            if ($value !== null) {
                $client->setOption($memcachedOption, (int) $value);
            }
        }

        $booleanMap = [
            'tcp_nodelay' => \Memcached::OPT_TCP_NODELAY,
            'no_block' => \Memcached::OPT_NO_BLOCK,
            'binary_protocol' => \Memcached::OPT_BINARY_PROTOCOL,
            'libketama_compatible' => \Memcached::OPT_LIBKETAMA_COMPATIBLE,
        ];

        foreach ($booleanMap as $key => $memcachedOption) {
            $value = $options[$key] ?? $dsn->getOption($key);

            if ($value !== null) {
                $client->setOption($memcachedOption, (bool) $value);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addServers(\Memcached $client, Dsn $dsn, array $options): void
    {
        $hosts = $dsn->getArrayOption('host');

        if ($hosts !== []) {
            // Multi-host DSN: memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]
            $servers = [];

            foreach ($hosts as $hostEntry) {
                $parts = explode(':', $hostEntry);
                $host = $parts[0];
                $port = isset($parts[1]) ? (int) $parts[1] : 11211;
                $weight = (int) ($options['weight'] ?? $dsn->getOption('weight') ?? 0);
                $servers[] = [$host, $port, $weight];
            }

            $client->addServers($servers);

            return;
        }

        // Single-host DSN
        $host = $dsn->getHost() ?? $options['host'] ?? '127.0.0.1';
        $port = $dsn->getPort() ?? (int) ($options['port'] ?? 11211);
        $weight = (int) ($options['weight'] ?? $dsn->getOption('weight') ?? 0);

        // Unix socket: memcached:///var/run/memcached.sock
        $path = $dsn->getPath();
        if ($path !== null && $dsn->getHost() === null) {
            $client->addServer($path, 0, $weight);

            return;
        }

        $client->addServer($host, $port, $weight);
    }
}
