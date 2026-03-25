# wppack/redis-cache-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=redis_cache_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for Redis-based object cache with ElastiCache IAM auth support. Manages the `object-cache.php` drop-in installation and registers cache services into the DI container.

## Architecture

RedisCachePlugin is a thin bootstrap layer on top of provider-agnostic components:

- **Object cache drop-in** (`object-cache.php`) is provided by `wppack/cache`
- **Redis adapter** is provided by `wppack/redis-cache` (`RedisAdapterFactory`)
- **ElastiCache IAM authentication** is provided by `wppack/elasticache-auth`
- **RedisCachePlugin** provides only: plugin bootstrap, drop-in lifecycle management (install/uninstall), environment-based configuration, and DI service registration

## Installation

```bash
composer require wppack/redis-cache-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- Redis server (or Amazon ElastiCache / Valkey)
- One of: `ext-redis`, `ext-relay`, or `predis/predis`

## Configuration

Set environment variables or constants in `wp-config.php`:

```php
// Required
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');

// Optional
define('WPPACK_CACHE_PREFIX', 'wp:');              // Key prefix (default: 'wp:')
define('WPPACK_CACHE_MAX_TTL', 86400);             // Max TTL in seconds
define('WPPACK_CACHE_HASH_ALLOPTIONS', true);      // Use Redis HASH for alloptions
define('WPPACK_CACHE_ASYNC_FLUSH', true);           // Use UNLINK instead of DEL
define('WPPACK_CACHE_COMPRESSION', 'zstd');         // 'none', 'zstd', 'lz4', 'lzf'
define('WPPACK_CACHE_ENABLED', false);              // Disable drop-in (kill switch)
```

### ElastiCache IAM Authentication

IAM authentication is enabled via DSN query parameters — no plugin-specific configuration required:

```php
define('WPPACK_CACHE_DSN', 'rediss://clustername.cache.amazonaws.com:6379?iam_auth=1&iam_region=ap-northeast-1&iam_user_id=my-user');
```

- TLS is required (`rediss://` or `valkeys://` scheme)
- `RedisAdapterFactory` detects `iam_auth=1` and uses `ElastiCacheIamTokenGenerator` automatically

### Valkey Support

Amazon ElastiCache Valkey is supported with the `valkeys://` scheme:

```php
define('WPPACK_CACHE_DSN', 'valkeys://clustername.cache.amazonaws.com:6379?iam_auth=1&iam_region=ap-northeast-1');
```

### Kill Switch

On read-only filesystems (Lambda, containers), the drop-in cannot be deleted on plugin deactivation. Set `WPPACK_CACHE_ENABLED` to `false` to disable external cache connections without removing the file:

```php
define('WPPACK_CACHE_ENABLED', false); // Falls back to in-memory cache
```

## Drop-in Management

- **Activation**: Copies `object-cache.php` from `wppack/cache` to `wp-content/`
- **Deactivation**: Removes `wp-content/object-cache.php` only if it contains the WpPack signature (`WpPack Object Cache Drop-in`)

## DI Services

The plugin registers the following services into the container:

| Service | Description |
|---------|-------------|
| `RedisCacheConfiguration` | Environment-based configuration via `fromEnvironment()` factory |
| `ObjectCache` | References `$GLOBALS['wp_object_cache']` (initialized by the drop-in) |
| `CacheManager` | WordPress cache API wrapper for DI-aware components |

## Documentation

See [full documentation](../../docs/plugins/redis-cache-plugin.md) for details.

## License

MIT
