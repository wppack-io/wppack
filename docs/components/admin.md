# Admin コンポーネント

**パッケージ:** `wppack/admin`
**名前空間:** `WpPack\Component\Admin\`
**レイヤー:** Feature

WordPress 管理画面のアクション・フィルターフックを Named Hook アトリビュートで型安全に登録するためのコンポーネントです。

## インストール

```bash
composer require wppack/admin
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// functions.php に散らばった管理フック
function my_admin_menu() {
    add_menu_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', 'my_plugin_page');
}
add_action('admin_menu', 'my_admin_menu');

function my_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_my-plugin') return;
    wp_enqueue_script('my-script', plugin_dir_url(__FILE__) . 'script.js');
}
add_action('admin_enqueue_scripts', 'my_admin_scripts');

function my_admin_notice() {
    ?>
    <div class="notice notice-info">
        <p>My notice message</p>
    </div>
    <?php
}
add_action('admin_notices', 'my_admin_notice');
```

### After（WpPack）

```php
use WpPack\Component\Admin\Attribute\AdminMenuAction;
use WpPack\Component\Admin\Attribute\AdminEnqueueScriptsAction;
use WpPack\Component\Admin\Attribute\AdminNoticesAction;

class MyPluginAdmin
{
    #[AdminMenuAction]
    public function registerMenu(): void
    {
        add_menu_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', [$this, 'renderPage']);
    }

    #[AdminEnqueueScriptsAction]
    public function enqueueScripts(string $hook): void
    {
        if ($hook !== 'toplevel_page_my-plugin') return;
        wp_enqueue_script('my-script', plugin_dir_url(__FILE__) . 'script.js');
    }

    #[AdminNoticesAction]
    public function displayNotice(): void
    {
        ?>
        <div class="notice notice-info">
            <p>My notice message</p>
        </div>
        <?php
    }
}
```

## Named Hook アトリビュート

Admin コンポーネントは、WordPress 管理画面機能用の Named Hook アトリビュートを提供します。

### メニュー管理

```php
#[AdminMenuAction(priority?: int = 10)]              // admin_menu - 管理メニューの追加
#[NetworkAdminMenuAction(priority?: int = 10)]       // network_admin_menu - ネットワーク管理メニュー
#[UserAdminMenuAction(priority?: int = 10)]          // user_admin_menu - ユーザー管理メニュー
```

### 管理画面初期化

```php
#[AdminInitAction(priority?: int = 10)]              // admin_init - 管理画面の初期化
#[CurrentScreenAction(priority?: int = 10)]          // current_screen - 現在のスクリーンが読み込まれた時
```

### アセット

```php
#[AdminEnqueueScriptsAction(priority?: int = 10)]    // admin_enqueue_scripts - 管理画面スクリプト/スタイルのエンキュー
#[AdminPrintStylesAction(priority?: int = 10)]       // admin_print_styles - 管理画面スタイルの出力
#[AdminPrintScriptsAction(priority?: int = 10)]      // admin_print_scripts - 管理画面スクリプトの出力
```

### 通知

```php
#[AdminNoticesAction(priority?: int = 10)]           // admin_notices - 管理画面通知の表示
#[NetworkAdminNoticesAction(priority?: int = 10)]    // network_admin_notices - ネットワーク管理通知
#[UserAdminNoticesAction(priority?: int = 10)]       // user_admin_notices - ユーザー管理通知
#[AllAdminNoticesAction(priority?: int = 10)]        // all_admin_notices - すべての管理通知
```

### ダッシュボード

```php
#[WpDashboardSetupAction(priority?: int = 10)]       // wp_dashboard_setup - ダッシュボードウィジェットのセットアップ
#[WpNetworkDashboardSetupAction(priority?: int = 10)] // wp_network_dashboard_setup - ネットワークダッシュボード
```

### ヘッダー/フッター

```php
#[AdminHeadAction(priority?: int = 10)]              // admin_head - 管理画面ヘッドコンテンツ
#[AdminFooterAction(priority?: int = 10)]            // admin_footer - 管理画面フッターコンテンツ
#[AdminPrintFooterScriptsAction(priority?: int = 10)] // admin_print_footer_scripts - フッタースクリプト
```

### リストテーブル

```php
#[ManagePostsColumnsFilter(priority?: int = 10)]     // manage_posts_columns - 投稿カラムの変更
#[ManagePostsCustomColumnAction(priority?: int = 10)] // manage_posts_custom_column - カラムコンテンツの表示
#[ManagePagesColumnsFilter(priority?: int = 10)]     // manage_pages_columns - 固定ページカラムの変更
#[ManageUsersColumnsFilter(priority?: int = 10)]     // manage_users_columns - ユーザーカラムの変更
```

### 管理バー

```php
#[AdminBarMenuAction(priority?: int = 10)]           // admin_bar_menu - 管理バーの変更
#[WpBeforeAdminBarRenderAction(priority?: int = 10)] // wp_before_admin_bar_render - レンダリング前
```

### Named Hook の使用例

```php
use WpPack\Component\Admin\Attribute\AdminMenuAction;
use WpPack\Component\Admin\Attribute\AdminInitAction;
use WpPack\Component\Admin\Attribute\AdminEnqueueScriptsAction;
use WpPack\Component\Admin\Attribute\AdminNoticesAction;

class AdminInterface
{
    #[AdminMenuAction]
    public function setupMenus(): void
    {
        add_menu_page(
            'WpPack Dashboard',
            'WpPack',
            'manage_options',
            'wppack-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-admin-generic',
            25
        );
    }

    #[AdminInitAction]
    public function initializeSettings(): void
    {
        register_setting('wppack_settings', 'wppack_options', [
            'sanitize_callback' => [$this, 'sanitizeOptions'],
            'show_in_rest' => true,
        ]);
    }

