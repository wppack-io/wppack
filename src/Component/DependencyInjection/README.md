# WpPack DependencyInjection

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=dependency_injection)](https://codecov.io/github/wppack-io/wppack)

PSR-11 compliant service container with autowiring and configuration management for WordPress.

## Installation

```bash
composer require wppack/dependency-injection
```

## ContainerValueResolver

`ContainerValueResolver` resolves method parameters by looking up their type in the PSR-11 container. Used with `ArgumentResolver` from the HttpFoundation component.

```php
use WpPack\Component\DependencyInjection\ValueResolver\ContainerValueResolver;
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\RequestValueResolver;

$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
    new ContainerValueResolver($container),
]);
```

## Documentation

See [docs/components/dependency-injection/](../../../docs/components/dependency-injection/) for full documentation.

## License

MIT
