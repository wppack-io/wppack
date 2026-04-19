# WPPack SiteHealth

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=site_health)](https://codecov.io/github/wppack-io/wppack)

Attribute-based site health checks and debug information for WordPress.

## Installation

```bash
composer require wppack/site-health
```

## Usage

```php
use WPPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WPPack\Component\SiteHealth\Attribute\AsDebugInfo;
use WPPack\Component\SiteHealth\DebugSectionInterface;
use WPPack\Component\SiteHealth\HealthCheckInterface;
use WPPack\Component\SiteHealth\Result;
use WPPack\Component\SiteHealth\SiteHealthRegistry;

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
    ->add(new PhpVersionCheck())
    ->add(new MyPluginDebugInfo())
    ->register();
```

## Documentation

See [docs/components/site-health/](../../../docs/components/site-health/) for full documentation.

## License

MIT
