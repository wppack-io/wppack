# Security Component

**パッケージ:** `wppack/security`
**名前空間:** `WPPack\Component\Security\`
**Category:** Identity & Security

WordPress 上でプラガブルな認証・認可フレームワークを提供するコンポーネントです。Authenticator パターンによるリクエストベース認証、Passport/Badge による認証要件の値オブジェクト化、Voter ベースの認可チェックを提供します。OAuth / SAML / 2FA は Bridge パッケージとして拡張可能です。

## インストール

```bash
composer require wppack/security
```

## 基本コンセプト

### アーキテクチャ概要

| 概念 | 説明 |
|---|---|
| Authenticator | リクエストベースの認証（`supports()` → `authenticate()` → `createToken()`） |
| Passport + Badge | 認証要件の値オブジェクト（UserBadge, CredentialsBadge 等） |
| Token | `PostAuthenticationToken`（`\WP_User` をラップ） |
| UserProvider | `WordPressUserProvider`（`get_user_by()` ラッパー） |
| Voter | `CapabilityVoter`（`user_can()` ラッパー）, `RoleVoter` |
| EventDispatcher | `CheckPassportEvent`, `AuthenticationSuccessEvent` 等 |

### Before（従来の WordPress）

```php
// 手動の認証・認可チェック
add_filter('authenticate', function ($user, $username, $password) {
    // カスタム認証ロジック...
    return $user;
}, 10, 3);

if (!current_user_can('edit_posts')) {
    wp_die('Unauthorized');
}
```

### After（WPPack Security）

```php
use WPPack\Component\Security\Security;

// 認可チェック
$security->denyAccessUnlessGranted('edit_posts');

// ユーザー取得
$user = $security->getUser();

// ロールチェック
if ($security->isGranted('ROLE_ADMINISTRATOR')) {
    // 管理者のみの処理
}
```

## 認証（Authentication）

### 認証フロー

```
[リクエスト着信]
    ↓
authenticate フィルター (priority 10)
    ↓
AuthenticationManager::handleAuthentication()
    ↓ 各 Authenticator を順に試行
authenticator->supports($request) ?
    ↓ yes
authenticator->authenticate($request) → Passport
    ↓
dispatch(CheckPassportEvent) ← Badge 検証（パスワード照合等）
    ↓
passport->ensureAllBadgesResolved()
    ↓
authenticator->createToken($passport) → TokenInterface
    ↓
dispatch(AuthenticationSuccessEvent)
    ↓
authenticator->onAuthenticationSuccess($request, $token) → ?Response
    ↓
Response を返した場合:
    AuthenticationManager が Cookie 設定 + レスポンス送信
null を返した場合:
    WordPress のデフォルトフローに委譲
    ↓
return WP_User ← WordPress に渡す
```

### AuthenticatorInterface

認証方式ごとに Authenticator を実装します:

```php
use WPPack\Component\Security\Authentication\AuthenticatorInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;

interface AuthenticatorInterface
{
    public function supports(Request $request): bool;
    public function authenticate(Request $request): Passport;
    public function createToken(Passport $passport): TokenInterface;
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response;
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response;
}
```

### Passport と Badge

Passport は認証に必要な情報（Badge）を集約する値オブジェクトです:

```php
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;

// フォームログイン認証の例
$passport = new Passport(
    new UserBadge($username),
    new CredentialsBadge($password),
    [new RememberMeBadge(true)],
);
```

外部認証（OAuth / SAML）では `SelfValidatingPassport` を使用:

```php
use WPPack\Component\Security\Authentication\Passport\SelfValidatingPassport;

// OAuth で検証済みのユーザー
$passport = new SelfValidatingPassport(
    new UserBadge($oauthUserId, fn(string $id) => $this->findUserByOAuthId($id)),
);
```

### 組み込み Authenticator

| Authenticator | 説明 | フィルター |
|---|---|---|
| `FormLoginAuthenticator` | `wp-login.php` フォーム認証 | `authenticate` |
| `CookieAuthenticator` | Cookie ベースセッション認証 | `determine_current_user` |
| `ApplicationPasswordAuthenticator` | REST API Application Password | `determine_current_user` |

### レスポンス制御と WordPress デフォルトフロー

`onAuthenticationSuccess` / `onAuthenticationFailure` の戻り値で、認証後の動作が分岐します:

| 戻り値 | 動作 | 対象 |
|--------|------|------|
| `Response` | `AuthenticationManager` が Cookie 設定（`wp_set_auth_cookie`）とレスポンス送信を実行 | OAuth, SAML |
| `null` | WordPress のデフォルトフローに委譲（`wp_signon()` が Cookie・リダイレクトを処理） | FormLogin, Cookie, ApplicationPassword |

WordPress 標準の `wp-login.php` を使う場合（`FormLoginAuthenticator`）は、認証の検証のみ Security コンポーネントが担当し、Cookie 設定やリダイレクトは WordPress 側で行われます。OAuth / SAML のように `wp_signon()` をバイパスする認証方式では、Authenticator が `RedirectResponse` を返し、`AuthenticationManager` が Cookie 設定（`wp_set_auth_cookie()`）を一元管理します。各 Authenticator は Cookie を直接操作せず、セッション確立は `AuthenticationManager` に委譲します。

```php
// WordPress デフォルトフローに委譲する場合（FormLogin 等）
public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
{
    return null; // WordPress が Cookie 設定・リダイレクトを処理
}

