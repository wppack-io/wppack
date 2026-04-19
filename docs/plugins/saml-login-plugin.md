# SamlLoginPlugin

SAML 2.0 SSO 認証を WordPress に統合するプラグイン。`wppack/saml-security` コンポーネントを利用し、環境変数ベースの設定・DI コンテナ統合・ルーティングを提供する。

## 概要

SamlLoginPlugin は `wppack/saml-security` の SAML 認証機能を WordPress プラグインとして使えるようにするパッケージです:

- **SSO 統合**: wp-login.php を IdP ログインに自動置き換え
- **環境変数設定**: `wp-config.php` の `define()` または環境変数で設定
- **JIT プロビジョニング**: SAML 属性から WordPress ユーザーを自動作成
- **ロールマッピング**: SAML グループ属性から WordPress ロールへのマッピング
- **SP メタデータ**: `/saml/metadata` で SP メタデータ XML を自動公開
- **SLO 対応**: Single Logout（IdP-Initiated / SP-Initiated）

## アーキテクチャ

### パッケージ構成

```
wppack/security          ← 認証基盤（AuthenticationManager, AuthenticatorInterface）
    ↑
wppack/saml-security     ← SAML 2.0 実装（SamlAuthenticator, SamlEntryPoint, ...）
    ↑
wppack/saml-login-plugin ← WordPress 統合（DI, ルーティング, 環境変数設定）
```

### レイヤー構成

```
src/Plugin/SamlLoginPlugin/
├── wppack-saml-login.php                        ← Bootstrap（Kernel::registerPlugin）
├── src/
│   ├── SamlLoginPlugin.php                      ← PluginInterface 実装
│   ├── Configuration/
│   │   └── SamlLoginConfiguration.php           ← 設定 VO（環境変数）
│   ├── DependencyInjection/
│   │   └── SamlLoginPluginServiceProvider.php   ← サービス登録
│   └── Route/
│       └── SamlRouteRegistrar.php               ← SAML エンドポイント
└── tests/
```

### 認証フロー

#### SP-Initiated SSO

```
┌─ ブラウザ ─────────────────────────────────────┐
│                                                │
│  1. /wp-login.php にアクセス                    │
│     → SamlEntryPoint が IdP SSO URL にリダイレクト │
│                                                │
│  2. IdP で認証（ユーザー名/パスワード入力）      │
│                                                │
│  3. IdP が /saml/acs に SAMLResponse を POST    │
│     → SamlRouteRegistrar → wp_signon()         │
│     → authenticate フィルター                   │
│     → SamlAuthenticator::authenticate()        │
│     → SamlUserResolver::resolveUser()          │
│     → セッション確立、リダイレクト               │
│                                                │
└────────────────────────────────────────────────┘
```

#### IdP-Initiated SSO

```
┌─ ブラウザ ─────────────────────────────────────┐
│                                                │
│  1. IdP ポータルから WordPress アプリをクリック   │
│                                                │
│  2. IdP が /saml/acs に SAMLResponse を POST    │
│     → SP-Initiated SSO と同じフロー             │
│                                                │
└────────────────────────────────────────────────┘
```

#### Single Logout (SLO)

