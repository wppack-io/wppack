# WPPack Transient

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=transient)](https://codecov.io/github/wppack-io/wppack)

Object-oriented wrapper for the WordPress Transient API. Provides manager classes for transient operations on single-site and multisite environments.

## Installation

```bash
composer require wppack/transient
```

## Usage

### TransientManager

```php
use WPPack\Component\Transient\TransientManager;

$transient = new TransientManager();

// Store and retrieve temporary data
$transient->set('api_response', $data, HOUR_IN_SECONDS);
$data = $transient->get('api_response');

// Delete
$transient->delete('api_response');
```

### SiteTransientManager (Multisite)

```php
use WPPack\Component\Transient\SiteTransientManager;

$siteTransient = new SiteTransientManager();

// Network-wide transient operations
$siteTransient->set('network_status', $status, DAY_IN_SECONDS);
$status = $siteTransient->get('network_status');
$siteTransient->delete('network_status');
```

### Named Hook Attributes

```php
use WPPack\Component\Hook\Attribute\Transient\Filter\PreTransientFilter;
use WPPack\Component\Hook\Attribute\Transient\Action\SetTransientAction;

final class TransientHooks
{
    #[PreTransientFilter('external_api_data', priority: 10)]
    public function interceptApiData(mixed $preValue): mixed
    {
        // Check in-memory cache first
        return false;
    }

    #[SetTransientAction('api_cache', priority: 10)]
    public function onApiCacheSet(mixed $value, int $expiration, string $transient): void
    {
        // Log cache updates
    }
}
```

## Documentation

See [docs/components/transient/](../../../docs/components/transient/) for full documentation.

## License

MIT
