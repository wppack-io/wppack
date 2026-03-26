# SamlSecurity

SAML 2.0 SP（Service Provider）認証ブリッジ

## 概要

| 項目 | 値 |
|------|-----|
| パッケージ名 | `wppack/saml-security` |
| 名前空間 | `WpPack\Component\Security\Bridge\SAML\` |
| レイヤー | Abstraction（Bridge） |
| 依存 | `wppack/security`, `onelogin/php-saml` |

外部 IdP（Okta, Azure AD, Google Workspace 等）による SAML 2.0 SSO 認証を WpPack Security コンポーネントに統合する Bridge パッケージです。`onelogin/php-saml` をラップし、WordPress の認証フローに組み込みます。

## インストール

```bash
composer require wppack/saml-security
```

## 設定

### IdP 設定 (IdpSettings)

IdP（Identity Provider）の情報を定義します:

```php
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;

$idpSettings = new IdpSettings(
    entityId: 'https://idp.example.com/metadata',
    ssoUrl: 'https://idp.example.com/sso',
    sloUrl: 'https://idp.example.com/slo',          // Single Logout URL（省略可）
    x509Cert: '-----BEGIN CERTIFICATE-----...-----END CERTIFICATE-----',
    certFingerprint: null,                            // 証明書フィンガープリント（省略可）
);
```

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `entityId` | `string` | IdP のエンティティ ID |
| `ssoUrl` | `string` | SSO エンドポイント URL |
| `sloUrl` | `?string` | SLO エンドポイント URL |
| `x509Cert` | `string` | IdP の X.509 証明書 |
| `certFingerprint` | `?string` | 証明書のフィンガープリント |

### SP 設定 (SpSettings)

SP（Service Provider = WordPress サイト）の情報を定義します:

```php
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;

$spSettings = new SpSettings(
    entityId: 'https://example.com',
    acsUrl: 'https://example.com/saml/acs',
    sloUrl: 'https://example.com/saml/slo',     // 省略可
    nameIdFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
);
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `entityId` | `string` | — | SP のエンティティ ID |
| `acsUrl` | `string` | — | Assertion Consumer Service URL |
| `sloUrl` | `?string` | `null` | Single Logout URL |
| `nameIdFormat` | `string` | `emailAddress` | NameID フォーマット |

### SAML 設定 (SamlConfiguration)

IdP と SP の設定を統合し、セキュリティオプションを制御します:

```php
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

$configuration = new SamlConfiguration(
    idpSettings: $idpSettings,
    spSettings: $spSettings,
    strict: true,                   // 厳格モード（本番環境では true 必須）
    debug: false,                   // デバッグモード
    wantAssertionsSigned: true,     // 署名付きアサーション要求
    wantNameIdEncrypted: false,     // NameID 暗号化要求
);
```

`SamlConfiguration::toOneLoginArray()` メソッドで `onelogin/php-saml` が要求する配列形式に変換されます。

## 認証フロー

### SP-Initiated SSO

```
[ユーザーがログインボタンをクリック]
    ↓
SamlEntryPoint::start()
    ↓ IdP に AuthnRequest を送信（HTTP-Redirect）
[IdP でユーザー認証]
    ↓ SAMLResponse を ACS URL に POST
SamlAuthenticator::supports() → true
    ↓
SamlAuthenticator::authenticate()
    ↓ onelogin/php-saml で検証
    ↓ SamlResponseReceivedEvent ディスパッチ
    ↓ SamlUserResolver でユーザー解決
SelfValidatingPassport を返却
    ↓
SamlAuthenticator::createToken()
    ↓
SamlAuthenticator::onAuthenticationSuccess()
    ↓ wp_clear_auth_cookie() で既存セッションクリア
    ↓ wp_set_auth_cookie() でセッション確立
    ↓ RelayState（同一オリジンのみ）にリダイレクト
```

### IdP-Initiated SSO

IdP 側からログインが開始される場合も、ACS URL に SAMLResponse が POST されるため、SP-Initiated と同じ `SamlAuthenticator` で処理されます。`RelayState` が設定されていない場合は `admin_url()` にリダイレクトします。

