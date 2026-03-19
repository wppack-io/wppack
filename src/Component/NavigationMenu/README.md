# WpPack NavigationMenu

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=navigation_menu)](https://codecov.io/github/wppack-io/wppack)

A component for managing WordPress navigation menus with modern PHP. Provides automatic menu location registration via `MenuLocationProviderInterface` and Named Hook attributes.

## Installation

```bash
composer require wppack/navigation-menu
```

## Usage

### MenuLocationProviderInterface (DI Auto-Collection)

```php
use WpPack\Component\NavigationMenu\MenuLocationProviderInterface;

class ThemeMenuProvider implements MenuLocationProviderInterface
{
    public function getMenuLocations(): array
    {
        return [
            'primary' => __('Primary Menu', 'my-theme'),
            'footer' => __('Footer Menu', 'my-theme'),
        ];
    }
}
```

### MenuRegistry (Direct Usage)

```php
use WpPack\Component\NavigationMenu\MenuRegistry;

$registry = new MenuRegistry();
$registry->registerLocations([
    'primary' => 'Primary Menu',
    'footer' => 'Footer Menu',
]);
$registry->unregisterLocation('footer');
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\NavigationMenu\Filter\WpNavMenuItemsFilter;
use WpPack\Component\Hook\Attribute\NavigationMenu\Filter\NavMenuCssClassFilter;
use WpPack\Component\Hook\Attribute\NavigationMenu\Action\WpCreateNavMenuAction;

final class MenuCustomizer
{
    #[WpNavMenuItemsFilter]
    public function addSearchForm(string $items, \stdClass $args): string
    {
        if ($args->theme_location === 'primary') {
            $items .= '<li class="menu-item-search">' . get_search_form(false) . '</li>';
        }
        return $items;
    }

    #[NavMenuCssClassFilter(priority: 5)]
    public function customizeClasses(array $classes, \WP_Post $item): array
    {
        $classes[] = 'custom-class';
        return $classes;
    }

    #[WpCreateNavMenuAction]
    public function onMenuCreated(string $menu_name, int $term_id): void
    {
        // Handle menu creation
    }
}
```

**Action Attributes:**
- `#[WpNavMenuItemCustomFieldsAction]` — `wp_nav_menu_item_custom_fields`
- `#[WpUpdateNavMenuItemAction]` — `wp_update_nav_menu_item`
- `#[WpCreateNavMenuAction]` — `wp_create_nav_menu`
- `#[WpUpdateNavMenuAction]` — `wp_update_nav_menu`
- `#[WpDeleteNavMenuAction]` — `wp_delete_nav_menu`

**Filter Attributes:**
- `#[WpNavMenuArgsFilter]` — `wp_nav_menu_args`
- `#[WpNavMenuItemsFilter]` — `wp_nav_menu_items`
- `#[WpNavMenuObjectsFilter]` — `wp_nav_menu_objects`
- `#[PreWpNavMenuFilter]` — `pre_wp_nav_menu`
- `#[NavMenuCssClassFilter]` — `nav_menu_css_class`
- `#[WpSetupNavMenuItemFilter]` — `wp_setup_nav_menu_item`
- `#[NavMenuItemIdFilter]` — `nav_menu_item_id`
- `#[NavMenuLinkAttributesFilter]` — `nav_menu_link_attributes`

## Documentation

See [docs/components/navigation-menu.md](../../../docs/components/navigation-menu.md) for details.

## License

MIT
