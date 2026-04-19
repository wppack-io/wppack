# OAuthSecurity

OAuth 2.0 / OpenID Connect 認証ブリッジ

## 概要

| 項目 | 値 |
|------|-----|
| パッケージ名 | `wppack/oauth-security` |
| 名前空間 | `WPPack\Component\Security\Bridge\OAuth\` |
| レイヤー | Abstraction（Bridge） |
| 依存 | `wppack/security`, `firebase/php-jwt` |

外部 IdP による OAuth 2.0 / OpenID Connect SSO 認証を WPPack Security コンポーネントに統合する Bridge パッケージです。19 種の専用プロバイダーと Generic OIDC プロバイダーをサポートし、`firebase/php-jwt` を使用した ID トークン検証と自前の OAuth/OIDC フロー実装を組み合わせています。

## インストール

```bash
composer require wppack/oauth-security
```

## 設定

### OAuthConfiguration

OAuth / OIDC の設定を定義します:

```php
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;

$configuration = new OAuthConfiguration(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    redirectUri: 'https://example.com/oauth/callback',
    scopes: ['openid', 'email', 'profile'],
    pkceEnabled: true,                                // PKCE（推奨）
);
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `clientId` | `string` | — | OAuth クライアント ID |
| `clientSecret` | `string` | — | OAuth クライアントシークレット |
| `redirectUri` | `string` | — | コールバック URL |
| `scopes` | `list<string>` | `['openid', 'email', 'profile']` | 要求スコープ |
| `authorizationEndpoint` | `?string` | `null` | 認可エンドポイント（Generic 用） |
| `tokenEndpoint` | `?string` | `null` | トークンエンドポイント |
| `userinfoEndpoint` | `?string` | `null` | UserInfo エンドポイント |
| `jwksUri` | `?string` | `null` | JWKS URI |
| `issuer` | `?string` | `null` | 発行者 |
| `discoveryUrl` | `?string` | `null` | OIDC Discovery URL |
| `endSessionEndpoint` | `?string` | `null` | ログアウトエンドポイント |
| `pkceEnabled` | `bool` | `true` | PKCE の有効化 |

## プロバイダー

### ProviderInterface

各 IdP の差異を吸収するインターフェースです:

```php
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
```

### GoogleProvider

Google アカウント（個人）および Google Workspace に対応:

```php
use WPPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;

$provider = new GoogleProvider(
    configuration: $configuration,
    hostedDomain: 'example.com',  // Workspace ドメイン制限（null=全アカウント許可）
);
```

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `hostedDomain` | `null\|string\|list<string>` | `null`: 全アカウント許可、`string`: 単一ドメイン制限、`array`: 複数ドメイン |

- OIDC 対応: `supportsOidc() = true`
- RP-Initiated Logout: 非対応（`getEndSessionEndpoint() = null`）
- `hd` パラメータによるドメインヒント + ID トークンの `hd` クレームのサーバーサイド検証

### EntraIdProvider

Microsoft Entra ID（旧 Azure AD）に対応:

```php
use WPPack\Component\Security\Bridge\OAuth\Provider\EntraIdProvider;

$provider = new EntraIdProvider(
    configuration: $configuration,
    tenantId: 'your-tenant-id',
);
```

- OIDC 対応、RP-Initiated Logout 対応
- テナント ID からエンドポイントを自動構築
- `prompt=select_account` をデフォルトで追加

### GitHubProvider

GitHub OAuth 2.0 に対応:

```php
$provider = new GitHubProvider(configuration: $configuration);
```

- OAuth 2.0（OIDC 非対応）
- `/user` API でユーザー情報を取得、OIDC 標準クレームに正規化

### AppleProvider

Sign in with Apple に対応:

```php
$provider = new AppleProvider(configuration: $configuration);
```

- OIDC 対応、`response_mode=form_post`
- ユーザー情報は ID トークンから取得（UserInfo エンドポイントなし）

