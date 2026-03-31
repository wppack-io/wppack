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

namespace WpPack\Component\Cache\Bridge\Redis\Adapter;

use WpPack\Component\Cache\Adapter\AdapterDefinition;
use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Adapter\AdapterField;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator;
use WpPack\Component\Cache\Exception\AdapterException;
use WpPack\Component\Cache\Exception\UnsupportedSchemeException;

final class RedisAdapterFactory implements AdapterFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['redis', 'rediss', 'valkey', 'valkeys'];

    /**
     * @var list<array{label: string, value: string}>
     *
     * @see https://docs.aws.amazon.com/AmazonElastiCache/latest/red-ug/Endpoints.html
     */
    private const ELASTICACHE_REGION_OPTIONS = [
        ['label' => 'us-east-1 (N. Virginia)', 'value' => 'us-east-1'],
        ['label' => 'us-east-2 (Ohio)', 'value' => 'us-east-2'],
        ['label' => 'us-west-1 (N. California)', 'value' => 'us-west-1'],
        ['label' => 'us-west-2 (Oregon)', 'value' => 'us-west-2'],
        ['label' => 'af-south-1 (Cape Town)', 'value' => 'af-south-1'],
        ['label' => 'ap-east-1 (Hong Kong)', 'value' => 'ap-east-1'],
        ['label' => 'ap-south-1 (Mumbai)', 'value' => 'ap-south-1'],
        ['label' => 'ap-south-2 (Hyderabad)', 'value' => 'ap-south-2'],
        ['label' => 'ap-northeast-1 (Tokyo)', 'value' => 'ap-northeast-1'],
        ['label' => 'ap-northeast-2 (Seoul)', 'value' => 'ap-northeast-2'],
        ['label' => 'ap-northeast-3 (Osaka)', 'value' => 'ap-northeast-3'],
        ['label' => 'ap-southeast-1 (Singapore)', 'value' => 'ap-southeast-1'],
        ['label' => 'ap-southeast-2 (Sydney)', 'value' => 'ap-southeast-2'],
        ['label' => 'ap-southeast-3 (Jakarta)', 'value' => 'ap-southeast-3'],
        ['label' => 'ap-southeast-4 (Melbourne)', 'value' => 'ap-southeast-4'],
        ['label' => 'ca-central-1 (Canada)', 'value' => 'ca-central-1'],
        ['label' => 'ca-west-1 (Calgary)', 'value' => 'ca-west-1'],
        ['label' => 'eu-central-1 (Frankfurt)', 'value' => 'eu-central-1'],
        ['label' => 'eu-central-2 (Zurich)', 'value' => 'eu-central-2'],
        ['label' => 'eu-north-1 (Stockholm)', 'value' => 'eu-north-1'],
        ['label' => 'eu-south-1 (Milan)', 'value' => 'eu-south-1'],
        ['label' => 'eu-south-2 (Spain)', 'value' => 'eu-south-2'],
        ['label' => 'eu-west-1 (Ireland)', 'value' => 'eu-west-1'],
        ['label' => 'eu-west-2 (London)', 'value' => 'eu-west-2'],
        ['label' => 'eu-west-3 (Paris)', 'value' => 'eu-west-3'],
        ['label' => 'il-central-1 (Tel Aviv)', 'value' => 'il-central-1'],
        ['label' => 'me-central-1 (UAE)', 'value' => 'me-central-1'],
        ['label' => 'me-south-1 (Bahrain)', 'value' => 'me-south-1'],
        ['label' => 'sa-east-1 (São Paulo)', 'value' => 'sa-east-1'],
        ['label' => 'us-gov-east-1 (GovCloud US-East)', 'value' => 'us-gov-east-1'],
        ['label' => 'us-gov-west-1 (GovCloud US-West)', 'value' => 'us-gov-west-1'],
    ];

    public static function definitions(): array
    {
        $standaloneFields = [
            new AdapterField('host', 'Host', default: '127.0.0.1', dsnPart: 'host'),
            new AdapterField('port', 'Port', type: 'number', default: '6379', dsnPart: 'port', maxWidth: '120px'),
            new AdapterField('password', 'Password', type: 'password', dsnPart: 'password'),
            new AdapterField('database', 'Database', type: 'number', default: '0', dsnPart: 'option:dbindex', maxWidth: '80px'),
        ];

        return [
            new AdapterDefinition(
                scheme: 'redis',
                label: 'Redis Standalone',
                fields: $standaloneFields,
            ),
            new AdapterDefinition(
                scheme: 'rediss',
                label: 'Redis Standalone (TLS)',
                fields: $standaloneFields,
            ),
            new AdapterDefinition(
                scheme: 'rediss-iam',
                label: 'ElastiCache / Valkey (IAM Auth)',
                fields: [
                    new AdapterField('host', 'Endpoint', required: true, dsnPart: 'host', help: 'e.g., my-cluster.xxxxx.apne1.cache.amazonaws.com'),
                    new AdapterField('port', 'Port', type: 'number', default: '6379', dsnPart: 'port', maxWidth: '120px'),
                    new AdapterField('iamRegion', 'Region', required: true, dsnPart: 'option:iam_region', options: self::ELASTICACHE_REGION_OPTIONS, maxWidth: '280px'),
                    new AdapterField('iamUserId', 'IAM User ID', required: true, dsnPart: 'option:iam_user_id'),
                ],
                dsnScheme: 'rediss',
                extraOptions: ['iam_auth' => '1'],
            ),
            new AdapterDefinition(
                scheme: 'redis-cluster',
                label: 'Redis Cluster',
                fields: [
                    new AdapterField('nodes', 'Nodes', type: 'textarea', required: true, help: 'One host:port per line', dsnPart: 'hosts'),
                    new AdapterField('password', 'Password', type: 'password', dsnPart: 'password'),
                ],
                dsnScheme: 'redis',
                extraOptions: ['redis_cluster' => '1'],
            ),
            new AdapterDefinition(
                scheme: 'redis-sentinel',
                label: 'Redis Sentinel',
                fields: [
                    new AdapterField('sentinelNodes', 'Sentinel Nodes', type: 'textarea', required: true, help: 'One host:port per line', dsnPart: 'hosts'),
                    new AdapterField('masterName', 'Master Name', required: true, dsnPart: 'option:redis_sentinel'),
                    new AdapterField('password', 'Password', type: 'password', dsnPart: 'password'),
                ],
                dsnScheme: 'redis',
            ),
        ];
    }

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
