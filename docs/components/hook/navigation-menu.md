## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/NavigationMenu/Subscriber/`

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
