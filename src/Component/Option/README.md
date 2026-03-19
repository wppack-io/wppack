# WpPack Option

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=option)](https://codecov.io/github/wppack-io/wppack)

Object-oriented wrapper for the WordPress Options API. Provides simple manager classes for option CRUD operations.

## Installation

```bash
composer require wppack/option
```

## Usage

### OptionManager

```php
use WpPack\Component\Option\OptionManager;

$option = new OptionManager();

// Get an option value
$value = $option->get('my_plugin_settings', []);

// Add a new option (fails if already exists)
$option->add('my_plugin_version', '1.0.0');

// Update an option (creates if not exists)
$option->update('my_plugin_settings', ['debug' => true]);

// Delete an option
$option->delete('my_plugin_old_setting');
```

### SiteOptionManager (Multisite)

```php
use WpPack\Component\Option\SiteOptionManager;

$siteOption = new SiteOptionManager();

// Get a network-wide option
$value = $siteOption->get('network_settings', []);

// Update (creates if not exists)
$siteOption->update('network_settings', ['maintenance' => false]);

// Delete
$siteOption->delete('network_old_setting');
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\Option\Filter\PreOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Action\UpdateOptionAction;

final class OptionHooks
{
    #[PreOptionFilter('blogname', priority: 10)]
    public function filterSiteName(mixed $preValue): mixed
    {
        if (defined('SITE_NAME_OVERRIDE')) {
            return SITE_NAME_OVERRIDE;
        }

        return false;
    }

    #[UpdateOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsUpdated(mixed $oldValue, mixed $newValue, string $option): void
    {
        wp_cache_flush_group('my_plugin');
    }
}
```

## Documentation

See [docs/components/option/](../../../docs/components/option/) for full documentation.

## License

MIT