## コンポーネント

### SamlAuthenticator

Security コンポーネントの `AuthenticatorInterface` を実装する中核クラスです。

```php
use WpPack\Component\Security\Bridge\SAML\SamlAuthenticator;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

$factory = new SamlAuthFactory($configuration);

$authenticator = new SamlAuthenticator(
    authFactory: $factory,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    acsPath: '/saml/acs',           // ACS パス（デフォルト: /saml/acs）
    crossSiteRedirector: null,         // マルチサイト用（後述）
    addUserToBlog: true,               // マルチサイトでブログにユーザーを自動追加
);
```

- `supports()`: POST リクエストかつ `SAMLResponse` パラメータが存在し、パスが `acsPath` に一致する場合に `true`
- `authenticate()`: `onelogin/php-saml` で SAMLResponse を検証し、`SelfValidatingPassport` を返却
- `createToken()`: `PostAuthenticationToken` を生成（マルチサイトではブログ ID を付与）
- `onAuthenticationSuccess()`: 既存セッションクリア後に `wp_set_auth_cookie()` でセッションを確立し、同一オリジンの `RelayState` にリダイレクト
- `onAuthenticationFailure()`: `wp-login.php?saml_error=1` にリダイレクト（エラー詳細はフック経由でのみ通知）

### SamlEntryPoint

SP-Initiated SSO のエントリポイントです:

```php
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;

$entryPoint = new SamlEntryPoint($factory);

// IdP にリダイレクト（処理は戻らない）
$entryPoint->start(returnTo: admin_url());

// ログイン URL のみ取得（リダイレクトしない）
$loginUrl = $entryPoint->getLoginUrl(returnTo: admin_url());
echo '<a href="' . esc_attr($loginUrl) . '">SSO ログイン</a>';
```

#### SSO 専用構成（register）

`register()` を呼ぶと、WordPress のフォームログインを無効化し、`wp-login.php` へのアクセスを IdP にリダイレクトします:

```php
// 一行で SSO 専用化
$entryPoint->register();
```

内部で登録されるフック:

| フック | 説明 |
|--------|------|
| `login_url` フィルター | `wp-login.php` の URL を IdP SSO URL に差し替え |
| `login_init` アクション | `wp-login.php` への GET アクセスを IdP にリダイレクト |

`$_GET['action']` がある場合（`logout`, `lostpassword` 等）はリダイレクトをスキップし、WordPress 標準フローを維持します。

### SamlMetadataController

SP メタデータ XML を IdP に提供するためのコントローラです:

```php
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;

$metadata = new SamlMetadataController($configuration);

// XML 文字列として取得
$xml = $metadata->getMetadataXml();

// HTTP レスポンスとして出力（Content-Type: application/xml）
$metadata->serve();
```

IdP の管理画面で SP メタデータ URL（例: `https://example.com`）を登録する際に使用します。

### SamlLogoutHandler

SAML Single Logout（SLO）を処理します:

```php
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;

$logoutHandler = new SamlLogoutHandler(
    authFactory: $factory,
    redirectAfterLogout: home_url(),
);

// SP-Initiated Logout: IdP に LogoutRequest を送信
$logoutHandler->initiateLogout(
    nameId: $nameId,
    sessionIndex: $sessionIndex,
    returnTo: home_url(),
);

// IdP-Initiated Logout: IdP からの LogoutRequest を処理
if ($logoutHandler->isLogoutRequest()) {
    $logoutHandler->handleIdpLogoutRequest();
}
```

| メソッド | 説明 |
|---------|------|
| `initiateLogout()` | IdP に LogoutRequest を送信（`never` 返却） |
| `handleIdpLogoutRequest()` | IdP からの LogoutRequest を処理し、`wp_logout()` + Cookie クリア |
| `isLogoutRequest()` | `$_GET['SAMLRequest']` の存在チェック |
| `isLogoutResponse()` | `$_GET['SAMLResponse']` の存在チェック |

### SamlAttributesBadge

SAML レスポンスの属性情報を保持する Badge です。Passport に自動追加されます:

```php
use WpPack\Component\Security\Bridge\SAML\Badge\SamlAttributesBadge;

// Passport から取得
$badge = $passport->getBadge(SamlAttributesBadge::class);

$nameId = $badge->getNameId();
$email = $badge->getAttribute('email');
$groups = $badge->getAttributeValues('groups');
$sessionIndex = $badge->getSessionIndex();
```

### SamlResponseReceivedEvent

SAMLResponse の検証成功後、ユーザー解決前にディスパッチされるイベントです:

```php
use WpPack\Component\Security\Bridge\SAML\Event\SamlResponseReceivedEvent;

final class SamlAuditListener
{
    public function __invoke(SamlResponseReceivedEvent $event): void
    {
        $nameId = $event->getNameId();
        $attributes = $event->getAttributes();
        $sessionIndex = $event->getSessionIndex();

        // 監査ログの記録等
    }
}
```

## ユーザー解決

### SamlUserResolverInterface

SAML の NameID と属性から WordPress ユーザーを解決するインターフェースです:

```php
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;

interface SamlUserResolverInterface
{
    /**
     * @param array<string, list<string>> $attributes
     */
    public function resolveUser(string $nameId, array $attributes): \WP_User;
}
```

### SamlUserResolver（デフォルト実装）

デフォルトのユーザー解決ロジックを提供します:

```php
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;

$userResolver = new SamlUserResolver(
    autoProvision: true,               // ユーザーが存在しない場合に自動作成
    defaultRole: 'subscriber',         // 新規ユーザーのデフォルトロール
    emailAttribute: 'email',           // メールアドレス属性名
    firstNameAttribute: 'firstName',   // 名属性名
    lastNameAttribute: 'lastName',     // 姓属性名
    displayNameAttribute: 'displayName', // 表示名属性名
    roleMapping: [                     // ロールマッピング
        'Admin' => 'administrator',
        'Editor' => 'editor',
        'Author' => 'author',
    ],
    roleAttribute: 'groups',          // ロール属性名
);
```

### JIT プロビジョニング

`autoProvision: true` を設定すると、SAML 認証成功時にユーザーが存在しない場合、WordPress ユーザーを自動作成します:

1. `email` 属性（`sanitize_email()` 済み）でメールアドレス検索
2. NameID（`sanitize_user()` 済み）でログイン名検索
3. いずれも見つからない場合、`wp_insert_user()` で新規作成

#### NameID バインディング

初回ログイン時、SAML NameID がユーザーメタ（`_wppack_saml_nameid`）に保存されます。以降のログインでは、メールアドレスで既存ユーザーが見つかった場合でも、保存済み NameID との一致を検証します。これにより、IdP 側でメールアドレスを変更してもアカウント乗っ取りを防止します。

#### 属性サニタイズ

すべての SAML 属性は WordPress 関数でサニタイズされてからデータベースに保存されます:

- NameID: `sanitize_user($nameId, true)` — 空文字になる場合は認証エラー
- メールアドレス: `sanitize_email()`
- 名前系フィールド（first_name, last_name, display_name）: `sanitize_text_field()`

既存ユーザーの場合は、SAML 属性に基づいてプロフィール情報（名前、表示名）を同期更新します。

### ロールマッピング

`roleMapping` と `roleAttribute` を設定すると、SAML 属性からの WordPress ロール自動マッピングが有効になります:

```php
$userResolver = new SamlUserResolver(
    roleMapping: [
        'IdP-Admins' => 'administrator',
        'IdP-Editors' => 'editor',
        'IdP-Authors' => 'author',
        'IdP-Members' => 'subscriber',
    ],
    roleAttribute: 'memberOf',
);
```

`roleAttribute` で指定した SAML 属性の値が `roleMapping` のキーと一致する場合、対応する WordPress ロールが設定されます。最初にマッチしたロールが適用されます。

### カスタム UserResolver

独自のユーザー解決ロジックが必要な場合は `SamlUserResolverInterface` を実装します:

```php
final class LdapSamlUserResolver implements SamlUserResolverInterface
{
    public function resolveUser(string $nameId, array $attributes): \WP_User
    {
        // LDAP 連携によるユーザー解決
        $employeeId = $attributes['employeeNumber'][0] ?? null;

        // カスタムロジック...
    }
}
```