### DiscordProvider

Discord OAuth 2.0 に対応:

```php
$provider = new DiscordProvider(configuration: $configuration);
```

- OAuth 2.0（OIDC 非対応）
- デフォルトスコープ: `identify`, `email`
- アバター URL を自動構築

### FacebookProvider

Facebook Login に対応:

```php
$provider = new FacebookProvider(configuration: $configuration);
```

- OAuth 2.0（OIDC 非対応）、Graph API v21.0
- デフォルトスコープ: `email`, `public_profile`

### SlackProvider

Slack の OpenID Connect に対応:

```php
$provider = new SlackProvider(configuration: $configuration);
```

- OIDC 対応、標準クレーム

### LineProvider

LINE Login v2.1 に対応:

```php
$provider = new LineProvider(configuration: $configuration);
```

- OIDC 対応
- `userId` → `sub`、`displayName` → `name` に正規化

### ドメインベースプロバイダー

Okta, Auth0, OneLogin, Keycloak, Cognito は `domain` パラメータからエンドポイントを自動構築します。Discovery URL のユーザー指定は不要です:

```php
use WPPack\Component\Security\Bridge\OAuth\Provider\OktaProvider;

$provider = new OktaProvider(
    configuration: $configuration,
    domain: 'dev-123456.okta.com',
);
```

| クラス | domain 例 |
|-------|-----------|
| `OktaProvider` | `dev-123456.okta.com` |
| `Auth0Provider` | `your-tenant.auth0.com` |
| `OneLoginProvider` | `your-company.onelogin.com` |
| `KeycloakProvider` | `keycloak.example.com/realms/myrealm` |
| `CognitoProvider` | `your-domain.auth.us-east-1.amazoncognito.com` |

すべて OIDC 対応、Discovery Document による動的エンドポイント取得にも対応。

### GenericOidcProvider

任意の OIDC 準拠 IdP に対応:

```php
$provider = new GenericOidcProvider(configuration: $configuration);
```

OIDC Discovery（`.well-known/openid-configuration`）によるエンドポイント自動取得に対応。`OAuthConfiguration` の `discoveryUrl` でディスカバリー URL を指定します。

### YahooProvider

Yahoo (global) の OpenID Connect に対応:

```php
$provider = new YahooProvider(configuration: $configuration);
```

- OIDC 対応
- Discovery URL: `https://api.login.yahoo.com/.well-known/openid-configuration`

### YahooJapanProvider

Yahoo! JAPAN YConnect v2 に対応:

```php
$provider = new YahooJapanProvider(configuration: $configuration);
```

- OIDC 対応
- Discovery URL: `https://auth.login.yahoo.co.jp/yconnect/v2/.well-known/openid-configuration`

### DAccountProvider

NTT docomo d Account Connect に対応:

```php
$provider = new DAccountProvider(configuration: $configuration);
```

- OIDC 対応
- Discovery URL: `https://conf.uw.docomo.ne.jp/.well-known/openid-configuration`

### AmazonProvider

Login with Amazon (LWA) に対応:

```php
$provider = new AmazonProvider(configuration: $configuration);
```

- OAuth 2.0（OIDC 非対応）
- デフォルトスコープ: `profile`
- `user_id` → `sub` に正規化

### MicrosoftProvider

Microsoft 個人アカウント（outlook.com、live.com 等）に対応:

```php
$provider = new MicrosoftProvider(configuration: $configuration);
```

- OIDC 対応、テナント `consumers` 固定
- Entra ID と同じ Microsoft identity platform を使用（組織アカウントは `EntraIdProvider` を使用）

### ProviderDefinition / ProviderRegistry

各プロバイダーは `ProviderDefinition` を返す `definition()` メソッドを実装しています:

```php
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderDefinition;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderRegistry;

// 全プロバイダー定義を取得
$definitions = ProviderRegistry::definitions();

// 特定プロバイダーの定義
$def = ProviderRegistry::definition('google');
$def->type;           // 'google'
$def->label;          // 'Google'
$def->dropdownLabel;  // 'Google'
$def->oidc;           // true
$def->requiredFields; // []
$def->defaultScopes;  // ['openid', 'email', 'profile']

// プロバイダークラスを取得
$class = ProviderRegistry::providerClass('google'); // GoogleProvider::class
```

`ProviderDefinition` のプロパティ:

| プロパティ | 型 | 説明 |
|-----------|------|------|
| `type` | `string` | プロバイダー識別子（`google`, `entra-id` 等） |
| `label` | `string` | 表示名（`Google`, `Entra ID` 等） |
| `dropdownLabel` | `string` | ドロップダウン用表示名（`Google`, `Microsoft Entra ID` 等） |
| `oidc` | `bool` | OIDC 対応かどうか |
| `requiredFields` | `list<string>` | 追加必須フィールド（`['domain']`, `['tenant_id']` 等） |
| `defaultScopes` | `list<string>` | デフォルトスコープ |

## 認証フロー

### シングルサイト

```
[ユーザーがログインボタンをクリック]
    ↓
OAuthEntryPoint::start()
    ↓ state, nonce, PKCE code_verifier 生成 → transient に保存
    ↓ IdP の authorization endpoint にリダイレクト
[IdP でユーザー認証]
    ↓ ?code=xxx&state=yyy で callback URL にリダイレクト
OAuthAuthenticator::supports() → true（GET + code + state）
    ↓
OAuthAuthenticator::authenticate()
    ↓ state 検証（transient から取得、ワンタイム）
    ↓ code → token 交換（TokenExchanger）
    ↓ ID Token 検証（IdTokenValidator + JwksProvider）
    ↓ OAuthResponseReceivedEvent ディスパッチ
    ↓ OAuthUserResolver でユーザー解決
SelfValidatingPassport を返却
    ↓
OAuthAuthenticator::createToken()
    ↓
OAuthAuthenticator::onAuthenticationSuccess()
    ↓ wp_clear_auth_cookie() で既存セッションクリア
    ↓ wp_set_auth_cookie() でセッション確立
    ↓ returnTo にリダイレクト
```

### マルチサイト（クロスサイト SSO）

```
[ユーザーが sub.example.com でログインクリック]
    ↓
OAuthEntryPoint::start(returnTo: 'https://sub.example.com/wp-admin/')
    ↓ state に returnTo を保存
    ↓ IdP にリダイレクト（callback URL = main.example.com/oauth/callback）
[IdP で認証]
    ↓ main.example.com でコールバック受信
OAuthAuthenticator::authenticate()
    ↓ 通常の認証フロー実行
    ↓ CrossSiteRedirector::needsRedirect(returnTo) → true
    ↓ ワンタイムトークン生成（HMAC 署名 + transient 保存）
    ↓ auto-submit フォームで sub.example.com/oauth/verify に POST
OAuthAuthenticator（sub.example.com）
    ↓ supports() → true（POST + _wppack_oauth_token）
    ↓ トークン検証 → user 取得 → wp_set_auth_cookie()
    ↓ returnTo にリダイレクト
```

## コンポーネント

### OAuthEntryPoint

OAuth 認証フローのエントリポイントです:

```php
use WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;

$entryPoint = new OAuthEntryPoint(
    provider: $provider,
    configuration: $configuration,
    stateStore: $stateStore,
);

// IdP にリダイレクト（処理は戻らない）
$entryPoint->start(returnTo: admin_url());

// ログイン URL のみ取得（リダイレクトしない）
$loginUrl = $entryPoint->getLoginUrl(returnTo: admin_url());
echo '<a href="' . esc_attr($loginUrl) . '">OAuth ログイン</a>';
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
| `login_url` フィルター | `wp-login.php` の URL を IdP 認可 URL に差し替え |
| `login_init` アクション | `wp-login.php` への GET アクセスを IdP にリダイレクト |

`$_GET['action']` がある場合（`logout`, `lostpassword` 等）はリダイレクトをスキップし、WordPress 標準フローを維持します。

### OAuthAuthenticator

Security コンポーネントの `AuthenticatorInterface` を実装する中核クラスです:

```php
use WPPack\Component\Security\Bridge\OAuth\OAuthAuthenticator;

