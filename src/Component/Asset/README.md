# WpPack Asset

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=asset)](https://codecov.io/github/wppack-io/wppack)

Object-oriented wrapper for the WordPress Script and Style APIs. Provides DI-friendly asset management.

## Installation

```bash
composer require wppack/asset
```

## Usage

```php
use WpPack\Component\Asset\AssetManager;

$asset = new AssetManager();

// Scripts
$asset->enqueueScript('my-script', plugins_url('js/app.js', __FILE__), ['jquery'], '1.0.0', true);
$asset->addInlineScript('my-script', 'var config = {};', 'before');
$asset->localizeScript('my-script', 'myData', ['ajaxUrl' => admin_url('admin-ajax.php')]);

// Styles
$asset->enqueueStyle('my-style', plugins_url('css/app.css', __FILE__), [], '1.0.0');
$asset->addInlineStyle('my-style', '.custom { color: red; }');

// Check status
if ($asset->scriptIs('jquery', 'enqueued')) {
    // jQuery is loaded
}
```

## Named Hook Attributes

```php
use WpPack\Component\Asset\AssetManager;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminEnqueueScriptsAction;

final class AdminAssetSubscriber
{
    public function __construct(
        private readonly AssetManager $asset,
    ) {}

    #[AdminEnqueueScriptsAction]
    public function enqueueScripts(): void
    {
        $this->asset->enqueueScript('my-script', '/js/app.js', ['jquery'], '1.0.0', true);
    }
}
```

## Documentation

See [docs/components/asset/](../../../docs/components/asset/) for full documentation.

## License

MIT
