# Admin Named Hook アトリビュート

WordPress 管理画面に関連するフック（メニュー登録、通知表示、スクリプト読み込み、カラムカスタマイズなど）の Named Hook アトリビュートです。

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Admin/Subscriber/`

## アクション

| アトリビュート | WordPress フック | 説明 |
|---|---|---|
| `#[AdminMenuAction]` | `admin_menu` | 管理メニューの登録 |
| `#[NetworkAdminMenuAction]` | `network_admin_menu` | マルチサイトネットワーク管理メニューの登録 |
| `#[UserAdminMenuAction]` | `user_admin_menu` | ユーザー管理メニューの登録 |
| `#[AdminEnqueueScriptsAction]` | `admin_enqueue_scripts` | 管理画面のスクリプト・スタイル読み込み |
| `#[AdminHeadAction]` | `admin_head` | 管理画面の `<head>` 内に出力 |
| `#[AdminFooterAction]` | `admin_footer` | 管理画面のフッターに出力 |
| `#[AdminNoticesAction]` | `admin_notices` | 管理画面の通知表示 |
| `#[AllAdminNoticesAction]` | `all_admin_notices` | すべての管理画面通知の表示 |
| `#[NetworkAdminNoticesAction]` | `network_admin_notices` | ネットワーク管理画面の通知表示 |
| `#[UserAdminNoticesAction]` | `user_admin_notices` | ユーザー管理画面の通知表示 |
| `#[AdminPrintScriptsAction]` | `admin_print_scripts` | 管理画面のスクリプト出力 |
| `#[AdminPrintStylesAction]` | `admin_print_styles` | 管理画面のスタイル出力 |
| `#[AdminPrintFooterScriptsAction]` | `admin_print_footer_scripts` | 管理画面フッターのスクリプト出力 |
| `#[AdminBarMenuAction]` | `admin_bar_menu` | 管理バーへのメニュー項目追加 |
| `#[WpBeforeAdminBarRenderAction]` | `wp_before_admin_bar_render` | 管理バーのレンダリング前処理 |
| `#[CurrentScreenAction]` | `current_screen` | 現在のスクリーンオブジェクト設定後の処理 |
| `#[CheckAdminRefererAction]` | `check_admin_referer` | 管理リファラーチェック時の処理 |
| `#[ManagePostsCustomColumnAction]` | `manage_posts_custom_column` | 投稿一覧のカスタムカラム値の出力 |

## フィルター

| アトリビュート | WordPress フック | 説明 |
|---|---|---|
| `#[AdminTitleFilter]` | `admin_title` | 管理画面のページタイトルを変更 |
| `#[AdminBodyClassFilter]` | `admin_body_class` | 管理画面の `<body>` クラスを変更 |
| `#[AdminFooterTextFilter]` | `admin_footer_text` | 管理画面フッターのテキストを変更 |
| `#[ManagePostsColumnsFilter]` | `manage_posts_columns` | 投稿一覧のカラムを変更 |
| `#[ManagePagesColumnsFilter]` | `manage_pages_columns` | 固定ページ一覧のカラムを変更 |
| `#[ManageUsersColumnsFilter]` | `manage_users_columns` | ユーザー一覧のカラムを変更 |

## コード例

### 管理メニューと通知の登録

```php
<?php

declare(strict_types=1);

namespace App\Admin\Subscriber;

use WpPack\Component\Hook\Attribute\Admin\Action\AdminMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminEnqueueScriptsAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminNoticesAction;

final class AdminPageSubscriber
{
    #[AdminMenuAction]
    public function registerMenu(): void
    {
        add_menu_page(
            __('My Plugin Settings', 'my-plugin'),
            __('My Plugin', 'my-plugin'),
            'manage_options',
            'my-plugin-settings',
            [$this, 'renderSettingsPage'],
            'dashicons-admin-generic',
            80
        );
    }

    #[AdminEnqueueScriptsAction]
    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'toplevel_page_my-plugin-settings') {
            return;
        }

        wp_enqueue_style('my-plugin-admin', plugins_url('assets/admin.css', __DIR__));
        wp_enqueue_script('my-plugin-admin', plugins_url('assets/admin.js', __DIR__), ['jquery'], '1.0.0', true);
    }

    #[AdminNoticesAction]
    public function showSetupNotice(): void
    {
        if (get_option('my_plugin_configured')) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html__('My Plugin requires configuration. Please visit the settings page.', 'my-plugin')
        );
    }
}
```

### 投稿一覧のカスタムカラム

```php
<?php

declare(strict_types=1);

namespace App\Admin\Subscriber;

use WpPack\Component\Hook\Attribute\Admin\Action\ManagePostsCustomColumnAction;
use WpPack\Component\Hook\Attribute\Admin\Filter\ManagePostsColumnsFilter;

final class PostColumnsSubscriber
{
    #[ManagePostsColumnsFilter]
    public function addColumns(array $columns): array
    {
        $columns['thumbnail'] = __('Thumbnail', 'my-plugin');
        $columns['word_count'] = __('Word Count', 'my-plugin');

        return $columns;
    }

    #[ManagePostsCustomColumnAction]
    public function renderColumnContent(string $columnName, int $postId): void
    {
        match ($columnName) {
            'thumbnail' => $this->renderThumbnail($postId),
            'word_count' => $this->renderWordCount($postId),
            default => null,
        };
    }

    private function renderThumbnail(int $postId): void
    {
        if (has_post_thumbnail($postId)) {
            echo get_the_post_thumbnail($postId, [50, 50]);
        }
    }

    private function renderWordCount(int $postId): void
    {
        $content = get_post_field('post_content', $postId);
        echo esc_html((string) str_word_count(wp_strip_all_tags($content)));
    }
}
```

### 管理バーのカスタマイズ

```php
<?php

declare(strict_types=1);

namespace App\Admin\Subscriber;

use WpPack\Component\Hook\Attribute\Admin\Action\AdminBarMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Filter\AdminBodyClassFilter;

final class AdminBarSubscriber
{
    #[AdminBarMenuAction(priority: 100)]
    public function addToolbarItems(\WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $adminBar->add_node([
            'id' => 'my-plugin-clear-cache',
            'title' => __('Clear Cache', 'my-plugin'),
            'href' => wp_nonce_url(admin_url('admin-post.php?action=clear_cache'), 'clear_cache'),
            'meta' => ['class' => 'my-plugin-toolbar-item'],
        ]);
    }

    #[AdminBodyClassFilter]
    public function addBodyClasses(string $classes): string
    {
        if (get_option('my_plugin_dark_mode')) {
            $classes .= ' my-plugin-dark-mode';
        }

        return $classes;
    }
}
```
