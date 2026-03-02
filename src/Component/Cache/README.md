# WpPack Cache

Object-oriented wrapper for the WordPress Object Cache API. Provides a simple manager class for cache operations with group support.

## Installation

```bash
composer require wppack/cache
```

## Usage

```php
use WpPack\Component\Cache\CacheManager;

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

## Object Cache Drop-in

WpPack provides an `object-cache.php` drop-in that replaces WordPress's default in-memory object cache with a persistent backend (Redis, Valkey, etc.).

```bash
# Install Redis bridge
composer require wppack/redis-cache

# Configure in wp-config.php
# define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');

# Deploy drop-in
cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php
```

The drop-in uses the Adapter/Bridge pattern — `ObjectCache` handles runtime cache, groups, serialization, and multisite, while `AdapterInterface` implementations handle persistence.

## Documentation

See [docs/components/cache/](../../../docs/components/cache/) for full documentation.

## License

MIT
