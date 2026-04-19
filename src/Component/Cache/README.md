# WPPack Cache

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=cache)](https://codecov.io/github/wppack-io/wppack)

Object-oriented wrapper for the WordPress Object Cache API. Provides a simple manager class for cache operations with group support.

## Installation

```bash
composer require wppack/cache
```

## Usage

```php
use WPPack\Component\Cache\CacheManager;

$cache = new CacheManager();

// Store and retrieve data
$cache->set('user_profile_123', $userData, 'my_app', 3600);
$data = $cache->get('user_profile_123', 'my_app');

// Add (fails if key exists) / Replace (fails if key doesn't exist)
$cache->add('counter', 0, 'my_app');
$cache->replace('counter', 1, 'my_app');

// Increment / Decrement
$cache->increment('counter', 1, 'my_app');
$cache->decrement('counter', 1, 'my_app');

// Delete and flush
$cache->delete('user_profile_123', 'my_app');
$cache->flush();
$cache->flushGroup('my_app');
```

### Maximum TTL

Enforce a maximum TTL on all cache writes to prevent unbounded expiration and memory exhaustion:

```php
// wp-config.php
define('WPPACK_CACHE_MAX_TTL', 86400); // 24 hours
```

Keys with TTL 0 (no expiration) or TTL exceeding `WPPACK_CACHE_MAX_TTL` are clamped to the configured value. Negative TTLs (immediate deletion) pass through unchanged.

### Compression

Enable adapter-level compression via phpredis / Relay `OPT_COMPRESSOR`:

```php
// wp-config.php
define('WPPACK_CACHE_COMPRESSION', 'zstd'); // 'none' (default), 'zstd', 'lz4', 'lzf'
```

Compression is handled by the native extension (ext-redis or Relay). Predis does not support compression.

### Async Flush

Use Redis `UNLINK` (non-blocking) instead of `DEL` (blocking) for key deletions. `UNLINK` removes the key from the keyspace in O(1) and frees memory in a background thread, avoiding main-thread blocking on large values.

```php
// wp-config.php
define('WPPACK_CACHE_ASYNC_FLUSH', true);
```

Applies to `delete`, `deleteMultiple`, prefix-based `flush`, and `hashDelete`. Does not affect `FLUSHDB` or `HDEL`. Requires Redis 4.0+ / Valkey.

### Hash Alloptions

WordPress stores all autoloaded options in a single serialized blob (`alloptions`). This causes race conditions when multiple requests update options simultaneously. Enable Hash storage to store each option as a separate Redis Hash field:

```php
// wp-config.php
define('WPPACK_CACHE_HASH_ALLOPTIONS', true);
```

> **Note:** Requires a Hash-capable backend (Redis / Valkey via ext-redis, Relay, or Predis). Non-Hash backends (Memcached, APCu, DynamoDB) automatically fall back to blob storage.

## Object Cache Drop-in

WPPack provides an `object-cache.php` drop-in that replaces WordPress's default in-memory object cache with a persistent backend (Redis, Valkey, etc.).

```bash
# Install Redis bridge
composer require wppack/redis-cache

# Configure in wp-config.php
# define('CACHE_DSN', 'redis://127.0.0.1:6379');

# Deploy drop-in
cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php
```

The drop-in uses the Adapter/Bridge pattern — `ObjectCache` handles runtime cache, groups, serialization, and multisite, while `AdapterInterface` implementations handle persistence.

## Documentation

See [docs/components/cache/](../../../docs/components/cache/) for full documentation.

## License

MIT
