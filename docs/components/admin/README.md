# Admin コンポーネント

**パッケージ:** `wppack/admin`
**名前空間:** `WpPack\Component\Admin\`
**レイヤー:** Feature

WordPress 管理画面のページ登録とフック管理を提供するコンポーネントです。クラスベースの管理ページ定義と、Named Hook アトリビュートによる型安全なフック登録の 2 つのアプローチをサポートします。

## インストール

```bash
composer require wppack/admin
```

## クラスベース管理ページ

### 基本的な使い方

`AbstractAdminPage` を継承し、`#[AsAdminPage]` アトリビュートでページ設定を宣言します。

```php
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AsAdminPage;

#[AsAdminPage(
    slug: 'my-plugin',
    title: 'My Plugin',
    menuTitle: 'My Plugin',
    icon: 'dashicons-admin-generic',
    position: 25,
)]
class MyPluginPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($this->title) . '</h1>';
        echo '<p>Plugin content here.</p>';
        echo '</div>';
    }
}
```

### AsAdminPage アトリビュート

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `slug` | `string` | （必須） | メニュースラッグ |
| `title` | `string` | （必須） | ページタイトル |
| `menuTitle` | `string` | `''`（= title） | メニュー表示名 |
| `capability` | `string` | `'manage_options'` | 必要な権限 |
| `parent` | `?string` | `null` | 親メニュースラッグ（null = トップレベル） |
| `icon` | `?string` | `null` | メニューアイコン URL / dashicons クラス |
| `position` | `?int` | `null` | メニュー表示位置 |

### サブメニューページ

`parent` を指定するとサブメニューとして登録されます。

```php
#[AsAdminPage(
    slug: 'my-plugin-settings',
    title: 'Settings',
    parent: 'my-plugin',
)]
class MyPluginSettingsPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Settings</h1>';
        echo '</div>';
    }
}
```

### スクリプト・スタイルのエンキュー

`enqueueScripts()` / `enqueueStyles()` をオーバーライドすると、そのページでのみアセットがロードされます（hookSuffix によるページスコープ）。

```php
#[AsAdminPage(slug: 'my-plugin', title: 'My Plugin')]
class MyPluginPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '<div class="wrap"><h1>My Plugin</h1></div>';
    }

    protected function enqueueScripts(string $hookSuffix): void
    {
        wp_enqueue_script('my-plugin-admin', plugins_url('assets/js/admin.js', MY_PLUGIN_FILE));
    }

    protected function enqueueStyles(string $hookSuffix): void
    {
        wp_enqueue_style('my-plugin-admin', plugins_url('assets/css/admin.css', MY_PLUGIN_FILE));
    }
}
```

### AdminPageRegistry

`AdminPageRegistry` でページを登録・削除します。

```php
use WpPack\Component\Admin\AdminPageRegistry;

$registry = new AdminPageRegistry();

// 登録
$registry->register(new MyPluginPage());

// トップレベルメニュー削除
$registry->remove('my-plugin');

// サブメニュー削除
$registry->removeSubmenu('my-plugin', 'my-plugin-settings');
```

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Admin/Subscriber/`

Admin コンポーネントは、WordPress 管理画面機能用の Named Hook アトリビュートを提供します。Hook コンポーネントの `Action` / `Filter` を継承しており、`ReflectionAttribute::IS_INSTANCEOF` で自動検出されます。

### メニュー管理

```php
use WpPack\Component\Admin\Attribute\Action\AdminMenuAction;
use WpPack\Component\Admin\Attribute\Action\NetworkAdminMenuAction;
use WpPack\Component\Admin\Attribute\Action\UserAdminMenuAction;

class AdminMenus
{
    #[AdminMenuAction]
    public function registerMenus(): void
    {
        add_menu_page(
            'WpPack Dashboard',
            'WpPack',
            'manage_options',
            'wppack-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-admin-generic',
            25,
        );
    }
}
```

### アセット

```php
use WpPack\Component\Admin\Attribute\Action\AdminEnqueueScriptsAction;

class AdminAssets
{
    #[AdminEnqueueScriptsAction]
    public function enqueueAssets(string $hookSuffix): void
    {
        if (str_contains($hookSuffix, 'wppack')) {
            wp_enqueue_script('wppack-admin', plugins_url('assets/js/admin.js', WPPACK_FILE));
        }
    }
}
```

### 通知

```php
use WpPack\Component\Admin\Attribute\Action\AdminNoticesAction;

class AdminNotices
{
    #[AdminNoticesAction]
    public function displayNotice(): void
    {
        echo '<div class="notice notice-info"><p>Information notice.</p></div>';
    }
}
```

### セキュリティ

```php
use WpPack\Component\Admin\Attribute\Action\CheckAdminRefererAction;

class SecurityHandler
{
    #[CheckAdminRefererAction]
    public function onRefererCheck(string $action, bool|int $result): void
    {
        // admin referer 検証時の追加ロジック
    }
}
```

### リストテーブルカラム

```php
use WpPack\Component\Admin\Attribute\Action\ManagePostsCustomColumnAction;
use WpPack\Component\Admin\Attribute\Filter\ManagePostsColumnsFilter;

