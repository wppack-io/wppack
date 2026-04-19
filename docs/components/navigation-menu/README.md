# NavigationMenu コンポーネント

**パッケージ:** `wppack/navigation-menu`
**名前空間:** `WPPack\Component\NavigationMenu\`
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

### After（WPPack）

```php
use WPPack\Component\Hook\Attribute\AfterSetupThemeAction;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\WpNavMenuItemsFilter;
use WPPack\Component\NavigationMenu\MenuRegistry;

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
use WPPack\Component\NavigationMenu\MenuLocationProviderInterface;

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
use WPPack\Component\NavigationMenu\MenuRegistry;

$registry = new MenuRegistry();
$registry->addProvider(new ThemeMenuProvider());
$registry->register(); // register_nav_menus() が呼ばれる
```

### 直接登録

```php
use WPPack\Component\Hook\Attribute\AfterSetupThemeAction;
use WPPack\Component\NavigationMenu\MenuRegistry;

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
$registry->all(): array           // ['location' => 'description']
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — NavigationMenu](../hook/navigation-menu.md) を参照してください。

## 依存関係

### 推奨
- **Theme コンポーネント** - テーマ統合