$authenticator = new OAuthAuthenticator(
    provider: $provider,
    configuration: $configuration,
    stateStore: $stateStore,
    tokenExchanger: $tokenExchanger,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    callbackPath: '/oauth/callback',        // コールバックパス
    idTokenValidator: $idTokenValidator,     // OIDC の場合
    jwksProvider: $jwksProvider,             // OIDC の場合
    crossSiteRedirector: null,              // マルチサイト用
    httpClient: $httpClient,                // GitHub 等の UserInfo 取得用
    addUserToBlog: true,                    // マルチサイトでブログに自動追加
    verifyPath: '/oauth/verify',            // クロスサイト検証パス
);
```

- `supports()`: GET + `code` + `state` パラメータで `callbackPath` に一致、または POST + `_wppack_oauth_token` で `verifyPath` に一致
- `authenticate()`: state 検証 → code→token 交換 → ID Token 検証 → ユーザー解決
- `onAuthenticationSuccess()`: セッション確立 + リダイレクト
- `onAuthenticationFailure()`: `wp-login.php?oauth_error=1` にリダイレクト

### OAuthLogoutHandler

OIDC RP-Initiated Logout（RFC）に対応:

```php
use WPPack\Component\Security\Bridge\OAuth\OAuthLogoutHandler;

$logoutHandler = new OAuthLogoutHandler(
    provider: $provider,
    configuration: $configuration,
    redirectAfterLogout: home_url(),
);

// RP-Initiated Logout（IdP にリダイレクト）またはローカルログアウト
$logoutHandler->initiateLogout(
    idToken: $savedIdToken,
    returnTo: home_url(),
);

// ローカルログアウトのみ
$logoutHandler->handleLocalLogout();

// RP-Initiated Logout 対応チェック
if ($logoutHandler->supportsRemoteLogout()) {
    // IdP ログアウト URL を表示
}
```

### OAuthTokenBadge

OAuth トークン情報を保持する Badge です。Passport に自動追加されます:

```php
use WPPack\Component\Security\Bridge\OAuth\Badge\OAuthTokenBadge;

$badge = $passport->getBadge(OAuthTokenBadge::class);
$subject = $badge->getSubject();
$email = $badge->getClaim('email');
$tokenSet = $badge->getTokenSet();
```

### OAuthResponseReceivedEvent

トークン検証成功後、ユーザー解決前にディスパッチされるイベントです:

```php
use WPPack\Component\Security\Bridge\OAuth\Event\OAuthResponseReceivedEvent;

final class OAuthAuditListener
{
    public function __invoke(OAuthResponseReceivedEvent $event): void
    {
        $subject = $event->getSubject();
        $claims = $event->getClaims();
        $tokenSet = $event->getTokenSet();

        // 監査ログの記録等
    }
}
```

## ユーザー解決

### OAuthUserResolverInterface

OAuth のサブジェクト ID とクレームから WordPress ユーザーを解決するインターフェースです:

```php
use WPPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;

interface OAuthUserResolverInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function resolveUser(string $subject, array $claims): \WP_User;
}
```

### OAuthUserResolver（デフォルト実装）

```php
use WPPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolver;

