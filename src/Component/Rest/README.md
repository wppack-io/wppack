# WpPack Rest

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=rest)](https://codecov.io/github/wppack-io/wppack)

REST API endpoint framework for WordPress.

## Installation

```bash
composer require wppack/rest
```

## Usage

### RestUrlGenerator

```php
use WpPack\Component\Rest\RestUrlGenerator;

$restUrl = new RestUrlGenerator();

$restUrl->url('wppack/v1/products');  // rest_url()
$restUrl->prefix();                   // rest_get_url_prefix()
```

## Documentation

See [docs/components/rest/](../../../docs/components/rest/) for full documentation.

## License

MIT
