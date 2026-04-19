## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/NavigationMenu/Subscriber/`

### メニュー登録フック

メニューロケーション登録には、Hook コンポーネントの `#[AfterSetupThemeAction]` アトリビュートを使用します。

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
use WPPack\Component\Hook\Attribute\NavigationMenu\WpNavMenuArgsFilter;

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
use WPPack\Component\Hook\Attribute\NavigationMenu\WpNavMenuItemsFilter;

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
use WPPack\Component\Hook\Attribute\NavigationMenu\WpNavMenuObjectsFilter;

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
use WPPack\Component\Hook\Attribute\NavigationMenu\WpNavMenuItemCustomFieldsAction;

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
use WPPack\Component\Hook\Attribute\NavigationMenu\WpUpdateNavMenuItemAction;

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
use WPPack\Component\Hook\Attribute\NavigationMenu\NavMenuCssClassFilter;

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

## クイックリファレンス

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