$userResolver = new OAuthUserResolver(
    providerName: 'google',               // プロバイダー名（meta key に使用）
    autoProvision: true,                   // JIT プロビジョニング
    defaultRole: 'subscriber',             // 新規ユーザーのデフォルトロール
    emailClaim: 'email',                   // メールアドレスクレーム
    firstNameClaim: 'given_name',          // 名クレーム（OIDC 標準）
    lastNameClaim: 'family_name',          // 姓クレーム（OIDC 標準）
    displayNameClaim: 'name',              // 表示名クレーム
    roleMapping: [                         // ロールマッピング
        'admin' => 'administrator',
        'editor' => 'editor',
    ],
    roleClaim: 'role',                     // ロールクレーム名
);
```

### サブジェクト ID バインディング

初回ログイン時、OAuth サブジェクト ID がユーザーメタ（`_wppack_oauth_sub_{providerName}`）に保存されます。プロバイダー名ごとに異なるメタキーを使用するため、複数の IdP を安全に併用できます。

メールアドレスで既存ユーザーが見つかった場合、保存済みサブジェクト ID との一致を検証し、アカウント乗っ取りを防止します。

### JIT プロビジョニング

`autoProvision: true` で、認証成功時にユーザーが存在しない場合に自動作成:

1. サブジェクト ID でメタ検索
2. `email` クレーム（`sanitize_email()` 済み）でメールアドレス検索
3. サブジェクト ID（`sanitize_user()` 済み）でログイン名検索
4. いずれも見つからない場合、`wp_insert_user()` で新規作成

## マルチサイト対応

### CrossSiteRedirector

マルチサイトで複数のサブサイトが異なるドメインを持つ場合に使用します:

```php
use WPPack\Component\Security\Bridge\OAuth\Multisite\CrossSiteRedirector;

$redirector = new CrossSiteRedirector(
    allowedHosts: ['main.example.com', 'sub.example.com'],
    verifyPath: '/oauth/verify',
);

$authenticator = new OAuthAuthenticator(
    // ...
    crossSiteRedirector: $redirector,
);
```

SAML Bridge の `CrossSiteRedirector` と同じパターンですが、SAMLResponse の代わりに HMAC 署名付きワンタイムトークンを使用します:

- `redirect()`: ワンタイムトークン生成（`wp_hash()` + transient 保存）→ auto-submit フォーム
- `verifyToken()`: transient からトークン取得・削除（ワンタイム）→ HMAC 検証 → user ID 返却
- `isHostAllowed()`: `allowedHosts` + multisite `get_sites()` チェック

> [!NOTE]
> リダイレクト先 URL は常に HTTPS が強制されます。`.local` / `.localhost` ドメインは自動許可されません。

## PKCE (Proof Key for Code Exchange)

OAuth 2.0 の認可コード横取り攻撃を防止する PKCE（RFC 7636）をデフォルトで有効化しています:

```php
use WPPack\Component\Security\Bridge\OAuth\Pkce\PkceGenerator;

// 自動的に OAuthEntryPoint で使用される
$pkce = PkceGenerator::generate();
// ['code_verifier' => '...', 'code_challenge' => '...', 'code_challenge_method' => 'S256']
```

`OAuthConfiguration` で `pkceEnabled: false` を設定して無効化できますが、セキュリティ上推奨しません。

## トークン管理

### TokenExchanger

認可コードをトークンに交換します:

```php
use WPPack\Component\Security\Bridge\OAuth\Token\TokenExchanger;

$exchanger = new TokenExchanger($httpClient);
$tokenSet = $exchanger->exchange(
    tokenEndpoint: $provider->getTokenEndpoint(),
    code: $authorizationCode,
    redirectUri: $configuration->getRedirectUri(),
    clientId: $configuration->getClientId(),
    clientSecret: $configuration->getClientSecret(),
    codeVerifier: $codeVerifier,
);
```

### TokenRefresher

リフレッシュトークンで新しいアクセストークンを取得します:

```php
use WPPack\Component\Security\Bridge\OAuth\Token\TokenRefresher;

