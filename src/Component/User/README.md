# WpPack User

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=user)](https://codecov.io/github/wppack-io/wppack)

User management utilities for WordPress.

## Installation

```bash
composer require wppack/user
```

## Quick Start

```php
use WpPack\Component\User\UserRepository;

$repository = new UserRepository();

// Find users
$users = $repository->findAll(['role' => 'editor']);
$user = $repository->find($userId);
$user = $repository->findByEmail('user@example.com');

// Create a user
$newId = $repository->insert([
    'user_login' => 'newuser',
    'user_pass' => 'password',
    'user_email' => 'new@example.com',
]);

// Meta operations
$repository->updateMeta($userId, 'custom_key', 'value');
$value = $repository->getMeta($userId, 'custom_key', single: true);
```

## Documentation

See [docs/components/user/](../../../docs/components/user/) for full documentation.

## License

MIT
