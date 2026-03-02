<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator;
use WpPack\Component\Cache\Exception\AdapterException;
use WpPack\Component\Cache\Exception\UnsupportedSchemeException;

final class RedisAdapterFactory implements AdapterFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['redis', 'rediss', 'valkey', 'valkeys'];

    public function create(Dsn $dsn, array $options = []): AdapterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'Redis', self::SUPPORTED_SCHEMES);
        }

        $params = $this->buildConnectionParams($dsn, $options);
        $class = $options['class'] ?? $dsn->getOption('class') ?? null;
        $isCluster = !empty($params['redis_cluster']);

        // Explicit client class specified
        if ($class !== null) {
            return $this->createForClient(ltrim($class, '\\'), $params);
        }

        // Auto-detection: ext-redis → Relay → Predis
        if (\extension_loaded('redis')) {
            return $isCluster ? new RedisClusterAdapter($params) : new RedisAdapter($params);
        }

        if (\extension_loaded('relay')) {
            return $isCluster ? new RelayClusterAdapter($params) : new RelayAdapter($params);
        }

        if (\class_exists(\Predis\Client::class)) {
            return new PredisAdapter($params);
        }

        throw new AdapterException('No Redis client library found. Install ext-redis, ext-relay, or predis/predis.');
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), self::SUPPORTED_SCHEMES, true)
            && (\extension_loaded('redis') || \extension_loaded('relay') || \class_exists(\Predis\Client::class));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createForClient(string $class, array $params): AdapterInterface
    {
        $isCluster = !empty($params['redis_cluster']);

        return match ($class) {
            'Redis' => new RedisAdapter($params),
            'RedisCluster' => new RedisClusterAdapter($params),
            'Relay\Relay' => new RelayAdapter($params),
            'Relay\Cluster' => new RelayClusterAdapter($params),
            'Predis\Client', 'Predis\ClientInterface' => new PredisAdapter($params),
            default => throw new AdapterException(sprintf('Unsupported Redis client class "%s".', $class)),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildConnectionParams(Dsn $dsn, array $options): array
    {
        $scheme = $dsn->getScheme();
        $tls = $scheme === 'rediss' || $scheme === 'valkeys';

        $params = [
            'tls' => $tls,
        ];

        // Host: from DSN host or from host[] query param for multi-host DSNs
        $hostArray = $dsn->getArrayOption('host');
        if ($hostArray !== []) {
            // Multi-host DSN (Cluster / Sentinel)
            $params['hosts'] = $this->parseHosts($hostArray);
        } else {
            $host = $dsn->getHost();
            $port = $dsn->getPort();

            if ($host !== null) {
                $params['host'] = $host;
                if ($port !== null) {
                    $params['port'] = $port;
                }
            }
        }

        // Socket from path (redis:///var/run/redis.sock)
        $path = $dsn->getPath();
        if ($path !== null && $dsn->getHost() === null) {
            // No-host DSN with path = socket
            $params['socket'] = $path;
        } elseif ($path !== null && $dsn->getHost() !== null) {
            // Path after host = DB index or socket
            $trimmedPath = ltrim($path, '/');
            if (is_numeric($trimmedPath)) {
                $params['dbindex'] = (int) $trimmedPath;
            } elseif (str_contains($path, '.sock')) {
                $params['socket'] = $path;
            }
        }

        // Auth from URL user or query param
        $auth = $dsn->getUser();
        if ($auth !== null && $auth !== '') {
            $params['auth'] = $auth;
        }
        $authOption = $dsn->getOption('auth');
        if ($authOption !== null && $authOption !== '') {
            $params['auth'] = $authOption;
        }

        // Numeric/boolean options from DSN query params
        $numericOptions = ['timeout', 'read_timeout', 'retry_interval', 'tcp_keepalive', 'dbindex', 'persistent'];
        foreach ($numericOptions as $optName) {
            $value = $dsn->getOption($optName);
            if ($value !== null) {
                $params[$optName] = $value;
            }
        }

        // String options
        $stringOptions = ['persistent_id', 'failover', 'iam_region', 'iam_user_id'];
        foreach ($stringOptions as $optName) {
            $value = $dsn->getOption($optName);
            if ($value !== null) {
                $params[$optName] = $value;
            }
        }

        // iam_auth (boolean/string)
        $iamAuthValue = $dsn->getOption('iam_auth');
        if ($iamAuthValue !== null) {
            $params['iam_auth'] = $iamAuthValue;
        }

        // Cluster / Sentinel flags
        $redisCluster = $dsn->getOption('redis_cluster');
        if ($redisCluster !== null && $redisCluster !== '' && $redisCluster !== '0' && $redisCluster !== 'false') {
            $params['redis_cluster'] = true;
        }

        $redisSentinel = $dsn->getOption('redis_sentinel');
        if ($redisSentinel !== null && $redisSentinel !== '') {
            $params['redis_sentinel'] = $redisSentinel;

            // Build sentinel hosts
            if (isset($params['hosts'])) {
                $sentinelHosts = [];
                foreach ($params['hosts'] as $hostSpec) {
                    $parts = explode(':', $hostSpec);
                    $sentinelHosts[] = [
                        'host' => $parts[0],
                        'port' => isset($parts[1]) ? (int) $parts[1] : 26379,
                    ];
                }
                $params['sentinel_hosts'] = $sentinelHosts;
            }
        }

        // Options array overrides DSN query params (except 'class' which is handled separately)
        foreach ($options as $key => $value) {
            if ($key === 'class') {
                continue;
            }
            $params[$key] = $value;
        }

        // IAM authentication shortcut
        $iamAuth = $params['iam_auth'] ?? null;

        if ($iamAuth !== null && $iamAuth !== '' && $iamAuth !== '0' && $iamAuth !== 'false' && $iamAuth !== false) {
            if (!class_exists(ElastiCacheIamTokenGenerator::class)) {
                throw new AdapterException(
                    'IAM authentication requires the wppack/elasticache-auth package. '
                    . 'Run: composer require wppack/elasticache-auth',
                );
            }

            $iamRegion = $params['iam_region']
                ?? throw new AdapterException('iam_region is required when iam_auth is enabled.');
            $iamUserId = $params['iam_user_id']
                ?? throw new AdapterException('iam_user_id is required when iam_auth is enabled.');

            if (!$tls) {
                throw new AdapterException('IAM authentication requires TLS. Use rediss:// or valkeys:// scheme.');
            }

            $host = $params['host']
                ?? throw new AdapterException('Host is required for IAM authentication.');
            $port = (int) ($params['port'] ?? 6379);

            $generator = new ElastiCacheIamTokenGenerator($iamRegion, $iamUserId);
            $params['credential_provider'] = $generator->createProvider($host . ':' . $port);

            // Remove static auth if set (IAM replaces it)
            unset($params['auth']);
        }

        return $params;
    }

    /**
     * @param list<string> $hostArray
     * @return list<string>
     */
    private function parseHosts(array $hostArray): array
    {
        $hosts = [];

        foreach ($hostArray as $hostSpec) {
            // Handle socket hosts: /var/run/redis.sock: (trailing colon)
            $hostSpec = rtrim($hostSpec, ':');
            $hosts[] = $hostSpec;
        }

        return $hosts;
    }
}
