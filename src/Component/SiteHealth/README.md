# WpPack SiteHealth

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=site_health)](https://codecov.io/github/wppack-io/wppack)

Attribute-based site health checks and debug information for WordPress.

## Installation

```bash
composer require wppack/site-health
```

## Usage

```php
use WpPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WpPack\Component\SiteHealth\Attribute\AsDebugInfo;
use WpPack\Component\SiteHealth\DebugSectionInterface;
use WpPack\Component\SiteHealth\HealthCheckInterface;
use WpPack\Component\SiteHealth\Result;
use WpPack\Component\SiteHealth\SiteHealthRegistry;

#[AsHealthCheck(id: 'php_version', label: 'PHP Version', category: 'security')]
class PhpVersionCheck implements HealthCheckInterface
{
    public function run(): Result
    {
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            return Result::critical('PHP version is outdated', 'Please upgrade to PHP 8.2 or later.');
        }

        return Result::good('PHP version is up to date', 'Running PHP ' . PHP_VERSION);
    }
}

#[AsDebugInfo(section: 'my-plugin', label: 'My Plugin')]
class MyPluginDebugInfo implements DebugSectionInterface
{
    public function getFields(): array
    {
        return [
            'version' => ['label' => 'Version', 'value' => '1.0.0'],
        ];
    }
}

// Standalone registration (without DI container)
$registry = new SiteHealthRegistry();
$registry
    ->register(new PhpVersionCheck())
    ->register(new MyPluginDebugInfo())
    ->bind();
```

## Documentation

See [docs/components/site-health/](../../../docs/components/site-health/) for full documentation.

## License

MIT
