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
    label: 'My Plugin',
    menuLabel: 'My Plugin',
    icon: 'dashicons-admin-generic',
    position: 25,
)]
class MyPluginPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<div class="wrap">'
            . '<h1>' . esc_html($this->label) . '</h1>'
            . '<p>Plugin content here.</p>'
            . '</div>';
    }
}
```

### AsAdminPage アトリビュート

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `slug` | `string` | （必須） | メニュースラッグ |
| `label` | `string` | （必須） | ページタイトル |
| `menuLabel` | `string` | `''`（= label） | メニュー表示名 |
| `parent` | `?string` | `null` | 親メニュースラッグ（null = トップレベル） |
| `icon` | `?string` | `null` | メニューアイコン URL / dashicons クラス |
| `position` | `?int` | `null` | メニュー表示位置 |
| `scope` | `AdminScope` | `AdminScope::Site` | 登録先スコープ（Site / Network / Auto） |

> [!NOTE]
> `#[IsGranted('capability')]`（Security コンポーネント）をクラスに付与して必要な権限を指定します。未指定時のデフォルトは `'manage_options'` です。

### サブメニューページ

`parent` を指定するとサブメニューとして登録されます。

```php
#[AsAdminPage(
    slug: 'my-plugin-settings',
    label: 'Settings',
    parent: 'my-plugin',
)]
class MyPluginSettingsPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<div class="wrap"><h1>Settings</h1></div>';
    }
}
```

### アセットのエンキュー

`enqueue()` をオーバーライドすると、そのページでのみアセットがロードされます。ページスコーピング（hookSuffix による判定）は自動で行われるため、サブクラスは hookSuffix を意識する必要はありません。

```php
#[AsAdminPage(slug: 'my-plugin', label: 'My Plugin')]
class MyPluginPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<div class="wrap"><h1>My Plugin</h1></div>';
    }

    protected function enqueue(): void
    {
        wp_enqueue_script('my-plugin-admin', plugins_url('assets/js/admin.js', MY_PLUGIN_FILE));
        wp_enqueue_style('my-plugin-admin', plugins_url('assets/css/admin.css', MY_PLUGIN_FILE));
    }
}
```

### Templating 連携

`AdminPageRegistry` に `TemplateRendererInterface` を渡すと、登録時に各ページへ自動注入されます。`render()` ショートカットメソッドで `__invoke()` 内から簡潔にテンプレートを呼び出せます。

```php
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Templating\TemplateRendererInterface;

// Registry に TemplateRendererInterface を渡す
$registry = new AdminPageRegistry($renderer);
$registry->register(new MyPluginPage());
```

```php
#[AsAdminPage(slug: 'my-plugin', label: 'My Plugin')]
class MyPluginPage extends AbstractAdminPage
{
    // render() ショートカットを使用
    public function __invoke(): string
    {
        return $this->render('admin/my-plugin.html.twig', [
            'label' => $this->label,
        ]);
    }
}
```

DI コンテナで直接 `TemplateRendererInterface` を注入する場合は、コンストラクタインジェクションも引き続き利用できます。

```php
#[AsAdminPage(slug: 'my-plugin', label: 'My Plugin')]
class MyPluginPage extends AbstractAdminPage
{
    public function __construct(
        private readonly TemplateRendererInterface $renderer,
    ) {}

    public function __invoke(): string
    {
        return $this->renderer->render('admin/my-plugin.html.twig', [
            'label' => $this->label,
        ]);
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
$registry->unregister('my-plugin');

// サブメニュー削除
$registry->unregisterSubmenu('my-plugin', 'my-plugin-settings');
```

## Network Admin Support (AdminScope)

The Admin component supports registering pages in the network admin for multisite environments via the `AdminScope` enum.

### AdminScope Enum

| Value | Description |
|-------|-------------|
| `AdminScope::Site` | Always register in the site admin (`admin_menu`). This is the default. |
| `AdminScope::Network` | Always register in the network admin (`network_admin_menu`). |
| `AdminScope::Auto` | Auto-detect based on whether the plugin is network-activated. |

### Usage

Specify the `scope` parameter on the `#[AsAdminPage]` attribute:

```php
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AdminScope;
use WpPack\Component\Admin\Attribute\AsAdminPage;

#[AsAdminPage(
    slug: 'my-plugin',
    label: 'My Plugin',
    parent: 'options-general.php',
    scope: AdminScope::Auto,
)]
class MyPluginPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<div class="wrap"><h1>My Plugin</h1></div>';
    }
}
```

### How Auto-Detection Works

When `AdminScope::Auto` is used, the registry determines the target admin screen at registration time. In the plugin's `boot()` method, pass the result of `AbstractPlugin::isNetworkActivated()` to `AdminPageRegistry::register()`:

```php
public function boot(Container $container): void
{
    $pageRegistry = $container->get(AdminPageRegistry::class);
    $page = $container->get(MyPluginPage::class);
    $pageRegistry->register($page, $this->isNetworkActivated());
}
```

`isNetworkActivated()` returns `true` when the plugin is network-activated on a multisite installation, and `false` otherwise (including single-site). The registry calls `setNetwork()` on the page, which only takes effect when the scope is `Auto`.

### Parent Menu Auto-Adjustment

When a page is registered in network admin mode, the parent menu slug `options-general.php` is automatically adjusted to `settings.php`, because the network admin uses `settings.php` instead of `options-general.php`. This adjustment happens transparently in `addMenuPage()`, so page classes do not need to handle this difference.

### Hook Selection

Based on the resolved network flag, `AdminPageRegistry` hooks the page into the appropriate menu action:

| Mode | Hook |
|------|------|
| Site admin | `admin_menu` |
| Network admin | `network_admin_menu` |

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
