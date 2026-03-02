# WpPack Redis Cache

Redis adapter for the WpPack Cache Object Cache drop-in. Provides `RedisAdapter` (standalone/sentinel) and `RedisClusterAdapter` using the ext-redis extension.

## Installation

```bash
composer require wppack/redis-cache
```

Requires `ext-redis` PHP extension.

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
