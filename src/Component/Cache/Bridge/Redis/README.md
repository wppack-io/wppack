# WPPack Redis Cache

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=redis_cache)](https://codecov.io/github/wppack-io/wppack)

Redis adapter for the WPPack Cache Object Cache drop-in. Supports multiple Redis client libraries within a single bridge package.

## Supported Clients

| Adapter | Client | Extension / Library |
|---------|--------|-------------------|
| `RedisAdapter` | `\Redis` | ext-redis |
| `RedisClusterAdapter` | `\RedisCluster` | ext-redis |
| `RelayAdapter` | `\Relay\Relay` | ext-relay |
| `RelayClusterAdapter` | `\Relay\Cluster` | ext-relay |
| `PredisAdapter` | `\Predis\Client` | predis/predis |

## Installation

```bash
composer require wppack/redis-cache
```

Install at least one Redis client:

```bash
# Option 1: ext-redis (recommended)
pecl install redis

# Option 2: ext-relay (highest performance with in-process caching)
pecl install relay

# Option 3: Predis (pure PHP, no extension required)
composer require predis/predis
```

## Configuration

Configure in `wp-config.php`:

```php
// Standalone
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');

// TLS
define('WPPACK_CACHE_DSN', 'rediss://127.0.0.1:6380');

// Valkey
define('WPPACK_CACHE_DSN', 'valkey://127.0.0.1:6379');

// Unix socket
define('WPPACK_CACHE_DSN', 'redis:///var/run/redis.sock');

// Cluster
define('WPPACK_CACHE_DSN', 'redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1');

// Sentinel
define('WPPACK_CACHE_DSN', 'redis:?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster');
```

### Client Selection

By default, the factory auto-detects: ext-redis → Relay → Predis (in priority order).

To force a specific client:

```php
// Via options array
define('WPPACK_CACHE_OPTIONS', ['class' => \Relay\Relay::class]);

// Via DSN query parameter
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379?class=Relay%5CRelay');
```

### Hash Alloptions

All Redis adapters implement `HashableAdapterInterface`, enabling the alloptions Hash storage feature. When `WPPACK_CACHE_HASH_ALLOPTIONS` is enabled, WordPress's `alloptions` cache key is stored as a Redis Hash instead of a serialized blob, eliminating race conditions on concurrent option updates.

```php
// wp-config.php
define('WPPACK_CACHE_HASH_ALLOPTIONS', true);
```

## Supported Schemes

| Scheme | Description |
|--------|------------|
| `redis://` | Redis TCP |
| `rediss://` | Redis TLS |
| `valkey://` | Valkey TCP |
| `valkeys://` | Valkey TLS |

## Documentation

See [docs/components/cache/](../../../../../docs/components/cache/) for full documentation.

## License

MIT
