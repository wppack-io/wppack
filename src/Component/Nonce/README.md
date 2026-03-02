# WpPack Nonce

Object-oriented wrapper for the WordPress Nonce API. Provides type-safe nonce operations.

## Installation

```bash
composer require wppack/nonce
```

## Usage

```php
use WpPack\Component\Nonce\NonceManager;

$nonceManager = new NonceManager();

// Create and verify nonces
$nonce = $nonceManager->create('my-action');
$valid = $nonceManager->verify($nonce, 'my-action');

// Generate hidden input
$field = $nonceManager->field('my-action');

// Generate nonce URL
$url = $nonceManager->url('https://example.com/action', 'my-action');
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
