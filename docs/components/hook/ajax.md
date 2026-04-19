# Ajax Named Hook アトリビュート

WordPress の Ajax リクエスト処理に関連するフック（ログインユーザー向け / 非ログインユーザー向けのハンドラー登録、リファラーチェックなど）の Named Hook アトリビュートです。

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Ajax/Subscriber/`

## アクション

| アトリビュート | WordPress フック | 説明 |
|---|---|---|
| `#[WpAjaxAction(action: 'my_action')]` | `wp_ajax_{action}` | ログインユーザー向け Ajax ハンドラー |
| `#[WpAjaxNoprivAction(action: 'my_action')]` | `wp_ajax_nopriv_{action}` | 非ログインユーザー向け Ajax ハンドラー |
| `#[CheckAjaxRefererAction]` | `check_ajax_referer` | Ajax リファラーチェック時の処理 |

> `WpAjaxAction` と `WpAjaxNoprivAction` は `action` パラメータ（必須）で Ajax アクション名を指定します。指定したアクション名が WordPress フック名の `wp_ajax_{action}` / `wp_ajax_nopriv_{action}` に展開されます。

## フィルター

Admin Ajax に関連するフィルターアトリビュートはありません。

## コード例

### ログインユーザー向け Ajax ハンドラー

```php
<?php

declare(strict_types=1);

namespace App\Ajax\Subscriber;

use WPPack\Component\Hook\Attribute\Ajax\Action\WpAjaxAction;

final class UserPreferenceAjaxSubscriber
{
    #[WpAjaxAction(action: 'save_user_preference')]
    public function savePreference(): void
    {
        check_ajax_referer('user_preference_nonce', 'nonce');

        $key = sanitize_key($_POST['key'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        if ($key === '' || $value === '') {
            wp_send_json_error(['message' => __('Invalid parameters.', 'my-plugin')], 400);
        }

        update_user_meta(get_current_user_id(), "preference_{$key}", $value);

        wp_send_json_success(['message' => __('Preference saved.', 'my-plugin')]);
    }
}
```

### ログイン / 非ログイン両方に対応するハンドラー

```php
<?php

declare(strict_types=1);

namespace App\Ajax\Subscriber;

use WPPack\Component\Hook\Attribute\Ajax\Action\WpAjaxAction;
use WPPack\Component\Hook\Attribute\Ajax\Action\WpAjaxNoprivAction;

final class SearchAjaxSubscriber
{
    #[WpAjaxAction(action: 'live_search')]
    #[WpAjaxNoprivAction(action: 'live_search')]
    public function handleLiveSearch(): void
    {
        check_ajax_referer('live_search_nonce', 'nonce');

        $query = sanitize_text_field($_GET['q'] ?? '');
        if (mb_strlen($query) < 2) {
            wp_send_json_error(['message' => __('Query too short.', 'my-plugin')], 400);
        }

        $results = new \WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 10,
        ]);

        $items = array_map(
            fn(\WP_Post $post) => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post),
                'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20),
            ],
            $results->posts
        );

        wp_send_json_success(['items' => $items, 'total' => $results->found_posts]);
    }
}
```

### Ajax リファラーチェックのカスタマイズ

```php
<?php

declare(strict_types=1);

namespace App\Ajax\Subscriber;

use Psr\Log\LoggerInterface;
use WPPack\Component\Hook\Attribute\Ajax\Action\CheckAjaxRefererAction;

final class AjaxSecuritySubscriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[CheckAjaxRefererAction]
    public function onCheckAjaxReferer(string $action, bool $result): void
    {
        if (!$result) {
            // リファラーチェック失敗をログに記録
            $this->logger->warning('Ajax referer check failed', [
                'action' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        }
    }
}
```
