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
| `parent` | `?string` | `null` | 親メニュースラッグ（null = トップレベル） |
| `icon` | `?string` | `null` | メニューアイコン URL / dashicons クラス |
| `position` | `?int` | `null` | メニュー表示位置 |

> **権限チェック:** `#[IsGranted('capability')]`（Security コンポーネント）をクラスに付与して必要な権限を指定します。未指定時のデフォルトは `'manage_options'` です。

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

→ [Hook コンポーネントのドキュメント](../hook/admin.md) を参照してください。

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

### 推奨
- **Setting コンポーネント** (`wppack/setting`) - 設定ページ（Settings API 統合）
- **DashboardWidget コンポーネント** (`wppack/dashboard-widget`) - ダッシュボードウィジェット
- **DependencyInjection コンポーネント** (`wppack/dependency-injection`) - サービスコンテナ
