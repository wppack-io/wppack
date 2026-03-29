# wppack/oauth-login-plugin

WordPress plugin for OAuth 2.0 / OpenID Connect multi-provider login. Integrates the `wppack/oauth-security` component as a WordPress plugin with DI, routing, and environment-based configuration.

## Installation

```bash
composer require wppack/oauth-login-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- OAuth 2.0 / OpenID Connect provider (Google, Azure AD, GitHub, Keycloak, etc.)

## Architecture

OAuthLoginPlugin implements `PluginInterface` and bootstraps via `Kernel::registerPlugin()`:

```
wppack/security            <- Authentication framework (AuthenticationManager, AuthenticatorInterface)
    ^
wppack/oauth-security      <- OAuth 2.0 / OIDC implementation (OAuthAuthenticator, OAuthEntryPoint, ...)
    ^
wppack/oauth-login-plugin  <- WordPress integration (DI, routing, environment config)
```

1. **Bootstrap** (`wppack-oauth-login.php`) registers the plugin with the Kernel
2. **ServiceProvider** registers per-provider authenticators, entry points, and route services in the DI container
3. **AuthenticationManager** registers `authenticate` and `determine_current_user` filters
4. **OAuthEntryPoint** initiates the Authorization Code flow with PKCE
5. **RouteRegistry** handles `/oauth/{provider}/authorize`, `/oauth/{provider}/callback`, and `/oauth/{provider}/verify` endpoints
6. **OAuthLoginForm** renders OAuth login buttons on the wp-login.php page

## Configuration

Define `OAUTH_PROVIDERS` in `wp-config.php`:

```php
define('OAUTH_PROVIDERS', [
    'google' => [
        'type'          => 'google',
        'client_id'     => 'your-client-id.apps.googleusercontent.com',
        'client_secret' => 'GOCSPX-...',
        'hosted_domain' => 'example.com', // optional: restrict to domain
    ],
]);

// Optional
define('OAUTH_SSO_ONLY', true);         // Replace wp-login.php entirely
define('OAUTH_AUTO_PROVISION', true);    // JIT user provisioning
define('OAUTH_DEFAULT_ROLE', 'subscriber');
```

### Supported Provider Types

| Type | Provider | Requires |
|------|----------|----------|
| `google` | Google Workspace / Google Account | `client_id`, `client_secret` |
| `azure` | Azure AD (Entra ID) | `client_id`, `client_secret`, `tenant_id` |
| `github` | GitHub | `client_id`, `client_secret` |
| `oidc` | Generic OpenID Connect | `client_id`, `client_secret`, `discovery_url` |

## Documentation

See [full documentation](../../docs/plugins/oauth-login-plugin.md) for details.

## License

MIT