// 独自にレスポンスを制御する場合（OAuth / SAML 等）
public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
{
    // Multisite 処理等の Authenticator 固有ロジック
    return new RedirectResponse($redirectUrl);
    // AuthenticationManager が Cookie 設定後にレスポンスを送信
}
```

### SSO 専用構成（OAuth / SAML のみ）

WordPress のデフォルトフォーム認証を無効化し、OAuth / SAML のみで認証する構成も可能です。

**ポイント:**
- `FormLoginAuthenticator` を登録しない
- `EntryPoint::register()` で `wp-login.php` を IdP へリダイレクト
- `CookieAuthenticator` はセッション維持に必要なため**必ず残す**（OAuth/SAML の初回認証後、以降のリクエストは Cookie ベースで認証されるため）

```php
// 1. AuthenticationManager に OAuth/SAML + Cookie のみ登録
$manager->addAuthenticator($oauthAuthenticator);  // or $samlAuthenticator
$manager->addAuthenticator($cookieAuthenticator);
// FormLoginAuthenticator は登録しない

// 2. EntryPoint を登録（login_url フィルター + login_init リダイレクト）
$entryPoint->register();
```

`register()` は内部で以下の WordPress フックを登録します:

- **`login_url` フィルター**: `wp-login.php` の URL を IdP ログイン URL に差し替え
- **`login_init` アクション**: `wp-login.php` への直接 GET アクセスを IdP にリダイレクト

`login_init` では `$_GET['action']` がある場合（`logout`, `lostpassword` 等）はリダイレクトしません。これにより WordPress 標準のログアウト・パスワードリセットフローを維持します。

EntryPoint の詳細は各 Bridge ドキュメントを参照してください:
- OAuth: [OAuthEntryPoint](./oauth-security.md)
- SAML: [SamlEntryPoint](./saml-security.md)

### カスタム Authenticator

```php
use WPPack\Component\Security\Attribute\AsAuthenticator;
use WPPack\Component\Security\Authentication\AuthenticatorInterface;

#[AsAuthenticator]
final class CustomLoginAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === '/my-login';
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->post->getString('email');
        $password = $request->post->getString('password');

        return new Passport(
            new UserBadge($email),
            new CredentialsBadge($password),
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();
        return new PostAuthenticationToken($user, $user->roles);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        // リダイレクト等（null を返すと WordPress のデフォルトフローに委譲）
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // エラーハンドリング（null を返すと WordPress のデフォルトフローに委譲）
        return null;
    }
}
```

### ステートレス認証（API）

`StatelessAuthenticatorInterface` を実装すると `determine_current_user` フィルター経由で毎リクエスト検証されます:

```php
use WPPack\Component\Security\Authentication\StatelessAuthenticatorInterface;

final class JwtAuthenticator implements StatelessAuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        return str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization'), 7);
        $payload = $this->jwtDecoder->decode($token);

        return new SelfValidatingPassport(
            new UserBadge($payload->sub, fn(string $id) => get_user_by('id', (int) $id)),
        );
    }
    // ...
}
```

### イベント

認証プロセスの各段階でイベントが発行されます:

| イベント | タイミング |
|---|---|
| `CheckPassportEvent` | Passport 作成後、Badge 検証前 |
| `AuthenticationSuccessEvent` | 認証成功時 |
| `AuthenticationFailureEvent` | 認証失敗時 |
| `LoginSuccessEvent` | `wp_login` フック後 |
| `LogoutEvent` | `wp_logout` フック後 |

```php
use WPPack\Component\Security\Event\CheckPassportEvent;