```
┌─ IdP-Initiated SLO ───────────────────────────┐
│                                                │
│  1. IdP が /saml/slo に SAMLRequest を GET     │
│     → SamlRouteRegistrar → SamlLogoutHandler   │
│     → handleIdpLogoutRequest()                 │
│     → wp_logout() + wp_clear_auth_cookie()     │
│     → home_url() にリダイレクト                 │
│                                                │
└────────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/saml-security | SAML 2.0 認証（SamlAuthenticator, SamlEntryPoint, SamlLogoutHandler） |
| wppack/security | 認証基盤（AuthenticationManager, AuthenticatorInterface） |
| wppack/event-dispatcher | イベントシステム（SamlResponseReceivedEvent） |
| wppack/dependency-injection | DI コンテナ |
| wppack/kernel | プラグインブートストラップ（PluginInterface） |

## 名前空間

```
WPPack\Plugin\SamlLoginPlugin\
```

## 設定

### 環境変数

`wp-config.php` で `define()` を使って設定します。環境変数（`$_ENV`, `getenv()`）にも対応。

#### 必須

| 変数 | 説明 |
|------|------|
| `SAML_IDP_ENTITY_ID` | IdP Entity ID |
| `SAML_IDP_SSO_URL` | IdP SSO エンドポイント URL |
| `SAML_IDP_X509_CERT` or `SAML_IDP_X509_CERT_FILE` | IdP 証明書（内容 or ファイルパス） |

#### オプション

| 変数 | デフォルト | 説明 |
|------|-----------|------|
| `SAML_IDP_SLO_URL` | `null` | IdP SLO URL（null = SLO 無効） |
| `SAML_IDP_CERT_FINGERPRINT` | `null` | 証明書フィンガープリント |
| `SAML_SP_ENTITY_ID` | `home_url()` | SP Entity ID |
| `SAML_SP_ACS_URL` | `home_url('/saml/acs')` | SP ACS URL |
| `SAML_SP_SLO_URL` | `home_url('/saml/slo')` | SP SLO URL |
| `SAML_SP_NAMEID_FORMAT` | `unspecified` | NameID フォーマット |
| `SAML_STRICT` | `true` | Strict モード |
| `SAML_DEBUG` | `false` | デバッグモード |
| `SAML_WANT_ASSERTIONS_SIGNED` | `true` | 署名検証 |
| `SAML_AUTO_PROVISION` | `false` | JIT プロビジョニング |
| `SAML_DEFAULT_ROLE` | `subscriber` | 新規ユーザーのデフォルトロール |
| `SAML_EMAIL_ATTRIBUTE` | `email` | メールアドレス属性名 |
| `SAML_FIRST_NAME_ATTRIBUTE` | `firstName` | 名属性名 |
| `SAML_LAST_NAME_ATTRIBUTE` | `lastName` | 姓属性名 |
| `SAML_DISPLAY_NAME_ATTRIBUTE` | `displayName` | 表示名属性名 |
| `SAML_ROLE_ATTRIBUTE` | `null` | ロール属性名 |
| `SAML_ROLE_MAPPING` | `null` | JSON ロールマッピング |
| `SAML_ADD_USER_TO_BLOG` | `true` | マルチサイトでブログに自動追加 |

### X.509 証明書の設定

本番環境ではファイルパス指定を推奨:

```php
// ファイルパス（推奨）
define('SAML_IDP_X509_CERT_FILE', '/etc/saml/idp-cert.pem');

// 直接指定（リテラル \n は改行に変換）
define('SAML_IDP_X509_CERT', '-----BEGIN CERTIFICATE-----\nMIID...\n-----END CERTIFICATE-----');
```

`SAML_IDP_X509_CERT_FILE` が設定されている場合、`SAML_IDP_X509_CERT` より優先されます。

### NameID フォーマット

短縮名が使用可能:

| 短縮名 | URN |
|--------|-----|
| `emailAddress` | `urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress` |
| `persistent` | `urn:oasis:names:tc:SAML:2.0:nameid-format:persistent` |
| `transient` | `urn:oasis:names:tc:SAML:2.0:nameid-format:transient` |
| `unspecified` | `urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified` |

完全な URN を直接指定することも可能です。

## IdP メタデータからの設定

環境変数で個別指定する代わりに、`IdpMetadataParser` を使って IdP メタデータ XML から `IdpSettings` を一括取り込みできます。詳細は [saml-security のドキュメント](../components/security/saml-security.md#idp-メタデータの取り込み) を参照してください。

> [!NOTE]
> プラグイン UI からのメタデータ取り込みは後続タスクで対応予定です。現時点ではコード経由での利用となります。

## IdP 設定ガイド

### Keycloak

```php
// wp-config.php
define('SAML_IDP_ENTITY_ID', 'http://localhost:8081/realms/master');
define('SAML_IDP_SSO_URL', 'http://localhost:8081/realms/master/protocol/saml');
define('SAML_IDP_SLO_URL', 'http://localhost:8081/realms/master/protocol/saml');
define('SAML_IDP_X509_CERT_FILE', '/path/to/keycloak-cert.pem');
define('SAML_AUTO_PROVISION', true);
```

Keycloak 管理コンソールで:
1. Clients → Create → SAML Client
2. Client ID: `home_url()` と同じ値
3. Valid Redirect URIs: `https://your-site.com/saml/acs`
4. IDP Initiated SSO URL Name: 任意の名前

### Okta

```php
define('SAML_IDP_ENTITY_ID', 'http://www.okta.com/exk...');
define('SAML_IDP_SSO_URL', 'https://your-org.okta.com/app/.../sso/saml');
define('SAML_IDP_X509_CERT_FILE', '/path/to/okta-cert.pem');
```

### Azure AD (Entra ID)

```php
define('SAML_IDP_ENTITY_ID', 'https://sts.windows.net/{tenant-id}/');
define('SAML_IDP_SSO_URL', 'https://login.microsoftonline.com/{tenant-id}/saml2');
define('SAML_IDP_SLO_URL', 'https://login.microsoftonline.com/{tenant-id}/saml2');
define('SAML_IDP_X509_CERT_FILE', '/path/to/azure-cert.pem');
```

