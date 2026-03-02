# Nonce コンポーネント

**パッケージ:** `wppack/nonce`
**名前空間:** `WpPack\Component\Nonce\`
**レイヤー:** Feature

WordPress の Nonce API（`wp_create_nonce()` / `wp_verify_nonce()` 等）をオブジェクト指向でラップするコンポーネントです。型安全な Nonce 操作と Attribute ベースの CSRF 保護を提供します。

## インストール

```bash
composer require wppack/nonce
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// nonce の作成と検証が文字列ベースで型安全でない
$nonce = wp_create_nonce('delete-post_' . $post_id);

if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'delete-post_' . $post_id)) {
    die('Security check failed');
}

wp_nonce_field('save-settings', 'settings_nonce');

$url = wp_nonce_url(admin_url('admin-post.php?action=delete&id=' . $id), 'delete-item_' . $id);
```

### After（WpPack）

```php
use WpPack\Component\Nonce\NonceManager;
use WpPack\Component\Nonce\Attribute\NonceProtected;

class PostController
{
    public function __construct(
        private readonly NonceManager $nonces,
    ) {}

    // Attribute で自動検証
    #[NonceProtected('delete-post')]
    public function deletePost(int $postId): void
    {
        wp_delete_post($postId);
    }

    public function renderDeleteButton(int $postId): string
    {
        // wp_nonce_url() のラッパー
        $url = $this->nonces->url(
            admin_url('admin-post.php?action=delete&id=' . $postId),
            'delete-post',
        );

        return sprintf('<a href="%s">Delete</a>', esc_url($url));
    }
}
```

## NonceManager

WordPress の Nonce 関数を型安全にラップするサービスクラスです。

```php
use WpPack\Component\Nonce\NonceManager;

class MyService
{
    public function __construct(
        private readonly NonceManager $nonces,
    ) {}

    public function example(): void
    {
        // wp_create_nonce() のラッパー
        $nonce = $this->nonces->create('my-action');

        // wp_verify_nonce() のラッパー
        $valid = $this->nonces->verify($nonce, 'my-action');

        // wp_nonce_field() のラッパー（HTML 文字列を返す）
        $field = $this->nonces->field('my-action');
        $field = $this->nonces->field('my-action', 'my_nonce_name');

        // wp_nonce_url() のラッパー
        $url = $this->nonces->url('https://example.com/action', 'my-action');

        // $_REQUEST から nonce を取得して検証
        $valid = $this->nonces->verifyRequest('my-action');
        $valid = $this->nonces->verifyRequest('my-action', 'custom_nonce_field');
    }
}
```

### NonceManager メソッド一覧

| メソッド | WordPress API | 説明 |
|---------|--------------|------|
| `create(string $action): string` | `wp_create_nonce()` | nonce トークンを生成 |
| `verify(string $nonce, string $action): bool` | `wp_verify_nonce()` | nonce を検証 |
| `field(string $action, string $name = '_wpnonce'): string` | `wp_nonce_field()` | hidden input を生成 |
| `url(string $url, string $action): string` | `wp_nonce_url()` | nonce 付き URL を生成 |
| `verifyRequest(string $action, string $name = '_wpnonce'): bool` | `wp_verify_nonce()` | `$_REQUEST` から nonce を取得して検証 |
| `checkAdminReferer(string $action, string $name = '_wpnonce'): void` | `check_admin_referer()` | 管理画面リファラーチェック |

## Attribute

### `#[NonceProtected]`

メソッド実行前に nonce を自動検証する Attribute です。検証失敗時は `wp_die()` を呼びます。

```php
use WpPack\Component\Nonce\Attribute\NonceProtected;

class SettingsController
{
    #[NonceProtected('update-settings')]
    public function updateSettings(array $settings): void
    {
        // nonce が有効な場合のみ実行される
        update_option('my_settings', $settings);
    }

    #[NonceProtected('delete-item', name: 'delete_nonce')]
    public function deleteItem(int $id): void
    {
        // カスタムフィールド名で nonce を検証
        wp_delete_post($id, true);
    }
}
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|-----|----------|------|
| `action` | `string` | _(必須)_ | nonce アクション名 |
| `name` | `string` | `'_wpnonce'` | リクエストパラメータ名 |

### `#[RequiresNonce]`

HTTP メソッドの制約も加えた nonce 検証 Attribute です。

