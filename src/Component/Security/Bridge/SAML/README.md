# SamlSecurity

SAML 2.0 SP（Service Provider）認証ブリッジ。`onelogin/php-saml` をラップし、外部 IdP（Okta, Azure AD, Google Workspace 等）による SSO 認証を WpPack Security コンポーネントに統合します。

## インストール

```bash
composer require wppack/saml-security
```

## 基本設定

```php
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

$idpSettings = new IdpSettings(
    entityId: 'https://idp.example.com/metadata',
    ssoUrl: 'https://idp.example.com/sso',
    sloUrl: 'https://idp.example.com/slo',
    x509Cert: '-----BEGIN CERTIFICATE-----...-----END CERTIFICATE-----',
);

$spSettings = new SpSettings(
    entityId: 'https://example.com/wp',
    acsUrl: 'https://example.com/wp/sso/verify',
    sloUrl: 'https://example.com/wp/sso/logout',
);

$configuration = new SamlConfiguration($idpSettings, $spSettings);
```

## 使用例

### SP-Initiated SSO

```php
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

$factory = new SamlAuthFactory($configuration);
$entryPoint = new SamlEntryPoint($factory);

// IdP にリダイレクト
$entryPoint->start(returnTo: admin_url());

// URL のみ取得
$loginUrl = $entryPoint->getLoginUrl();
```

### ACS（Assertion Consumer Service）

```php
use WpPack\Component\Security\Bridge\SAML\SamlAuthenticator;

$authenticator = new SamlAuthenticator(
    authFactory: $factory,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    acsPath: '/sso/verify',
);
```

### SP メタデータ

```php
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;

$metadata = new SamlMetadataController($configuration);
$metadata->serve(); // XML レスポンスを出力
```

### Single Logout

```php
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;

$logoutHandler = new SamlLogoutHandler($factory, redirectAfterLogout: home_url());
$logoutHandler->initiateLogout($nameId, $sessionIndex);
```

## ユーザー解決（JIT プロビジョニング）

```php
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;

$userResolver = new SamlUserResolver(
    autoProvision: true,
    defaultRole: 'subscriber',
    emailAttribute: 'email',
    firstNameAttribute: 'firstName',
    lastNameAttribute: 'lastName',
    roleMapping: [
        'Admin' => 'administrator',
        'Editor' => 'editor',
    ],
    roleAttribute: 'groups',
);
```

## マルチサイト対応

### クロスサイト SSO

```php
use WpPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;

$redirector = new CrossSiteRedirector(
    allowedHosts: ['site-a.example.com', 'site-b.example.com'],
);

$authenticator = new SamlAuthenticator(
    authFactory: $factory,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    crossSiteRedirector: $redirector,
);
```

## 依存関係

- `wppack/security` ^1.0
- `onelogin/php-saml` ^4.0

## ドキュメント

詳細は [docs/components/security/saml-security.md](../../../../docs/components/security/saml-security.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。
