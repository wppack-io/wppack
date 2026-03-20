# WpPack Widget

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=widget)](https://codecov.io/github/wppack-io/wppack)

A WordPress widget system component. Provides widget definitions via `AbstractWidget` + `#[AsWidget]` attributes, along with Named Hook Attributes.

## Installation

```bash
composer require wppack/widget
```

## Usage

### Widget Definition

```php
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;

#[AsWidget(id: 'recent_posts', label: 'Recent Posts', description: 'Display recent posts')]
class RecentPostsWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        return $args['before_widget'] . '<p>Hello</p>' . $args['after_widget'];
    }
}
```

### WidgetRegistry

```php
use WpPack\Component\Widget\WidgetRegistry;

$registry = new WidgetRegistry();
$registry->register(RecentPostsWidget::class);
$registry->unregister(RecentPostsWidget::class);
$registry->registerSidebar([
    'name' => 'Main Sidebar',
    'id' => 'main-sidebar',
    'before_widget' => '<section id="%1$s" class="widget %2$s">',
    'after_widget' => '</section>',
    'before_title' => '<h3 class="widget-title">',
    'after_title' => '</h3>',
]);
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\Widget\Action\WidgetsInitAction;
use WpPack\Component\Hook\Attribute\Widget\Filter\WidgetTitleFilter;

final class WidgetHooks
{
    #[WidgetsInitAction]
    public function registerWidgets(): void
    {
        // Register widgets
    }

    #[WidgetTitleFilter(priority: 5)]
    public function filterTitle(string $title): string
    {
        return $title;
    }
}
```

**Action Attributes:**
- `#[WidgetsInitAction]` — `widgets_init`
- `#[DynamicSidebarBeforeAction]` — `dynamic_sidebar_before`
- `#[DynamicSidebarAfterAction]` — `dynamic_sidebar_after`

**Filter Attributes:**
- `#[DynamicSidebarHasWidgetsFilter]` — `dynamic_sidebar_has_widgets`
- `#[DynamicSidebarParamsFilter]` — `dynamic_sidebar_params`
- `#[RegisterSidebarFilter]` — `register_sidebar`
- `#[WidgetAreaPreviewFilter]` — `widget_area_preview`
- `#[WidgetContentFilter]` — `widget_content`
- `#[WidgetDisplayCallbackFilter]` — `widget_display_callback`
- `#[WidgetFormCallbackFilter]` — `widget_form_callback`
- `#[WidgetTextFilter]` — `widget_text`
- `#[WidgetTitleFilter]` — `widget_title`
- `#[WidgetUpdateCallbackFilter]` — `widget_update_callback`
- `#[WidgetsPrefetchingFilter]` — `widgets_prefetching`

## Documentation

See [docs/components/widget/](../../../docs/components/widget/) for full documentation.

## License

MIT
