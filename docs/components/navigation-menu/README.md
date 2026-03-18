# NavigationMenu コンポーネント

**パッケージ:** `wppack/navigation-menu`
**名前空間:** `WpPack\Component\NavigationMenu\`
**レイヤー:** Application

WordPress のメニュー関数 `register_nav_menus()` / `wp_nav_menu()` をモダンな PHP でラップし、メニューロケーション自動登録とメニュー関連の Named Hook アトリビュートを提供するコンポーネントです。

## インストール

```bash
composer require wppack/navigation-menu
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// Traditional WordPress - procedural and scattered
add_action('after_setup_theme', function() {
    register_nav_menus([
        'primary' => __('Primary Menu', 'my-theme'),
        'footer' => __('Footer Menu', 'my-theme'),
    ]);
});

// Modify menu output
add_filter('wp_nav_menu_items', function($items, $args) {
    if ($args->theme_location === 'primary') {
        $items .= '<li class="menu-search">' . get_search_form(false) . '</li>';
    }
    return $items;
}, 10, 2);

// In template
wp_nav_menu([
    'theme_location' => 'primary',
    'container' => 'nav',
    'container_class' => 'primary-navigation',
    'menu_class' => 'menu',
    'fallback_cb' => false,
    'depth' => 2,
]);
```

### After（WpPack）

```php
use WpPack\Component\Hook\Attribute\AfterSetupThemeAction;
use WpPack\Component\Hook\Attribute\NavigationMenu\Filter\WpNavMenuItemsFilter;
use WpPack\Component\NavigationMenu\MenuRegistry;

class NavigationManager
{
    public function __construct(
        private readonly MenuRegistry $menus,
    ) {}

    #[AfterSetupThemeAction]
    public function registerMenus(): void
    {
        $this->menus->registerLocations([
            'primary' => __('Primary Menu', 'wppack'),
            'footer' => __('Footer Menu', 'wppack'),
        ]);
    }

    #[WpNavMenuItemsFilter]
    public function addSearchForm(string $items, \stdClass $args): string
    {
        if ($args->theme_location === 'primary') {
            $items .= '<li class="menu-item menu-item-search">' . get_search_form(false) . '</li>';
        }
        return $items;
    }
}
```

## 主要クラス

| クラス | 説明 |
|--------|------|
| `MenuLocationProviderInterface` | メニューロケーションを提供するインターフェース |
| `MenuRegistry` | メニューロケーションの登録・管理 |

## MenuLocationProviderInterface

DI コンテナで auto-tag し、`MenuRegistry` に自動収集されるパターンです。

```php
use WpPack\Component\NavigationMenu\MenuLocationProviderInterface;

class ThemeMenuProvider implements MenuLocationProviderInterface
{
    public function getMenuLocations(): array
    {
        return [
            'primary' => __('Primary Menu', 'my-theme'),
            'footer' => __('Footer Menu', 'my-theme'),
            'mobile' => __('Mobile Navigation', 'my-theme'),
        ];
    }
}
```

## MenuRegistry

メニューロケーションの登録・管理を行うレジストリです。

### プロバイダー経由の一括登録

`addProvider()` でプロバイダーを収集し、`register()` で `after_setup_theme` のタイミングに一括登録します。

```php
use WpPack\Component\NavigationMenu\MenuRegistry;

$registry = new MenuRegistry();
$registry->addProvider(new ThemeMenuProvider());
$registry->register(); // register_nav_menus() が呼ばれる
```

### 直接登録

```php
use WpPack\Component\Hook\Attribute\AfterSetupThemeAction;
use WpPack\Component\NavigationMenu\MenuRegistry;

class MenuManager
{
    public function __construct(
        private readonly MenuRegistry $menus,
    ) {}

    #[AfterSetupThemeAction]
    public function registerMenuLocations(): void
    {
        $this->menus->registerLocations([
            'primary' => __('Primary Navigation', 'wppack'),
            'footer' => __('Footer Menu', 'wppack'),
            'mobile' => __('Mobile Navigation', 'wppack'),
        ]);
    }
}
```

### API リファレンス

```php
$registry->addProvider(MenuLocationProviderInterface $provider): void
$registry->register(): void                          // プロバイダーのロケーションを一括登録
$registry->registerLocation(string $location, string $description): void
$registry->registerLocations(array $locations): void
$registry->unregisterLocation(string $location): void
$registry->hasLocation(string $location): bool
$registry->getRegisteredLocations(): array           // ['location' => 'description']
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/navigation-menu.md) を参照してください。
## Hook アトリビュートリファレンス

```php
// メニュー登録
// メニューロケーション登録には Hook コンポーネントの #[AfterSetupThemeAction] を使用

// メニュー表示
#[WpNavMenuArgsFilter(priority?: int = 10)]          // メニュー引数の変更
#[WpNavMenuItemsFilter(priority?: int = 10)]         // メニュー HTML の変更
#[WpNavMenuObjectsFilter(priority?: int = 10)]       // メニューオブジェクトの処理
#[PreWpNavMenuFilter(priority?: int = 10)]           // メニュー出力のオーバーライド

// メニューアイテム
#[WpNavMenuItemCustomFieldsAction(priority?: int = 10)] // カスタムフィールドの追加
#[WpUpdateNavMenuItemAction(priority?: int = 10)]    // メニューアイテムデータの保存
#[WpSetupNavMenuItemFilter(priority?: int = 10)]     // メニューアイテムのセットアップ

// CSS クラス
#[NavMenuCssClassFilter(priority?: int = 10)]        // メニューアイテムクラス
#[NavMenuItemIdFilter(priority?: int = 10)]          // メニューアイテム ID
#[NavMenuLinkAttributesFilter(priority?: int = 10)]  // リンク属性

// メニュー管理
#[WpCreateNavMenuAction(priority?: int = 10)]        // メニュー作成時
#[WpUpdateNavMenuAction(priority?: int = 10)]        // メニュー更新時
#[WpDeleteNavMenuAction(priority?: int = 10)]        // メニュー削除時
```

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress メニュー登録フック用

### 推奨
- **Theme コンポーネント** - テーマ統合
