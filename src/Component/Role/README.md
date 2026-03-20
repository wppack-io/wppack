# WpPack Role

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=role)](https://codecov.io/github/wppack-io/wppack)

User role and capability management for WordPress.

Provides attribute-based role definitions (`#[AsRole]`), role synchronization (`RoleManager`), and authorization checking (`#[IsGranted]`, `IsGrantedChecker`).

## Installation

```bash
composer require wppack/role
```

## Quick Start

```php
use WpPack\Component\Role\Attribute\AsRole;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\RoleManager;

// Define a role
#[AsRole(name: 'shop_manager', label: 'Shop Manager', capabilities: ['read', 'edit_posts', 'manage_products'])]
final class ShopManagerRole {}

// Synchronize with WordPress
$manager = new RoleManager();
$manager->add(ShopManagerRole::class);
$manager->synchronize();

// Protect a class with capability check
#[IsGranted('manage_products')]
final class ProductController {}
```

## Documentation

See [docs/components/role.md](../../../docs/components/role.md) for full documentation.

## License

MIT
