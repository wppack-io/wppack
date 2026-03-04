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
use WpPack\Component\NavigationMenu\Attribute\Filter\WpNavMenuItemsFilter;
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

### メニュー登録フック

メニューロケーション登録には、Hook コンポーネントの `#[AfterSetupThemeAction]` アトリビュートを使用します。

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
        $this->menus->registerLocation('primary', __('Primary Navigation', 'wppack'));
        $this->menus->registerLocation('footer', __('Footer Menu', 'wppack'));
        $this->menus->registerLocation('mobile', __('Mobile Navigation', 'wppack'));
    }
}
```

### メニュー表示フック

#### #[WpNavMenuArgsFilter]

**WordPress フック:** `wp_nav_menu_args`
**使用場面:** 表示前にナビゲーションメニュー引数を変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuArgsFilter;

class MenuArgumentsCustomizer
{
    #[WpNavMenuArgsFilter]
    public function customizeMenuArgs(array $args): array
    {
        if (!empty($args['theme_location'])) {
            $location = $args['theme_location'];
            $args['menu_class'] = "wppack-menu wppack-menu--{$location}";
            $args['container_class'] = "wppack-menu-container--{$location}";
        }

        if ($args['theme_location'] === 'primary') {
            $args['depth'] = 3;
        }

        return $args;
    }
}
```

#### #[WpNavMenuItemsFilter]

**WordPress フック:** `wp_nav_menu_items`
**使用場面:** メニュー HTML 出力を変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuItemsFilter;

class MenuItemsEnhancer
{
    #[WpNavMenuItemsFilter]
    public function enhanceMenuItems(string $items, \stdClass $args): string
    {
        if ($args->theme_location === 'primary') {
            $items .= '<li class="menu-item menu-item-search">' . get_search_form(false) . '</li>';
        }

        if ($args->theme_location === 'account') {
            if (is_user_logged_in()) {
                $items .= sprintf(
                    '<li class="menu-item"><a href="%s">%s</a></li>',
                    wp_logout_url(home_url()),
                    __('Logout', 'wppack')
                );
            } else {
                $items .= sprintf(
                    '<li class="menu-item"><a href="%s">%s</a></li>',
                    wp_login_url(get_permalink()),
                    __('Login', 'wppack')
                );
            }
        }

        return $items;
    }
}
```

#### #[WpNavMenuObjectsFilter]

**WordPress フック:** `wp_nav_menu_objects`
**使用場面:** レンダリング前にメニューアイテムオブジェクトを変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuObjectsFilter;

class MenuObjectProcessor
{
    #[WpNavMenuObjectsFilter]
    public function processMenuObjects(array $sorted_menu_items, \stdClass $args): array
    {
        foreach ($sorted_menu_items as &$item) {
            // Mark external links
            if ($this->isExternalLink($item->url)) {
                $item->classes[] = 'external-link';
                $item->target = '_blank';
                $item->xfn = 'noopener noreferrer';
            }

            // Add icon support
            if ($icon = get_post_meta($item->ID, '_menu_item_icon', true)) {
                $item->classes[] = 'has-icon';
                $item->title = sprintf('<i class="icon-%s"></i> %s', esc_attr($icon), $item->title);
            }
        }

        return $sorted_menu_items;
    }

    private function isExternalLink(string $url): bool
    {
        return !empty($url) && !str_contains($url, home_url());
    }
}
```

### メニューアイテムフック

#### #[WpNavMenuItemCustomFieldsAction]

**WordPress フック:** `wp_nav_menu_item_custom_fields`
**使用場面:** 管理画面でメニューアイテムにカスタムフィールドを追加する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuItemCustomFieldsAction;

class MenuItemFields
{
    #[WpNavMenuItemCustomFieldsAction]
    public function addIconField(int $item_id, \WP_Post $item, int $depth, \stdClass $args): void
    {
        $icon = get_post_meta($item_id, '_menu_item_icon', true);
        ?>
        <p class="field-icon description description-wide">
            <label for="edit-menu-item-icon-<?php echo $item_id; ?>">
                <?php _e('Icon', 'wppack'); ?><br />
                <input type="text"
                       name="menu-item-icon[<?php echo $item_id; ?>]"
                       id="edit-menu-item-icon-<?php echo $item_id; ?>"
                       class="widefat"
                       value="<?php echo esc_attr($icon); ?>">
            </label>
        </p>
        <?php
    }
}
```

#### #[WpUpdateNavMenuItemAction]

**WordPress フック:** `wp_update_nav_menu_item`
**使用場面:** カスタムメニューアイテムデータを保存する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpUpdateNavMenuItemAction;

class MenuItemSaver
{
    #[WpUpdateNavMenuItemAction]
    public function saveCustomFields(int $menu_id, int $menu_item_db_id, array $args): void
    {
        if (isset($_POST['menu-item-icon'][$menu_item_db_id])) {
            $icon = sanitize_text_field($_POST['menu-item-icon'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_icon', $icon);
        }
    }
}
```

### CSS クラスフック

#### #[NavMenuCssClassFilter]

**WordPress フック:** `nav_menu_css_class`
**使用場面:** メニューアイテムの CSS クラスを変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\NavMenuCssClassFilter;

class MenuClassCustomizer
{
    #[NavMenuCssClassFilter]
    public function customizeItemClasses(array $classes, \WP_Post $item, \stdClass $args, int $depth): array
    {
        $classes[] = 'menu-item-depth-' . $depth;

        if ($item->type === 'post_type') {
            $classes[] = 'menu-item-' . $item->object;
        }

        return array_unique(array_filter($classes));
    }
}
```

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
