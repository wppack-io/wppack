# Security Component

**パッケージ:** `wppack/security`
**名前空間:** `WpPack\Component\Security\`
**レイヤー:** Abstraction

WordPress のセキュリティ関数（`current_user_can()`、Nonce、サニタイズ）をモダンな型安全 API とアトリビュートで拡張するコンポーネントです。認可チェック、CSRF 保護をアトリビュートベースで宣言的に適用できます。

## インストール

```bash
composer require wppack/security
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - 手動のセキュリティチェック
if (!current_user_can('edit_posts')) {
    wp_die('Unauthorized');
}

if (!wp_verify_nonce($_POST['nonce'], 'edit_post_' . $post_id)) {
    wp_die('Security check failed');
}

$title = sanitize_text_field($_POST['title']);
```

### After（WpPack）

```php
use WpPack\Component\Security\Attribute\RequiresCapability;
use WpPack\Component\Security\Attribute\RequiresNonce;

class PostController
{
    #[RequiresCapability('edit_posts')]
    #[RequiresNonce('edit_post')]
    public function updatePost(int $postId, array $data): void
    {
        // セキュリティチェックはアトリビュートで自動処理（Nonce Component 連携）
        $this->updatePostData($postId, $data);
    }
}
```

## 主要機能

### Fluent Security API

`current_user_can()` と Nonce 関連の WordPress 関数を Fluent インターフェースでラップします。

```php
use WpPack\Component\Security\Security;

$security->can('edit_posts')->orFail('Insufficient permissions');
$security->nonce('edit_post')->verify($_POST['nonce'])->orFail();
$security->sanitize($_POST['data'])->asText()->stripTags();
```

### アトリビュートによる自動保護

メソッドにアトリビュートを付与するだけで、認可・Nonce 検証を自動適用できます。

```php
use WpPack\Component\Security\Attribute\RequiresCapability;
use WpPack\Component\Security\Attribute\RequiresNonce;

#[RequiresCapability('manage_options')]
#[RequiresNonce('settings_update')]
public function updateSettings(array $settings): void
{
    // メソッドは自動的に保護される（Nonce Component 連携）
}
```

## クイックスタート

### セキュアな管理画面コントローラ

```php
<?php
use WpPack\Component\Security\Security;
use WpPack\Component\Security\Attribute\RequiresCapability;
use WpPack\Component\Security\Attribute\RequiresNonce;

class SecureAdminController
{
    public function __construct(private Security $security) {}

    public function showSettingsPage(): void
    {
        $this->security->can('manage_options')->orRedirect(admin_url());

        $nonce = $this->security->nonce('update_settings')->create();
        $this->renderSettingsForm($nonce);
    }

    #[RequiresCapability('manage_options')]
    #[RequiresNonce('update_settings')]
    public function updateSettings(): void
    {
        $settings = $this->security->sanitize($_POST['settings'])
            ->asArray()
            ->stripTags()
            ->trimWhitespace()
            ->get();

        $apiKey = $this->security->sanitize($_POST['api_key'])
            ->asText()
            ->maxLength(64)
            ->get();

        $this->saveSettings([
            'api_key' => $apiKey,
            'settings' => $settings,
        ]);

        wp_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=settings')));
        exit;
    }

    #[RequiresCapability('edit_posts')]
    #[RequiresNonce('ajax_action')]
    public function handleAjaxRequest(): void
    {
        $action = $this->security->sanitize($_POST['action'])->asText()->get();
        $data = $this->security->sanitize($_POST['data'])->asArray()->get();

        wp_send_json_success(['message' => 'Action completed successfully']);
    }
}
```

### お問い合わせフォームのセキュリティ

```php
<?php
class ContactFormHandler
{
    public function __construct(private Security $security) {}