// 2FA 検証リスナーの例
final class TwoFactorListener
{
    public function __invoke(CheckPassportEvent $event): void
    {
        $user = $event->getPassport()->getUser();
        if ($this->has2faEnabled($user)) {
            // 2FA バッジの検証
        }
    }
}
```

## 認可（Authorization）

### Voter パターン

認可チェックは Voter チェーンで判定されます:

```php
use WPPack\Component\Security\Authorization\Voter\VoterInterface;

interface VoterInterface
{
    public const ACCESS_GRANTED = 1;
    public const ACCESS_DENIED = -1;
    public const ACCESS_ABSTAIN = 0;

    public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int;
}
```

### 組み込み Voter

| Voter | 説明 | 属性形式 |
|---|---|---|
| `CapabilityVoter` | `user_can()` ラッパー | `'edit_posts'`, `'manage_options'` |
| `RoleVoter` | ロールベースチェック | `'ROLE_ADMINISTRATOR'`, `'ROLE_EDITOR'` |

### AccessDecisionManager

デフォルトの **affirmative** ストラテジー:

- いずれかの Voter が `ACCESS_DENIED` → 即時拒否
- いずれかの Voter が `ACCESS_GRANTED` → 許可
- 全 Voter が `ACCESS_ABSTAIN` → 拒否（`allowIfAllAbstain` で変更可能）

### カスタム Voter

```php
use WPPack\Component\Security\Attribute\AsVoter;
use WPPack\Component\Security\Authorization\Voter\VoterInterface;

#[AsVoter]
final class PostOwnerVoter implements VoterInterface
{
    public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
    {
        if ($attribute !== 'EDIT_OWN_POST' || !$subject instanceof \WP_Post) {
            return self::ACCESS_ABSTAIN;
        }

        if (!$token->isAuthenticated()) {
            return self::ACCESS_DENIED;
        }

        return $subject->post_author === $token->getUser()->ID
            ? self::ACCESS_GRANTED
            : self::ACCESS_DENIED;
    }
}
```

## Security ファサード

`Security` クラスは認証・認可の統合的なインターフェースを提供します:

```php
use WPPack\Component\Security\Security;

final class Security
{
    // 権限チェック
    public function isGranted(string $attribute, mixed $subject = null): bool;

    // 現在の認証済みユーザー取得（未認証なら null）
    public function getUser(): ?\WP_User;

    // 権限がなければ AccessDeniedException をスロー
    public function denyAccessUnlessGranted(
        string $attribute,
        mixed $subject = null,
        string $message = 'Access Denied.',
    ): void;
}
```

### 使用例

```php
class PostController
{
    public function __construct(private Security $security) {}

    public function editPost(\WP_Post $post): void
    {
        // Capability チェック
        $this->security->denyAccessUnlessGranted('edit_post', $post->ID);

        // ロールチェック
        if ($this->security->isGranted('ROLE_ADMINISTRATOR')) {
            // 管理者のみの処理
        }

        // 現在のユーザー取得
        $user = $this->security->getUser();
    }
}
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/security.md) を参照してください。

## DI 統合

```php
use WPPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WPPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WPPack\Component\Security\DependencyInjection\RegisterVotersPass;

// サービスプロバイダー登録
$builder->registerServiceProvider(new SecurityServiceProvider());

// コンパイラパス登録（#[AsAuthenticator] / #[AsVoter] 自動検出）
$builder->addCompilerPass(new RegisterAuthenticatorsPass());
$builder->addCompilerPass(new RegisterVotersPass());
```

## マルチサイト対応

Security コンポーネントは WordPress マルチサイト環境をサポートしています。

### ブログコンテキスト

認証トークンは認証が行われたブログ ID を保持します:

```php
$token = $security->getToken();
$blogId = $token->getBlogId(); // int（認証時のブログ ID）or null（シングルサイト）
```

組み込み Authenticator（`FormLoginAuthenticator`、`CookieAuthenticator`、`ApplicationPasswordAuthenticator`）は `get_current_blog_id()` を使用してトークンにブログ ID を自動設定します。カスタム Authenticator でも同様に設定できます:

```php
public function createToken(Passport $passport): TokenInterface
{
    $user = $passport->getUser();
    $blogId = function_exists('get_current_blog_id') ? get_current_blog_id() : null;

    return new PostAuthenticationToken($user, $user->roles, $blogId);
}
```

### Super Admin チェック

`RoleVoter` は `ROLE_SUPER_ADMIN` 属性をサポートし、WordPress の `is_super_admin()` に委譲します:

```php
// Super Admin かどうかチェック
if ($security->isGranted('ROLE_SUPER_ADMIN')) {
    // ネットワーク管理者のみの処理
}
```

