# WpPack Nonce

Object-oriented wrapper for the WordPress Nonce API. Provides type-safe nonce operations.

## Installation

```bash
composer require wppack/nonce
```

## Usage

```php
use WpPack\Component\Nonce\NonceManager;

$nonce = new NonceManager();

// Create and verify nonces
$token = $nonce->create('my-action');
$valid = $nonce->verify($token, 'my-action');

// Generate hidden input
$field = $nonce->field('my-action');

// Generate nonce URL
$url = $nonce->url('https://example.com/action', 'my-action');
```

## Named Hook Attributes

```php
use WpPack\Component\Nonce\Attribute\Filter\NonceLifeFilter;

final class NonceLifetimeCustomizer
{
    #[NonceLifeFilter(priority: 10)]
    public function customizeLifetime(int $seconds): int
    {
        return HOUR_IN_SECONDS * 4;
    }
}
```

## Documentation

See [docs/components/nonce/](../../../docs/components/nonce/) for full documentation.

## License

MIT