    #[RequiresNonce('contact_form')]
    public function handleSubmission(): void
    {
        $name = $this->security->sanitize($_POST['name'])
            ->asText()
            ->stripTags()
            ->maxLength(100)
            ->get();

        $email = $this->security->sanitize($_POST['email'])
            ->asEmail()
            ->get();

        $message = $this->security->sanitize($_POST['message'])
            ->asText()
            ->stripTags()
            ->maxLength(1000)
            ->get();

        if (empty($name) || empty($email) || empty($message)) {
            wp_die('All fields are required.');
        }

        $this->sendContactEmail($name, $email, $message);

        wp_redirect(add_query_arg('sent', '1', $_SERVER['REQUEST_URI']));
        exit;
    }
}
```

## Named Hook Attributes

### 認証フック

```php
#[WpLoginAction(priority: 10)]              // ログイン成功後
#[WpLoginFailedAction(priority: 10)]        // ログイン失敗後
#[AuthenticateFilter(priority: 10)]         // 認証プロセスの変更
#[WpLogoutAction(priority: 10)]             // ログアウト前
#[DetermineCurrentUserFilter(priority: 10)] // 現在のユーザーをフィルタ
```

### パスワードフック

```php
#[PasswordResetAction(priority: 10)]        // パスワードリセット後
#[RetrievePasswordAction(priority: 10)]     // パスワードリセット要求時
#[CheckPasswordFilter(priority: 10)]        // パスワード強度の検証
```

### 権限フック

```php
#[UserHasCapFilter(priority: 10)]           // ユーザー権限のフィルタ
#[MapMetaCapFilter(priority: 10)]           // メタ権限のマッピング
```

### 使用例：認証セキュリティシステム

```php
use WpPack\Component\Security\Attribute\AuthenticateFilter;
use WpPack\Component\Security\Attribute\WpLoginAction;
use WpPack\Component\Security\Attribute\WpLoginFailedAction;
use WpPack\Component\Security\Attribute\UserHasCapFilter;

class SecuritySystem
{
    #[AuthenticateFilter(priority: 10)]
    public function enforceRateLimiting($user, string $username, string $password)
    {
        $ip = $this->getClientIp();

        if ($this->rateLimiter->isBlocked($ip)) {
            return new WP_Error('rate_limit', __('Too many login attempts.', 'wppack'));
        }

        return $user;
    }

    #[WpLoginAction(priority: 10)]
    public function onSuccessfulLogin(string $userLogin, WP_User $user): void
    {
        $this->rateLimiter->clear($this->getClientIp());

        $this->updateSecurityProfile($user->ID, [
            'last_login' => current_time('mysql'),
            'last_ip' => $this->getClientIp(),
        ]);
    }

    #[WpLoginFailedAction(priority: 10)]
    public function onFailedLogin(string $username, WP_Error $error): void
    {
        $ip = $this->getClientIp();
        $this->rateLimiter->recordFailure($ip);
    }

    #[UserHasCapFilter(priority: 10)]
    public function enforceCapabilitySecurity(
        array $allcaps,
        array $caps,
        array $args,
        WP_User $user,
    ): array {
        $sensitiveCaps = ['delete_plugins', 'delete_themes', 'edit_users', 'delete_users'];

        if (array_intersect($caps, $sensitiveCaps)) {
            if (!$this->hasRecentVerification($user->ID)) {
                return [];
            }
        }

        return $allcaps;
    }
}
```

## 利用シーン

**最適なケース:**
- 管理画面でのセンシティブな操作の保護
- フォーム送信時の CSRF 保護
- 権限ベースのアクセス制御が必要なアプリケーション

**代替を検討すべきケース:**
- 基本的なセキュリティで十分なシンプルなブログ
- カスタムセキュリティフレームワークを既に使用している場合

## 依存関係

### 必須
- なし — WordPress のセキュリティ API をそのまま利用

### 推奨
- **Nonce Component** — WordPress Nonce との統合
- **Validator Component** — 入力バリデーション
- **Sanitizer Component** — 入力サニタイズの強化
