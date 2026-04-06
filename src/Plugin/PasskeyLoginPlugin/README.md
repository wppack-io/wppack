# wppack/passkey-login-plugin

WordPress plugin for WebAuthn/Passkey passwordless login. Provides passkey registration, authentication, and Conditional UI integration on the WordPress login page.

## Architecture

PasskeyLoginPlugin is a thin bootstrap layer on top of provider-agnostic components:

- **WebAuthn ceremony management** is provided by `wppack/passkey-security` (`CeremonyManager`)
- **Credential storage** is provided by `wppack/passkey-security` (`DatabaseCredentialRepository`)
- **REST API controllers** (register, authenticate, manage credentials) are provided by `wppack/passkey-security`
- **PasskeyLoginPlugin** provides only: plugin bootstrap, environment-based configuration, DI service registration, DB migration, and login form integration

## Installation

```bash
composer require wppack/passkey-login-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.9+
- HTTPS (required by the WebAuthn specification)

## Configuration

Set environment variables or constants in `wp-config.php`:

```php
// Optional — all settings have sensible defaults
define('PASSKEY_ENABLED', true);                          // Enable/disable passkey login
define('PASSKEY_RP_NAME', 'My Site');                     // Relying Party name (defaults to site name)
define('PASSKEY_RP_ID', 'example.com');                   // Relying Party ID (defaults to domain)
define('PASSKEY_ALLOW_SIGNUP', false);                    // Allow passkey-only registration
define('PASSKEY_REQUIRE_USER_VERIFICATION', 'preferred'); // preferred/required/discouraged
```

Settings can also be stored in `wp_options` under the key `wppack_passkey_login`.

Priority: constant > wp_options > environment variable > default.

## DI Services

The plugin registers the following services into the container:

| Service | Description |
|---------|-------------|
| `PasskeyLoginConfiguration` | Environment-based configuration via `fromEnvironmentOrOptions()` factory |
| `PasskeyConfiguration` | Bridge configuration constructed from `PasskeyLoginConfiguration` |
| `DatabaseCredentialRepository` | Credential storage using custom DB table |
| `CeremonyManager` | WebAuthn ceremony (registration/authentication) management |
| `AuthenticationController` | REST endpoint for passkey authentication |
| `RegistrationController` | REST endpoint for passkey registration |
| `CredentialController` | REST endpoint for credential management |
| `PasskeyLoginForm` | Login page integration with Conditional UI |

## License

MIT