### Google Workspace

```php
define('SAML_IDP_ENTITY_ID', 'https://accounts.google.com/o/saml2?idpid=...');
define('SAML_IDP_SSO_URL', 'https://accounts.google.com/o/saml2/idp?idpid=...');
define('SAML_IDP_X509_CERT_FILE', '/path/to/google-cert.pem');
```

## ユーザープロビジョニング

### JIT プロビジョニング

`SAML_AUTO_PROVISION=true` を設定すると、IdP で認証済みの未登録ユーザーを自動作成します:

```php
define('SAML_AUTO_PROVISION', true);
define('SAML_DEFAULT_ROLE', 'subscriber');
```

SAML 属性から以下のユーザー情報が同期されます（新規作成時および既存ユーザーのログイン時）:
- `email` → `user_email`
- `firstName` → `first_name`
- `lastName` → `last_name`
- `displayName` → `display_name`

#### 属性名のカスタマイズ

IdP の属性名がデフォルト値と異なる場合、環境変数でオーバーライドできます:

```php
// Azure AD (Entra ID) の場合
define('SAML_EMAIL_ATTRIBUTE', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress');
define('SAML_FIRST_NAME_ATTRIBUTE', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname');
define('SAML_LAST_NAME_ATTRIBUTE', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname');
define('SAML_DISPLAY_NAME_ATTRIBUTE', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name');
```

### カスタム属性マッピング

デフォルトの属性マッピングに加え、SAML 属性を WordPress ユーザーメタにマッピングできます。

#### 宣言的マッピング（SamlAttributeMapping）

`SamlAttributeMapping` で SAML 属性名と WordPress メタキーの対応を定義します:

```php
use WPPack\Component\Security\Bridge\SAML\UserResolution\SamlAttributeMapping;
use WPPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;

// カスタム ServiceProvider でオーバーライド
$builder->findDefinition(SamlUserResolver::class)
    ->setArgument('$customMappings', [
        new SamlAttributeMapping('department', 'org_department'),
        new SamlAttributeMapping('employeeNumber', 'employee_id'),
        new SamlAttributeMapping('title', 'job_title'),
    ]);
```

カスタムマッピングで保存される値は `sanitize_text_field()` でサニタイズされます。

#### イベントによるカスタマイズ

条件分岐や値の結合など、宣言的マッピングで対応できない複雑なロジックにはイベントリスナーを使用します:

```php
use WPPack\Component\EventDispatcher\Attribute\AsEventListener;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserAttributesMappedEvent;

final class CustomSamlMapper
{
    #[AsEventListener]
    public function __invoke(SamlUserAttributesMappedEvent $event): void
    {
        $attrs = $event->getAttributes();
        $meta = $event->getUserMeta();

        // 複数属性を結合してメタに保存
        $dept = $attrs['department'][0] ?? '';
        $org = $attrs['organization'][0] ?? '';
        if ($dept !== '' && $org !== '') {
            $meta['org_department'] = $org . ' / ' . $dept;
            $event->setUserMeta($meta);
        }
    }
}
```

詳細は [saml-security のドキュメント](../components/security/saml-security.md#属性マッピングのカスタマイズ) を参照してください。

#### 将来の管理画面対応

管理画面を追加する際は以下のステップで対応可能です。コンポーネント側の変更は不要です:

1. 管理画面でマッピングルール（SAML 属性名 → メタキー）を CRUD → `wp_options` に JSON 保存
2. プラグインの ServiceProvider で `wp_options` から読み込み → `SamlAttributeMapping[]` を構築
3. `setArgument('$customMappings', ...)` で `SamlUserResolver` に渡す

### ロールマッピング

SAML グループ属性から WordPress ロールへのマッピング:

```php
define('SAML_ROLE_ATTRIBUTE', 'groups');
define('SAML_ROLE_MAPPING', '{"admins":"administrator","editors":"editor","authors":"author"}');
```

## セキュリティ考慮事項

- **証明書管理**: 本番では `SAML_IDP_X509_CERT_FILE` でファイルパスを指定。環境変数に証明書を直接格納する場合はシークレット管理サービスを利用
- **Strict モード**: `SAML_STRICT=true`（デフォルト）を推奨。Destination、Issuer、InResponseTo の厳格な検証を有効化
- **署名検証**: `SAML_WANT_ASSERTIONS_SIGNED=true`（デフォルト）で Assertion の署名を必須化
- **NameID バインディング**: ユーザーの NameID を `wp_usermeta` に保存し、NameID の変更によるなりすましを防止
- **SensitiveParameter**: 証明書は `#[\SensitiveParameter]` 属性でスタックトレースからの漏洩を防止
