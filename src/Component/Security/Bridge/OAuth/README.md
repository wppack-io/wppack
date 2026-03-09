# OAuthSecurity

OAuth 2.0 / OpenID Connect 認証ブリッジ（WpPack Security Bridge）

## 概要

外部 IdP（Google, Azure AD, Okta, GitHub 等）による OAuth 2.0 / OpenID Connect SSO 認証を WordPress に統合します。

## インストール

```bash
composer require wppack/oauth-security
```

## 要件

- PHP 8.2+
- wppack/security ^1.0
- firebase/php-jwt ^7.0

## 対応プロバイダー

| プロバイダー | OIDC | RP-Initiated Logout | PKCE |
|-------------|------|---------------------|------|
| Google | Yes | No | Yes |
| Azure AD | Yes | Yes | Yes |
| GitHub | No | No | No |
| Generic OIDC | Yes | Yes (if supported) | Yes |

## ドキュメント

詳細なドキュメントは [docs/components/security/oauth-security.md](../../../../docs/components/security/oauth-security.md) を参照してください。

## ライセンス

MIT
