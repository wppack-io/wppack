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

$restUrl = new RestUrlGenerator($registry);

$restUrl->url('wppack/v1/products');  // rest_url()
$restUrl->prefix();                   // rest_get_url_prefix()

// Named route URL generation
$restUrl->generate('product_show', ['id' => 42]);
// => https://example.com/wp-json/my-plugin/v1/products/42
```

## Documentation

See [docs/components/rest/](../../../docs/components/rest/) for full documentation.

## License

MIT