class PostColumns
{
    #[ManagePostsColumnsFilter]
    public function addColumns(array $columns): array
    {
        $columns['views'] = 'Views';

        return $columns;
    }

    #[ManagePostsCustomColumnAction]
    public function displayColumn(string $columnName, int $postId): void
    {
        if ($columnName === 'views') {
            echo esc_html((string) get_post_meta($postId, '_view_count', true));
        }
    }
}
```

### フィルター

```php
use WpPack\Component\Admin\Attribute\Filter\AdminBodyClassFilter;
use WpPack\Component\Admin\Attribute\Filter\AdminFooterTextFilter;
use WpPack\Component\Admin\Attribute\Filter\AdminTitleFilter;

class AdminFilters
{
    #[AdminBodyClassFilter]
    public function addBodyClass(string $classes): string
    {
        return $classes . ' my-plugin-active';
    }

    #[AdminFooterTextFilter]
    public function customizeFooter(string $text): string
    {
        return 'Powered by My Plugin';
    }

    #[AdminTitleFilter]
    public function customizeTitle(string $adminTitle): string
    {
        return 'My Plugin - ' . $adminTitle;
    }
}
```

## Hook アトリビュートリファレンス

### Actions（18）

| アトリビュート | WordPress フック | 説明 |
|--------------|----------------|------|
| `AdminMenuAction` | `admin_menu` | 管理メニューの追加 |
| `NetworkAdminMenuAction` | `network_admin_menu` | ネットワーク管理メニュー |
| `UserAdminMenuAction` | `user_admin_menu` | ユーザー管理メニュー |
| `CurrentScreenAction` | `current_screen` | 現在のスクリーンが読み込まれた時 |
| `AdminEnqueueScriptsAction` | `admin_enqueue_scripts` | 管理画面スクリプト/スタイルのエンキュー |
| `AdminPrintStylesAction` | `admin_print_styles` | 管理画面スタイルの出力 |
| `AdminPrintScriptsAction` | `admin_print_scripts` | 管理画面スクリプトの出力 |
| `AdminNoticesAction` | `admin_notices` | 管理画面通知の表示 |
| `NetworkAdminNoticesAction` | `network_admin_notices` | ネットワーク管理通知 |
| `UserAdminNoticesAction` | `user_admin_notices` | ユーザー管理通知 |
| `AllAdminNoticesAction` | `all_admin_notices` | すべての管理通知 |
| `AdminHeadAction` | `admin_head` | 管理画面ヘッドコンテンツ |
| `AdminFooterAction` | `admin_footer` | 管理画面フッターコンテンツ |
| `AdminPrintFooterScriptsAction` | `admin_print_footer_scripts` | フッタースクリプト |
| `ManagePostsCustomColumnAction` | `manage_posts_custom_column` | カラムコンテンツの表示 |
| `AdminBarMenuAction` | `admin_bar_menu` | 管理バーの変更 |
| `WpBeforeAdminBarRenderAction` | `wp_before_admin_bar_render` | レンダリング前 |
| `CheckAdminRefererAction` | `check_admin_referer` | admin referer 検証 |

### Filters（6）

| アトリビュート | WordPress フック | 説明 |
|--------------|----------------|------|
| `ManagePostsColumnsFilter` | `manage_posts_columns` | 投稿カラムの変更 |
| `ManagePagesColumnsFilter` | `manage_pages_columns` | 固定ページカラムの変更 |
| `ManageUsersColumnsFilter` | `manage_users_columns` | ユーザーカラムの変更 |
| `AdminBodyClassFilter` | `admin_body_class` | 管理画面 body CSS クラス変更 |
| `AdminFooterTextFilter` | `admin_footer_text` | 管理画面フッターテキスト変更 |
| `AdminTitleFilter` | `admin_title` | 管理画面ページタイトル変更 |

すべてのアトリビュートは `priority` パラメータ（デフォルト: `10`）を受け取ります。

```php
#[AdminMenuAction(priority: 5)]  // 優先度 5 で実行
```

## 管理画面初期化について

`admin_init` フックは管理画面のライフサイクルフックであり、Hook コンポーネントが所有しています。管理画面初期化時のフックには Hook コンポーネントの `AdminInitAction` を使用してください。

```php
use WpPack\Component\Hook\Attribute\Action\AdminInitAction;
```

## ダッシュボードウィジェットについて

ダッシュボードウィジェット機能は DashboardWidget コンポーネント（`wppack/dashboard-widget`）が提供しています。`AbstractDashboardWidget` + `#[AsDashboardWidget]` でクラスベースのウィジェット定義が可能です。

## プラグイン / テーマでの配置

プラグインやテーマで管理ページクラスを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Admin/
    └── Page/
        ├── ProductsPage.php
        ├── ReportsPage.php
        └── ImportPage.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 必須
- **Hook コンポーネント** (`wppack/hook`) - Named Hook アトリビュートの基底クラス

### 推奨
- **Setting コンポーネント** (`wppack/setting`) - 設定ページ（Settings API 統合）
- **DashboardWidget コンポーネント** (`wppack/dashboard-widget`) - ダッシュボードウィジェット
- **DependencyInjection コンポーネント** (`wppack/dependency-injection`) - サービスコンテナ