## マルチサイト対応

### 基本的なマルチサイト認証

シングルサイトまたはマルチサイトの同一サイトでの認証は、追加設定なしで動作します。`SamlAuthenticator` はマルチサイト環境を検出すると、`PostAuthenticationToken` にブログ ID を自動設定します。

`addUserToBlog: true`（デフォルト）の場合、認証成功時にユーザーが現在のブログのメンバーでなければ自動追加します。

### クロスサイト SSO (CrossSiteRedirector)

マルチサイトで複数のサブサイトが異なるドメインを持つ場合、IdP の ACS URL はメインサイト 1 つに設定し、`CrossSiteRedirector` で適切なサブサイトにリダイレクトします。

```php
use WpPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;

$redirector = new CrossSiteRedirector(
    allowedHosts: ['main.example.com', 'sub.example.com'],
);

$authenticator = new SamlAuthenticator(
    authFactory: $factory,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    crossSiteRedirector: $redirector,
);
```

### フロー図

```
[ユーザーが sub.example.com でログインクリック]
    ↓
SamlEntryPoint::start(returnTo: 'https://sub.example.com/wp-admin/')
    ↓ RelayState = 'https://sub.example.com/wp-admin/'
[IdP で認証]
    ↓ SAMLResponse を main.example.com/saml/acs に POST
SamlAuthenticator（main.example.com）
    ↓ CrossSiteRedirector::needsRedirect() → true
    ↓ auto-submit フォームで sub.example.com/saml/acs にリダイレクト
SamlAuthenticator（sub.example.com）
    ↓ 通常の認証フロー
    ↓ wp_set_auth_cookie()
    ↓ sub.example.com/wp-admin/ にリダイレクト
```

`CrossSiteRedirector` は以下の方法でホストを許可します:
- `allowedHosts` に明示的に指定（開発環境のドメインもここに追加）
- マルチサイトの `get_sites()` に登録されたホスト

> [!NOTE]
> リダイレクト先の ACS URL は常に HTTPS が強制されます。

## IdP 設定ガイド

### Okta

```php
$idpSettings = new IdpSettings(
    entityId: 'http://www.okta.com/exk...',
    ssoUrl: 'https://your-org.okta.com/app/your-app/exk.../sso/saml',
    sloUrl: 'https://your-org.okta.com/app/your-app/exk.../slo/saml',
    x509Cert: '...',  // Okta 管理画面 > SAML Signing Certificates
);

$spSettings = new SpSettings(
    entityId: 'https://example.com',
    acsUrl: 'https://example.com/saml/acs',
);
```

Okta 管理画面での設定:
- **Single sign-on URL**: `https://example.com/saml/acs`
- **Audience URI (SP Entity ID)**: `https://example.com`
- **Name ID format**: EmailAddress

### Azure AD (Entra ID)

```php
$idpSettings = new IdpSettings(
    entityId: 'https://sts.windows.net/{tenant-id}/',
    ssoUrl: 'https://login.microsoftonline.com/{tenant-id}/saml2',
    sloUrl: 'https://login.microsoftonline.com/{tenant-id}/saml2',
    x509Cert: '...',  // Azure Portal > Enterprise Applications > SAML Signing Certificate
);

$spSettings = new SpSettings(
    entityId: 'https://example.com',
    acsUrl: 'https://example.com/saml/acs',
);
```

Azure Portal での設定:
- **Identifier (Entity ID)**: `https://example.com`
- **Reply URL (Assertion Consumer Service URL)**: `https://example.com/saml/acs`
- **Sign on URL**: `https://example.com/saml/login`

### Google Workspace

```php
$idpSettings = new IdpSettings(
    entityId: 'https://accounts.google.com/o/saml2?idpid=...',
    ssoUrl: 'https://accounts.google.com/o/saml2/idp?idpid=...',
    sloUrl: null,  // Google Workspace は SLO 未サポート
    x509Cert: '...',  // Google Admin > Apps > SAML apps > Certificate
);

$spSettings = new SpSettings(
    entityId: 'https://example.com',
    acsUrl: 'https://example.com/saml/acs',
);
```

