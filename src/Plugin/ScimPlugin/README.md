# wppack/scim-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=scim_plugin)](https://codecov.io/github/wppack-io/wppack)

A WordPress plugin that provides SCIM 2.0 (RFC 7643/7644) provisioning endpoints. Enables automatic user and group provisioning from Identity Providers such as Azure AD, Okta, and OneLogin.

## Installation

```bash
composer require wppack/scim-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- SCIM 2.0 compatible Identity Provider (Azure AD, Okta, OneLogin, etc.)

## Architecture

ScimPlugin implements `PluginInterface` and bootstraps via `Kernel::registerPlugin()`:

1. **Bootstrap** registers the plugin with the Kernel
2. **ServiceProvider** registers SCIM controllers, authenticator, and repositories in the DI container
3. **AuthenticationManager** registers `determine_current_user` filter for Bearer token authentication
4. **RestRegistry** registers all SCIM REST controllers (Users, Groups, Schemas, ResourceTypes, ServiceProviderConfig)
5. **Controllers** handle SCIM API requests with event dispatching

## Configuration

Set environment variables in `wp-config.php`:

```php
// Required
define('SCIM_BEARER_TOKEN', 'your-secure-random-token');

// Optional
define('SCIM_SERVICE_ACCOUNT_USER_ID', 1);
define('SCIM_DEFAULT_ROLE', 'subscriber');
define('SCIM_ALLOW_USER_DELETION', false);
```

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SCIM_BEARER_TOKEN` | Yes | — | Bearer token for SCIM API authentication |
| `SCIM_SERVICE_ACCOUNT_USER_ID` | No | `1` | WordPress user ID for the SCIM service account |
| `SCIM_AUTO_PROVISION` | No | `true` | Enable automatic user provisioning |
| `SCIM_DEFAULT_ROLE` | No | `subscriber` | Default role for provisioned users |
| `SCIM_ALLOW_GROUP_MANAGEMENT` | No | `true` | Allow SCIM to manage WordPress roles |
| `SCIM_ALLOW_USER_DELETION` | No | `false` | Allow permanent user deletion (false = deactivate only) |
| `SCIM_BLOG_ID` | No | — | Target blog ID for multisite |
| `SCIM_MAX_RESULTS` | No | `100` | Maximum results per list request |

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/scim/v2/ServiceProviderConfig` | Service provider configuration |
| GET | `/wp-json/scim/v2/Schemas` | Schema definitions |
| GET | `/wp-json/scim/v2/ResourceTypes` | Resource type definitions |
| GET/POST | `/wp-json/scim/v2/Users` | List/create users |
| GET/PUT/PATCH/DELETE | `/wp-json/scim/v2/Users/{id}` | User operations |
| GET/POST | `/wp-json/scim/v2/Groups` | List/create groups |
| GET/PUT/PATCH/DELETE | `/wp-json/scim/v2/Groups/{id}` | Group operations |

## Documentation

See [full documentation](../../docs/plugins/scim-plugin.md) for details.

## License

MIT
