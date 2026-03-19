# WpPack APCu Cache

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=apcu_cache)](https://codecov.io/github/wppack-io/wppack)

APCu cache adapter for the WpPack Cache component.

## Installation

```bash
composer require wppack/apcu-cache
```

## Requirements

- PHP 8.2+
- `ext-apcu`
- `wppack/cache` ^0.1
- APCu enabled in php.ini (`apc.enabled=1`)

## DSN Format

```php
'apcu://'
```

## Configuration (wp-config.php)

```php
define('WPPACK_CACHE_DSN', 'apcu://');
define('WPPACK_CACHE_PREFIX', 'wp:');
```

## CLI Usage

APCu requires `apc.enable_cli=1` in php.ini for CLI scripts (e.g., WP-CLI).

```ini
; php.ini
apc.enable_cli=1
```

## Limitations

- **Local only**: APCu is per-process shared memory. Cache is not shared across servers.
- **CLI mode**: Separate cache store from web requests unless `apc.enable_cli=1` is set.
- **No persistence**: Cache is lost on PHP restart.