Google Admin での設定:
- **ACS URL**: `https://example.com/saml/acs`
- **Entity ID**: `https://example.com`
- **Name ID**: Basic Information > Primary email

## セキュリティ考慮事項

| 脅威 | 対策 | 設定 |
|------|------|------|
| リプレイ攻撃 | `onelogin/php-saml` が InResponseTo を検証 | `strict: true` |
| 署名偽造 | アサーション署名の検証 | `wantAssertionsSigned: true` |
| NameID 傍受 | NameID の暗号化 | `wantNameIdEncrypted: true` |
| オープンリダイレクト | 同一オリジンチェック + `wp_validate_redirect()` で RelayState を検証 | `SamlAuthenticator` |
| アカウント乗っ取り | NameID バインディングによるメール照合の安全性確保 | `SamlUserResolver` |
| XSS / プロフィール改ざん | SAML 属性を `sanitize_text_field()` / `sanitize_email()` でサニタイズ | `SamlUserResolver` |
| セッション固定 | 認証成功時に既存セッションをクリアしてから再発行 | `SamlAuthenticator` |
| 情報漏洩 | エラーメッセージを汎用化、詳細はフック経由で通知 | `SamlAuthenticator` |
| クロスサイト攻撃 | `allowedHosts` でリダイレクト先を制限、HTTPS 強制 | `CrossSiteRedirector` |
| プロビジョニング失敗時の情報漏洩防止 | `wp_insert_user()` のエラー詳細を例外に含めず、`wppack_saml_user_provision_failed` フックで通知 | `SamlUserResolver` |
| ユーザー列挙 | 認証失敗時の一律エラーページ | `onAuthenticationFailure()` |
| 権限昇格 | JIT プロビジョニング時のデフォルトロール制限 | `SamlUserResolver` |

本番環境では以下の設定を**必須**:
- `strict: true`（**必須** — 署名検証・InResponseTo 検証を強制。`false` にするとリプレイ攻撃や署名偽造に対して脆弱になる）
- `wantAssertionsSigned: true`（必須）
- `debug: false`
- HTTPS の使用（ACS URL、SLO URL）

### セキュリティイベントフック

監査ログやモニタリングのために、以下の WordPress アクションフックが発火されます:

| フック名 | 発火タイミング | パラメータ |
|---------|-------------|-----------|
| `wppack_saml_authenticated` | SAML 認証成功時 | `$nameId`, `$attributes` |
| `wppack_saml_authentication_failed` | 認証失敗時 | `$exception` |
| `wppack_saml_authentication_error` | SAML レスポンス検証エラー時 | `$errors`, `$lastErrorReason` |
| `wppack_saml_user_provisioned` | JIT プロビジョニングでユーザー作成時 | `$user`, `$nameId`, `$attributes` |
| `wppack_saml_user_provision_failed` | JIT ユーザー作成失敗時 | `$nameId`, `$wpError` |
| `wppack_saml_user_updated` | 既存ユーザーの属性同期更新時 | `$user`, `$attributes` |
| `wppack_saml_cross_site_redirect` | クロスサイトリダイレクト実行時 | `$targetUrl` |

```php
// 使用例: 認証イベントのロギング（LoggerInterface を DI またはクロージャ経由で注入）
add_action('wppack_saml_authenticated', function (string $nameId, array $attributes) use ($logger): void {
    $logger->info('SAML login', ['nameId' => $nameId]);
});

add_action('wppack_saml_authentication_error', function (array $errors, ?string $reason) use ($logger): void {
    $logger->error('SAML error', ['errors' => $errors, 'reason' => $reason]);
});
```

## 依存関係

### 必須
- **wppack/security** — Security コンポーネント（`AuthenticatorInterface`, `Passport`, `Badge` 等）
- **onelogin/php-saml** ^4.0 — SAML 2.0 プロトコル処理

### 推奨
- **wppack/event-dispatcher** — `SamlResponseReceivedEvent` のディスパッチ
- **wppack/http-foundation** — `Request` オブジェクト
- **wppack/dependency-injection** — DI コンテナ統合