$refresher = new TokenRefresher($httpClient);
$newTokenSet = $refresher->refresh(
    tokenEndpoint: $provider->getTokenEndpoint(),
    refreshToken: $savedRefreshToken,
    clientId: $configuration->getClientId(),
    clientSecret: $configuration->getClientSecret(),
);
```

### IdTokenValidator

`firebase/php-jwt` を使用して ID トークンの署名とクレームを検証します:

```php
use WPPack\Component\Security\Bridge\OAuth\Token\IdTokenValidator;

$validator = new IdTokenValidator();
$claims = $validator->validate(
    idToken: $tokenSet->getIdToken(),
    nonce: $storedNonce,
    clientId: $configuration->getClientId(),
    issuer: $provider->getIssuer(),
    jwks: $jwksProvider->getKeys($provider->getJwksUri()),
);
```

検証項目: `iss`, `aud`, `exp`, `iat`, `nonce`, `azp`

### OidcDiscovery / JwksProvider

OIDC Discovery ドキュメントと JWKS キーセットを取得・キャッシュします:

```php
use WPPack\Component\Security\Bridge\OAuth\Token\OidcDiscovery;
use WPPack\Component\Security\Bridge\OAuth\Token\JwksProvider;

$discovery = new OidcDiscovery($httpClient);
$document = $discovery->discover('https://accounts.google.com/.well-known/openid-configuration');

$jwksProvider = new JwksProvider($httpClient);
$keys = $jwksProvider->getKeys($document->getJwksUri());
```

キャッシュ TTL: Discovery = 24 時間、JWKS = 1 時間（WordPress transient 使用）

## IdP 設定ガイド

### Google

```php
$configuration = new OAuthConfiguration(
    clientId: 'xxx.apps.googleusercontent.com',
    clientSecret: 'GOCSPX-xxx',
    redirectUri: 'https://example.com/oauth/callback',
    scopes: ['openid', 'email', 'profile'],
);

$provider = new GoogleProvider(
    configuration: $configuration,
    hostedDomain: 'example.com',  // Workspace のみに制限する場合
);
```

Google Cloud Console での設定:
- **認証情報** > OAuth 2.0 クライアント ID を作成
- **承認済みのリダイレクト URI**: `https://example.com/oauth/callback`

### Azure AD (Entra ID)

```php
$configuration = new OAuthConfiguration(
    clientId: 'your-application-id',
    clientSecret: 'your-client-secret',
    redirectUri: 'https://example.com/oauth/callback',
    scopes: ['openid', 'email', 'profile'],
);

$provider = new AzureProvider(
    configuration: $configuration,
    tenantId: 'your-tenant-id',
);
```

Azure Portal での設定:
- **アプリの登録** > 新規登録
- **リダイレクト URI**: `https://example.com/oauth/callback`（Web プラットフォーム）
- **証明書とシークレット** > 新しいクライアントシークレット

### GitHub

```php
$configuration = new OAuthConfiguration(
    clientId: 'your-github-client-id',
    clientSecret: 'your-github-client-secret',
    redirectUri: 'https://example.com/oauth/callback',
    scopes: ['read:user', 'user:email'],
    pkceEnabled: false,  // GitHub は PKCE 非対応
);

$provider = new GitHubProvider(
    configuration: $configuration,
);
```

GitHub Settings での設定:
- **Developer settings** > OAuth Apps > New OAuth App
- **Authorization callback URL**: `https://example.com/oauth/callback`

> [!NOTE]
> GitHub は OIDC 非対応のため、ID トークンは発行されません。UserInfo は `/user` API から取得されます。`httpClient` パラメータを `OAuthAuthenticator` に渡す必要があります。

## セキュリティ考慮事項

