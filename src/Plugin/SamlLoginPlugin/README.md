# wppack/saml-login-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=saml_login_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for SAML 2.0 SSO authentication. Integrates the `wppack/saml-security` component as a WordPress plugin with DI, routing, and environment-based configuration.

## Installation

```bash
composer require wppack/saml-login-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.3 or higher
- SAML 2.0 compatible Identity Provider (Keycloak, Okta, Azure AD, Google Workspace, etc.)

## Architecture

SamlLoginPlugin implements `PluginInterface` and bootstraps via `Kernel::registerPlugin()`:

1. **Bootstrap** (`wppack-saml-login.php`) registers the plugin with the Kernel
2. **ServiceProvider** registers SAML authenticator, entry point, and route services in the DI container
3. **AuthenticationManager** registers `authenticate` and `determine_current_user` filters
4. **SamlEntryPoint** replaces wp-login.php with IdP redirect
5. **SamlRouteRegistrar** handles `/saml/metadata`, `/saml/acs`, and `/saml/slo` endpoints

## Configuration

Set environment variables in `wp-config.php`:

```php
// Required
define('SAML_IDP_ENTITY_ID', 'https://idp.example.com/realms/master');
define('SAML_IDP_SSO_URL', 'https://idp.example.com/realms/master/protocol/saml');
define('SAML_IDP_X509_CERT_FILE', '/path/to/idp-cert.pem');

// Optional
define('SAML_AUTO_PROVISION', true);
define('SAML_DEFAULT_ROLE', 'subscriber');
```

### Required Variables

| Variable | Description |
|----------|-------------|
| `SAML_IDP_ENTITY_ID` | IdP Entity ID |
| `SAML_IDP_SSO_URL` | IdP SSO endpoint URL |
| `SAML_IDP_X509_CERT` or `SAML_IDP_X509_CERT_FILE` | IdP certificate (value or file path) |

### Optional Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SAML_IDP_SLO_URL` | `null` | IdP SLO URL (null = SLO disabled) |
| `SAML_SP_ENTITY_ID` | `home_url()` | SP Entity ID |
| `SAML_SP_ACS_URL` | `home_url('/saml/acs')` | SP ACS URL |
| `SAML_SP_SLO_URL` | `home_url('/saml/slo')` | SP SLO URL |
| `SAML_SP_NAMEID_FORMAT` | `emailAddress` | NameID format |
| `SAML_STRICT` | `true` | Strict mode |
| `SAML_DEBUG` | `false` | Debug mode |
| `SAML_WANT_ASSERTIONS_SIGNED` | `true` | Assertion signature verification |
| `SAML_AUTO_PROVISION` | `false` | JIT user provisioning |
| `SAML_DEFAULT_ROLE` | `subscriber` | Default role for new users |
| `SAML_ROLE_ATTRIBUTE` | `null` | SAML attribute for role mapping |
| `SAML_ROLE_MAPPING` | `null` | JSON role mapping (e.g., `{"admins":"administrator"}`) |
| `SAML_ADD_USER_TO_BLOG` | `true` | Auto-add user to blog on multisite |

## Documentation

See [full documentation](../../docs/plugins/saml-login-plugin.md) for details.

## License

MIT
