# OAuthSecurity

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=oauth_security)](https://codecov.io/github/wppack-io/wppack)

OAuth 2.0 / OpenID Connect authentication bridge (WpPack Security Bridge)

## Overview

Integrates OAuth 2.0 / OpenID Connect SSO authentication with external IdPs (Google, Azure AD, Okta, GitHub, etc.) into WordPress.

## Installation

```bash
composer require wppack/oauth-security
```

## Requirements

- PHP 8.2+
- wppack/security ^1.0
- firebase/php-jwt ^7.0

## Supported Providers

| Provider | OIDC | RP-Initiated Logout | PKCE |
|----------|------|---------------------|------|
| Google | Yes | No | Yes |
| Azure AD | Yes | Yes | Yes |
| GitHub | No | No | No |
| Generic OIDC | Yes | Yes (if supported) | Yes |

## Documentation

For detailed documentation, see [docs/components/security/oauth-security.md](../../../../docs/components/security/oauth-security.md).

## License

MIT
