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

use AsyncAws\Core\Credentials\Credentials;
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

    /** @var list<array{label: string, value: string}> */
    private const CLIENT_OPTIONS = [
        ['label' => 'Auto-detect', 'value' => ''],
        ['label' => 'PhpRedis (ext-redis)', 'value' => 'Redis'],
        ['label' => 'Relay (ext-relay)', 'value' => 'Relay\\Relay'],
        ['label' => 'Predis', 'value' => 'Predis\\Client'],
    ];

    public static function definitions(): array
    {
        $clientField = new AdapterField('class', 'Client Library', options: self::CLIENT_OPTIONS, dsnPart: 'option:class', maxWidth: '200px');
        $iamAuthField = new AdapterField('iamAuth', 'Use IAM Authentication', type: 'boolean', dsnPart: 'option:iam_auth', help: 'For Amazon ElastiCache / Valkey with IAM-based access control');
        $iamAccessKeyField = new AdapterField('iamAccessKey', 'Access Key ID', dsnPart: 'option:iam_access_key', conditional: 'iamAuth', help: 'Leave empty to use IAM role');
        $iamSecretKeyField = new AdapterField('iamSecretKey', 'Secret Access Key', type: 'password', dsnPart: 'option:iam_secret_key', conditional: 'iamAuth');
        $iamUserIdField = new AdapterField('iamUserId', 'ElastiCache User ID', required: true, dsnPart: 'option:iam_user_id', conditional: 'iamAuth', help: 'The user ID defined in ElastiCache user management');
        $passwordField = new AdapterField('password', 'Password', type: 'password', dsnPart: 'password', conditional: '!iamAuth');

        return [
            new AdapterDefinition(
                scheme: 'redis',
                label: 'Redis Standalone',
                fields: [
                    new AdapterField('host', 'Host', default: '127.0.0.1', dsnPart: 'host'),
                    new AdapterField('port', 'Port', type: 'number', default: '6379', dsnPart: 'port', maxWidth: '120px'),
                    new AdapterField('password', 'Password', type: 'password', dsnPart: 'password'),
                    new AdapterField('database', 'Database', type: 'number', default: '0', dsnPart: 'option:dbindex', maxWidth: '80px'),
                    $clientField,
                ],
            ),
            new AdapterDefinition(
                scheme: 'rediss',
                label: 'Redis Standalone (TLS)',
                fields: [
                    new AdapterField('host', 'Host', default: '127.0.0.1', dsnPart: 'host'),
                    new AdapterField('port', 'Port', type: 'number', default: '6379', dsnPart: 'port', maxWidth: '120px'),
                    $iamAuthField,
                    $passwordField,
                    $iamAccessKeyField,
                    $iamSecretKeyField,
                    $iamUserIdField,
                    new AdapterField('database', 'Database', type: 'number', default: '0', dsnPart: 'option:dbindex', maxWidth: '80px'),
                    $clientField,
                ],
            ),
            new AdapterDefinition(
                scheme: 'redis-cluster',
                label: 'Redis Cluster',
                fields: [
                    new AdapterField('nodes', 'Nodes', type: 'textarea', required: true, help: 'One host:port per line', dsnPart: 'hosts'),
                    new AdapterField('password', 'Password', type: 'password', dsnPart: 'password'),
                    $clientField,
                ],
                dsnScheme: 'redis',
                extraOptions: ['redis_cluster' => '1'],
            ),
            new AdapterDefinition(
                scheme: 'rediss-cluster',
                label: 'Redis Cluster (TLS)',
                fields: [
                    new AdapterField('nodes', 'Nodes', type: 'textarea', required: true, help: 'One host:port per line', dsnPart: 'hosts'),
                    $iamAuthField,
                    $passwordField,
                    $iamAccessKeyField,
                    $iamSecretKeyField,
                    $iamUserIdField,
                    $clientField,
                ],
                dsnScheme: 'rediss',
                extraOptions: ['redis_cluster' => '1'],
            ),
            new AdapterDefinition(
                scheme: 'redis-sentinel',
                label: 'Redis Sentinel',
                fields: [
                    new AdapterField('sentinelNodes', 'Sentinel Nodes', type: 'textarea', required: true, help: 'One host:port per line', dsnPart: 'hosts'),
                    new AdapterField('masterName', 'Master Name', required: true, dsnPart: 'option:redis_sentinel'),
                    new AdapterField('password', 'Password', type: 'password', dsnPart: 'password'),
                    $clientField,
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

            if (!$tls) {
                throw new AdapterException('IAM authentication requires TLS. Use rediss:// or valkeys:// scheme.');
            }

            $host = $params['host']
                ?? throw new AdapterException('Host is required for IAM authentication.');
            $iamUserId = $params['iam_user_id']
                ?? throw new AdapterException('iam_user_id is required when iam_auth is enabled.');
            $iamRegion = $params['iam_region'] ?? self::extractRegionFromHost($host);
            $port = (int) ($params['port'] ?? 6379);

            $iamAccessKey = $params['iam_access_key'] ?? null;
            $iamSecretKey = $params['iam_secret_key'] ?? null;
            $credentialProvider = null;
            if (\is_string($iamAccessKey) && $iamAccessKey !== '' && \is_string($iamSecretKey) && $iamSecretKey !== '') {
                $credentialProvider = new Credentials($iamAccessKey, $iamSecretKey);
            }

            $generator = new ElastiCacheIamTokenGenerator($iamRegion, $iamUserId, $credentialProvider);
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

    /**
     * Extract AWS region from ElastiCache/Valkey endpoint hostname.
     *
     * e.g., "my-cluster.xxxxx.apne1.cache.amazonaws.com" → "ap-northeast-1"
     */
    private static function extractRegionFromHost(string $host): string
    {
        // ElastiCache endpoints: xxx.yyy.{region}.cache.amazonaws.com
        // Serverless: xxx.serverless.{region}.cache.amazonaws.com
        if (preg_match('/\.([a-z]{2}-[a-z]+-\d+)\.cache\.amazonaws\.com$/', $host, $matches)) {
            return $matches[1];
        }

        // Valkey/MemoryDB: xxx.{region}.memorydb.amazonaws.com
        if (preg_match('/\.([a-z]{2}-[a-z]+-\d+)\.memorydb\.amazonaws\.com$/', $host, $matches)) {
            return $matches[1];
        }

        throw new AdapterException(sprintf(
            'Cannot detect region from host "%s". Specify iam_region explicitly.',
            $host,
        ));
    }
}
