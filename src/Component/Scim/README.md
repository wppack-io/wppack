# WPPack Scim

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=scim)](https://codecov.io/github/wppack-io/wppack)

A component that provides SCIM 2.0 (RFC 7643/7644) provisioning support for WordPress. Enables automatic user and group provisioning from Identity Providers such as Azure AD, Okta, and OneLogin.

## Installation

```bash
composer require wppack/scim
```

## Features

- SCIM 2.0 compliant `/Users` and `/Groups` endpoints
- Bearer token authentication
- User provisioning (create, update, deactivate, delete)
- Group management via WordPress roles
- SCIM filter support (`eq`, `co`, `sw`, etc.)
- PATCH operations (RFC 7644 §3.5.2)
- Event system for provisioning lifecycle
- Multisite support

## Usage

### With ScimPlugin (recommended)

The easiest way to use SCIM provisioning is through the `wppack/scim-plugin` package.

### Standalone

```php
use WPPack\Component\Scim\Controller\UserController;
use WPPack\Component\Scim\Controller\GroupController;
use WPPack\Component\Rest\RestRegistry;

$restRegistry->register($userController);
$restRegistry->register($groupController);
```

## Documentation

For details, see [docs/components/scim/](../../docs/components/scim/).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Development takes place in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).

## License

MIT