```php
use WpPack\Component\Nonce\Attribute\RequiresNonce;

class AdminController
{
    #[RequiresNonce('delete-user', method: 'POST')]
    public function deleteUser(int $userId): void
    {
        // POST リクエストかつ有効な nonce の場合のみ実行
        wp_delete_user($userId);
    }
}
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|-----|----------|------|
| `action` | `string` | _(必須)_ | nonce アクション名 |
| `method` | `string\|null` | `null` | 許可する HTTP メソッド（`'GET'`, `'POST'` 等） |
| `name` | `string` | `'_wpnonce'` | リクエストパラメータ名 |

## クイックスタート

### セキュアフォーム

```php
use WpPack\Component\Nonce\NonceManager;

class ContactForm
{
    public function __construct(
        private readonly NonceManager $nonces,
    ) {}

    public function render(): void
    {
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="submit_contact">
            <?php echo $this->nonces->field('contact-form-submit'); ?>

            <input type="text" name="name" required>
            <input type="email" name="email" required>
            <textarea name="message" required></textarea>

            <button type="submit">Send Message</button>
        </form>
        <?php
    }
}
```

### フォーム送信ハンドラー

```php
use WpPack\Component\Nonce\Attribute\NonceProtected;
use WpPack\Component\Hook\Attribute\Action;

class ContactFormHandler
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {}

    #[Action('admin_post_submit_contact')]
    #[Action('admin_post_nopriv_submit_contact')]
    #[NonceProtected('contact-form-submit')]
    public function handleSubmit(): void
    {
        // nonce は Attribute で自動検証済み
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $message = sanitize_textarea_field($_POST['message']);

        $this->mailer->send([
            'to' => get_option('admin_email'),
            'subject' => 'Contact Form',
            'message' => sprintf("Name: %s\nEmail: %s\nMessage: %s", $name, $email, $message),
        ]);

        wp_safe_redirect(add_query_arg('success', '1', wp_get_referer()));
        exit;
    }
}
```

### 管理アクション URL

```php
class PostActions
{
    public function __construct(
        private readonly NonceManager $nonces,
    ) {}

    public function getDeleteLink(int $postId): string
    {
        $url = admin_url('admin-post.php?action=delete_post&post_id=' . $postId);
        return $this->nonces->url($url, 'delete-post_' . $postId);
    }

    #[Action('admin_post_delete_post')]
    public function handleDelete(): void
    {
        $postId = (int) ($_GET['post_id'] ?? 0);

        // check_admin_referer() のラッパー
        $this->nonces->checkAdminReferer('delete-post_' . $postId);

        if (!current_user_can('delete_post', $postId)) {
            wp_die('Permission denied');
        }

        wp_delete_post($postId, true);
        wp_safe_redirect(admin_url('edit.php?deleted=1'));
        exit;
    }
}
```

## Named Hook Attributes

### Nonce 検証フック

```php
#[CheckAdminRefererAction(priority: 10)]      // check_admin_referer 実行時
```

### Nonce 生成フック

```php
#[NonceUserLoggedOutFilter(priority: 10)]     // ログアウトユーザーの nonce UID
#[NonceLifeFilter(priority: 10)]              // nonce の有効期間（デフォルト: 1日）
```

### 使用例：nonce の有効期間をカスタマイズ

```php
use WpPack\Component\Nonce\Attribute\NonceLifeFilter;

class NonceLifetimeCustomizer
{
    #[NonceLifeFilter(priority: 10)]
    public function customizeLifetime(int $seconds): int
    {
        // デフォルトの1日（86400秒）を4時間に短縮
        return HOUR_IN_SECONDS * 4;
    }
}
```

## クイックリファレンス

### NonceManager

```php
$nonces->create('action');                     // nonce を作成
$nonces->verify($nonce, 'action');             // nonce を検証
$nonces->field('action');                      // hidden input を出力
$nonces->field('action', 'custom_name');       // カスタム名で hidden input
$nonces->url($url, 'action');                  // nonce 付き URL
$nonces->verifyRequest('action');              // $_REQUEST から検証
$nonces->checkAdminReferer('action');          // 管理画面リファラーチェック
```

### Attribute

```php
#[NonceProtected('action')]                    // 自動 nonce 検証
#[NonceProtected('action', name: 'my_nonce')]  // カスタムフィールド名
#[RequiresNonce('action', method: 'POST')]     // HTTP メソッド制約付き
```

## 利用シーン

**最適なケース:**
- フォーム送信の CSRF 保護
- 管理アクション URL の生成
- 一括操作のセキュリティ

**代替を検討すべきケース:**
- REST API エンドポイント（REST コンポーネントの認証機能を使用）
- 長期的な認証（セッション / Cookie を使用）

## 依存関係

### 必須
なし — WordPress の nonce 関数をそのまま利用

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
