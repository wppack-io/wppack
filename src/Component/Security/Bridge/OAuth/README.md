# OAuthSecurity

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=oauth_security)](https://codecov.io/github/wppack-io/wppack)

OAuth 2.0 / OpenID Connect authentication bridge (WpPack Security Bridge)

## Overview

Integrates OAuth 2.0 / OpenID Connect SSO authentication with external IdPs into WordPress.

## Installation

```bash
composer require wppack/oauth-security
```

## Requirements

- PHP 8.2+
- wppack/security ^1.0
- firebase/php-jwt ^7.0

## Supported Providers

| Provider | Type | OIDC | Required Config |
|----------|------|------|-----------------|
| Apple | `apple` | Yes | client_id, client_secret |
| Auth0 | `auth0` | Yes | client_id, client_secret, **domain** |
| AWS Cognito | `cognito` | Yes | client_id, client_secret, **domain** |
| Discord | `discord` | No | client_id, client_secret |
| Facebook | `facebook` | No | client_id, client_secret |
| GitHub | `github` | No | client_id, client_secret |
| Google | `google` | Yes | client_id, client_secret |
| Keycloak | `keycloak` | Yes | client_id, client_secret, **domain** |
| LINE | `line` | Yes | client_id, client_secret |
| Microsoft Entra ID | `entra-id` | Yes | client_id, client_secret, **tenant_id** |
| Okta | `okta` | Yes | client_id, client_secret, **domain** |
| OneLogin | `onelogin` | Yes | client_id, client_secret, **domain** |
| Microsoft (Personal) | `microsoft` | Yes | client_id, client_secret |
| Slack | `slack` | Yes | client_id, client_secret |
| Yahoo | `yahoo` | Yes | client_id, client_secret |
| Yahoo! JAPAN | `yahoo-japan` | Yes | client_id, client_secret |
| d Account | `d-account` | Yes | client_id, client_secret |
| Generic OIDC | `oidc` | Yes | client_id, client_secret, discovery_url |

## Documentation

For detailed documentation, see [docs/components/security/oauth-security.md](../../../../docs/components/security/oauth-security.md).

## Third-party Notices

This component includes provider logo SVG data in `ProviderIcons`. All logos are trademarks of their respective owners and are used solely for identifying sign-in providers in accordance with each provider's branding guidelines. OpenID icon is from [Simple Icons](https://simpleicons.org), licensed under CC0 1.0 Universal.

## License

MIT
