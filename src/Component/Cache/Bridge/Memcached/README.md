# WpPack Memcached Cache

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=memcached_cache)](https://codecov.io/github/wppack-io/wppack)

Memcached cache adapter for the WpPack Cache component.

## Installation

```bash
composer require wppack/memcached-cache
```

## Requirements

- PHP 8.2+
- `ext-memcached`
- `wppack/cache` ^0.1
- Memcached server

## DSN Format

```php
// Standalone
'memcached://127.0.0.1:11211'

// Multiple servers
'memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]'

// SASL authentication
'memcached://user:password@127.0.0.1:11211'

// Unix socket
'memcached:///var/run/memcached.sock'

// With options
'memcached://127.0.0.1:11211?weight=100'
```

## Configuration (wp-config.php)

```php
define('WPPACK_CACHE_DSN', 'memcached://127.0.0.1:11211');
define('WPPACK_CACHE_PREFIX', 'wp:');
```

## Options

| Parameter | Default | Description |
|-----------|---------|-------------|
| `host` | `127.0.0.1` | Server host |
| `port` | `11211` | Server port |
| `weight` | `0` | Server weight |
| `persistent_id` | — | Persistent connection ID |
| `username` | — | SASL username |
| `password` | — | SASL password |
| `timeout` | — | Connection timeout (ms) |
| `retry_timeout` | — | Retry timeout (s) |
| `tcp_nodelay` | `true` | Disable Nagle's algorithm |
| `no_block` | `true` | Asynchronous I/O |
| `binary_protocol` | `true` | Use binary protocol |
| `libketama_compatible` | `true` | Consistent hashing |

## Local Development

```yaml
# docker-compose.yml
services:
  memcached:
    image: memcached:1.6
    ports:
      - '11211:11211'
```