- **マルチサイト**: `is_super_admin()` がネットワークの Super Admin リストを参照
- **シングルサイト**: `is_super_admin()` が `delete_users` ケイパビリティを持つかで判定（管理者は true）

### CapabilityVoter のマルチサイト動作

`CapabilityVoter` は WordPress の `user_can()` に委譲しており、マルチサイト環境でもそのまま動作します。`user_can()` は現在のブログコンテキストに基づいてケイパビリティを評価します。

### クロスブログ権限チェック

別のブログでの権限をチェックするには、Database コンポーネントの `switch_to_blog()` と組み合わせます:

```php
use WPPack\Component\Database\DatabaseManager;

// ブログ 2 での権限チェック
$db->switchToBlog(2, function () use ($security) {
    if ($security->isGranted('edit_posts')) {
        // ブログ 2 で投稿編集可能
    }
});
```

### Named Hook 連携

Role コンポーネントの `GrantSuperAdminAction` / `RevokeSuperAdminAction` と連携して Super Admin の変更を監視できます:

```php
use WPPack\Component\Hook\Attribute\Role\Action\GrantSuperAdminAction;
use WPPack\Component\Hook\Attribute\Role\Action\RevokeSuperAdminAction;

class SuperAdminMonitor
{
    #[GrantSuperAdminAction]
    public function onGrantSuperAdmin(int $userId): void
    {
        // Super Admin 付与時の処理（監査ログ等）
    }

    #[RevokeSuperAdminAction]
    public function onRevokeSuperAdmin(int $userId): void
    {
        // Super Admin 剥奪時の処理
    }
}
```

## Bridge パッケージ拡張ポイント

OAuth / SAML / 2FA は `AuthenticatorInterface` を実装する Bridge パッケージとして追加:

| Bridge | パッケージ名 | ドキュメント |
|--------|-------------|-------------|
| OAuth 2.0 / OpenID Connect | `wppack/oauth-security` | [oauth-security.md](./oauth-security.md) |
| SAML 2.0 SP | `wppack/saml-security` | [saml-security.md](./saml-security.md) |

```php
// wppack/oauth-security
final class OAuthAuthenticator implements AuthenticatorInterface { /* ... */ }

// wppack/saml-security
final class SamlAuthenticator implements AuthenticatorInterface { /* ... */ }

// wppack/two-factor-security
// CheckPassportEvent リスナーで 2FA バッジを検証
final class TwoFactorListener { /* ... */ }
```

## セキュリティ対策

| 脅威 | 対策 | 実装箇所 |
|------|------|---------|
| タイミング攻撃 | `wp_check_password()` 内部の `hash_equals()` | `CheckCredentialsListener` |
| ブルートフォース | `AuthenticationFailureEvent` で試行追跡可能 | `AuthenticationManager` |
| ユーザー列挙 | 一律エラーメッセージ（`getSafeMessage()`） | `AuthenticationException` |
| 権限昇格 | DENY 優先の Voter チェーン | `AccessDecisionManager` |
| 未検証 Badge | `ensureAllBadgesResolved()` で全 Badge 検証 | `AuthenticationManager` |
| Cookie 改ざん | `wp_validate_auth_cookie()` に委譲 | `CookieAuthenticator` |
| アカウント乗っ取り（SSO） | NameID / Subject ID バインディングで既存ユーザーのメール一致時にも IdP 側 ID を検証 | `OAuthUserResolver`, `SamlUserResolver`（[詳細](./oauth-security.md#サブジェクト-id-バインディング)） |

## 依存関係

### 必須
- **wppack/role** — `#[IsGranted]` アトリビュート、`IsGrantedChecker`、`AccessDeniedException`
- **wppack/http-foundation** — Request オブジェクト
- **wppack/event-dispatcher** — イベントディスパッチ

### 推奨
- **wppack/hook** — Named Hook アトリビュート（WpLoginAction, AuthenticateFilter 等）
- **wppack/dependency-injection** — DI コンテナ統合
- **Nonce Component** — CSRF 保護

> [!NOTE]
> `#[IsGranted]` アトリビュートと `IsGrantedChecker` は Role コンポーネント（`WPPack\Component\Role\Attribute\IsGranted`、`WPPack\Component\Role\Authorization\IsGrantedChecker`）で提供されています。Security コンポーネントの `AuthorizationChecker` は Role の `AuthorizationCheckerInterface` を実装しており、Voter ベースの認可チェックを `IsGrantedChecker` に注入できます。