| 脅威 | 対策 | 実装箇所 |
|------|------|----------|
| 認可コード横取り | PKCE (S256) | `PkceGenerator` |
| CSRF | state パラメータ（ワンタイム transient） | `OAuthStateStore` |
| トークン偽造 | ID Token の署名検証（JWKS） | `IdTokenValidator` |
| リプレイ攻撃 | nonce 検証 + state ワンタイム消費 | `OAuthAuthenticator` |
| オープンリダイレクト | 同一オリジンチェック + `wp_validate_redirect()` | `OAuthAuthenticator` |
| アカウント乗っ取り | サブジェクト ID バインディング | `OAuthUserResolver` |
| XSS / プロフィール改ざん | クレームのサニタイズ（`sanitize_text_field()` / `sanitize_email()`） | `OAuthUserResolver` |
| セッション固定 | 認証成功時に既存セッションクリア→再発行 | `OAuthAuthenticator` |
| 情報漏洩（エラーメッセージ） | 全コンポーネントでエラーメッセージ汎用化 | `JwksProvider`, `OidcDiscovery`, `IdTokenValidator`, `TokenExchanger`, `TokenRefresher` |
| MITM / ダウングレード攻撃 | 全エンドポイントで HTTPS 強制 | `TokenExchanger`, `TokenRefresher`, `OidcDiscovery`, `JwksProvider`, `DiscoveryDocument` |
| 不正メール形式によるユーザー解決エラー | `filter_var()` によるメール形式検証 | `OAuthUserResolver` |
| クロスサイト攻撃 | `allowedHosts` + HMAC 署名トークン + HTTPS 強制 | `CrossSiteRedirector` |
| Workspace ドメイン詐称 | `hd` クレームのサーバーサイド検証 | `GoogleProvider` |
| プロビジョニング失敗時の情報漏洩防止 | `wp_insert_user()` のエラー詳細を例外に含めず、`wppack_oauth_user_provision_failed` フックで通知 | `OAuthUserResolver` |
| ユーザー列挙 | 認証失敗時の一律エラーページ | `onAuthenticationFailure()` |

## セキュリティイベントフック

| フック名 | 発火タイミング | パラメータ |
|---------|-------------|-----------|
| `wppack_oauth_authenticated` | 認証成功時 | `$subject`, `$claims` |
| `wppack_oauth_authentication_failed` | 認証失敗時 | `$exception` |
| `wppack_oauth_authentication_error` | トークン/検証エラー時 | `$errorCode`, `$errorDescription` |
| `wppack_oauth_user_provisioned` | JIT ユーザー作成時 | `$user`, `$subject`, `$claims` |
| `wppack_oauth_user_provision_failed` | JIT ユーザー作成失敗時 | `$subject`, `$wpError` |
| `wppack_oauth_user_updated` | 属性同期更新時 | `$user`, `$claims` |
| `wppack_oauth_cross_site_redirect` | クロスサイトリダイレクト時 | `$targetUrl` |
| `wppack_oauth_token_refreshed` | トークンリフレッシュ成功時 | `$userId`, `$tokenSet` |
| `wppack_oauth_logout` | ログアウト実行時 | `$userId`, `$remote` |

```php
// 使用例: 認証イベントのロギング（LoggerInterface を DI またはクロージャ経由で注入）
add_action('wppack_oauth_authenticated', function (string $subject, array $claims) use ($logger): void {
    $logger->info('OAuth login', ['subject' => $subject, 'email' => $claims['email'] ?? null]);
});

add_action('wppack_oauth_authentication_error', function (string $errorCode, string $desc) use ($logger): void {
    $logger->error('OAuth error', ['code' => $errorCode, 'description' => $desc]);
});
```

## 依存関係

### 必須
- **wppack/security** — Security コンポーネント（`AuthenticatorInterface`, `Passport`, `Badge` 等）
- **firebase/php-jwt** ^7.0 — JWT / JWK 処理（ランタイム依存ゼロ）

### 推奨
- **wppack/event-dispatcher** — `OAuthResponseReceivedEvent` のディスパッチ
- **wppack/http-foundation** — `Request` オブジェクト
- **wppack/http-client** — IdP との HTTP 通信
- **wppack/dependency-injection** — DI コンテナ統合
