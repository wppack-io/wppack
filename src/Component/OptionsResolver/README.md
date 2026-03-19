# WpPack OptionsResolver

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=options_resolver)](https://codecov.io/github/wppack-io/wppack)

An OptionsResolver for WordPress. Extends Symfony OptionsResolver to automatically cast values from strings when a single type (`'int'`, `'float'`, `'bool'`) is specified via `setAllowedTypes()`.

## Installation

```bash
composer require wppack/options-resolver
```

## Usage

### Basic

All features of Symfony OptionsResolver are available as-is.

```php
use WpPack\Component\OptionsResolver\OptionsResolver;

$resolver = new OptionsResolver();
$resolver->setDefaults([
    'title' => '',
    'style' => 'primary',
]);
$resolver->setAllowedValues('style', ['primary', 'secondary', 'danger']);

$resolved = $resolver->resolve(['title' => 'Hello', 'style' => 'danger']);
// ['title' => 'Hello', 'style' => 'danger']
```

### Automatic Casting by Type Specification

When a single type is specified via `setAllowedTypes()`, an automatic casting normalizer is registered for string-to-type conversion. This is useful in scenarios where all values are passed as strings, such as WordPress shortcode attributes.

```php
$resolver = new OptionsResolver();
$resolver->setDefaults([
    'count' => 5,
    'ratio' => 1.0,
    'enabled' => false,
]);
$resolver->setAllowedTypes('count', 'int');     // '10' → 10
$resolver->setAllowedTypes('ratio', 'float');   // '3.14' → 3.14
$resolver->setAllowedTypes('enabled', 'bool');  // 'true' → true

$resolved = $resolver->resolve(['count' => '10', 'ratio' => '3.14', 'enabled' => 'yes']);
// ['count' => 10, 'ratio' => 3.14, 'enabled' => true]
```

| Type | Conversion |
|------|------------|
| `'int'` | `(int)` cast |
| `'float'` | `(float)` cast |
| `'bool'` | `'true'`/`'1'`/`'yes'` → `true`, all others → `false` |

When multiple types are specified as an array (e.g., `['int', 'string']`), automatic casting is not applied.

## Integration with the Shortcode Component

When used in combination with the `wppack/shortcode` component, you can declaratively define attributes using the `configureAttributes()` method. See the [Shortcode documentation](../../../docs/components/shortcode/) for details.

## License

MIT