    #[AdminEnqueueScriptsAction]
    public function enqueueAssets(string $hookSuffix): void
    {
        wp_enqueue_style('wppack-admin', plugins_url('assets/css/admin.css', WPPACK_PLUGIN_FILE));

        if (strpos($hookSuffix, 'wppack') !== false) {
            wp_enqueue_script(
                'wppack-admin-app',
                plugins_url('assets/js/admin-app.js', WPPACK_PLUGIN_FILE),
                ['wp-element', 'wp-components', 'wp-api'],
                WPPACK_VERSION,
                true
            );
        }
    }

    #[AdminNoticesAction]
    public function displayNotices(): void
    {
        if (!get_option('wppack_api_key')) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    'WpPack requires an API key. <a href="%s">Configure it now</a>.',
                    admin_url('admin.php?page=wppack-settings')
                )
            );
        }
    }
}
```

### カスタム管理カラム

```php
use WpPack\Component\Admin\Attribute\ManagePostsColumnsFilter;
use WpPack\Component\Admin\Attribute\ManagePostsCustomColumnAction;

class PostColumns
{
    #[ManagePostsColumnsFilter]
    public function addCustomColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $value) {
            $newColumns[$key] = $value;
            if ($key === 'title') {
                $newColumns['wppack_views'] = 'Views';
            }
        }
        $newColumns['wppack_featured'] = '<span class="dashicons dashicons-star-filled"></span>';
        return $newColumns;
    }

    #[ManagePostsCustomColumnAction]
    public function displayCustomColumns(string $columnName, int $postId): void
    {
        match ($columnName) {
            'wppack_views' => printf('%s', number_format_i18n(
                get_post_meta($postId, '_wppack_view_count', true) ?: 0
            )),
            'wppack_featured' => get_post_meta($postId, '_wppack_featured', true)
                ? print '<span class="dashicons dashicons-star-filled" style="color: #f0ad4e;"></span>'
                : null,
            default => null,
        };
    }
}
```

### ネットワーク管理とダッシュボードウィジェット

```php
use WpPack\Component\Admin\Attribute\NetworkAdminMenuAction;
use WpPack\Component\Admin\Attribute\WpDashboardSetupAction;

class NetworkAdmin
{
    #[NetworkAdminMenuAction]
    public function registerNetworkMenus(): void
    {
        add_menu_page(
            'Network WpPack',
            'WpPack Network',
            'manage_network_options',
            'wppack-network',
            [$this, 'renderNetworkPage'],
            'dashicons-networking',
            30
        );
    }
}

class DashboardWidgets
{
    #[WpDashboardSetupAction]
    public function registerDashboardWidgets(): void
    {
        wp_add_dashboard_widget(
            'wppack_status_widget',
            'WpPack Status',
            [$this, 'renderStatusWidget'],
            null,
            null,
            'normal',
            'high'
        );
    }
}
```

## Hook アトリビュートリファレンス

```php
// メニュー管理
#[AdminMenuAction(priority?: int = 10)]              // 管理メニューの追加
#[NetworkAdminMenuAction(priority?: int = 10)]       // ネットワーク管理メニュー
#[UserAdminMenuAction(priority?: int = 10)]          // ユーザー管理メニュー

// 管理画面初期化
#[AdminInitAction(priority?: int = 10)]              // 管理画面の初期化
#[CurrentScreenAction(priority?: int = 10)]          // 現在のスクリーンが読み込まれた時

// アセット
#[AdminEnqueueScriptsAction(priority?: int = 10)]    // 管理画面スクリプト/スタイルのエンキュー
#[AdminPrintStylesAction(priority?: int = 10)]       // 管理画面スタイルの出力
#[AdminPrintScriptsAction(priority?: int = 10)]      // 管理画面スクリプトの出力

// 通知
#[AdminNoticesAction(priority?: int = 10)]           // 管理画面通知の表示
#[NetworkAdminNoticesAction(priority?: int = 10)]    // ネットワーク管理通知
#[UserAdminNoticesAction(priority?: int = 10)]       // ユーザー管理通知
#[AllAdminNoticesAction(priority?: int = 10)]        // すべての管理通知

// ダッシュボード
#[WpDashboardSetupAction(priority?: int = 10)]       // ダッシュボードウィジェットのセットアップ
#[WpNetworkDashboardSetupAction(priority?: int = 10)] // ネットワークダッシュボード

// ヘッダー/フッター
#[AdminHeadAction(priority?: int = 10)]              // 管理画面ヘッドコンテンツ
#[AdminFooterAction(priority?: int = 10)]            // 管理画面フッターコンテンツ
#[AdminPrintFooterScriptsAction(priority?: int = 10)] // フッタースクリプト

// リストテーブル
#[ManagePostsColumnsFilter(priority?: int = 10)]     // 投稿カラムの変更
#[ManagePostsCustomColumnAction(priority?: int = 10)] // カラムコンテンツの表示
#[ManagePagesColumnsFilter(priority?: int = 10)]     // 固定ページカラムの変更
#[ManageUsersColumnsFilter(priority?: int = 10)]     // ユーザーカラムの変更

// 管理バー
#[AdminBarMenuAction(priority?: int = 10)]           // 管理バーの変更
#[WpBeforeAdminBarRenderAction(priority?: int = 10)] // レンダリング前
```

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress アクション/フィルター登録用

### 推奨
- **DependencyInjection コンポーネント** - サービスコンテナと管理サービス用
- **Security コンポーネント** - 権限チェックと nonce 検証用
- **Option コンポーネント** - 管理設定ストレージ用
- **EventDispatcher コンポーネント** - 管理ライフサイクルイベント用
