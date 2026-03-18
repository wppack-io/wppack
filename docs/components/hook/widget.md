## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Widget/Subscriber/`

Widget コンポーネントは、WordPress ウィジェット機能のための Named Hook アトリビュートを提供します。

### ウィジェット登録フック

#### #[WidgetsInitAction]

**WordPress フック:** `widgets_init`

```php
use WpPack\Component\Hook\Attribute\Widget\Action\WidgetsInitAction;
use WpPack\Component\Widget\WidgetRegistry;

class WidgetManager
{
    private WidgetRegistry $widgetRegistry;

    public function __construct(WidgetRegistry $widgetRegistry)
    {
        $this->widgetRegistry = $widgetRegistry;
    }

    #[WidgetsInitAction]
    public function registerWidgets(): void
    {
        $this->widgetRegistry->register(RecentPostsWidget::class);
        $this->widgetRegistry->register(SocialLinksWidget::class);
        $this->widgetRegistry->register(NewsletterWidget::class);

        $this->widgetRegistry->registerSidebar([
            'name' => __('Main Sidebar', 'wppack'),
            'id' => 'main-sidebar',
            'description' => __('The primary widget area', 'wppack'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ]);
    }

    #[WidgetsInitAction(priority: 20)]
    public function registerConditionalWidgets(): void
    {
        if (class_exists('WooCommerce')) {
            $this->widgetRegistry->register(ProductCarouselWidget::class);
        }
    }
}
```

### ウィジェット表示フック

#### #[DynamicSidebarBeforeAction]

**WordPress フック:** `dynamic_sidebar_before`

```php
use WpPack\Component\Hook\Attribute\Widget\Action\DynamicSidebarBeforeAction;

class SidebarManager
{
    #[DynamicSidebarBeforeAction]
    public function beforeSidebarDisplay(int|string $index, bool $has_widgets): void
    {
        if ($index === 'main-sidebar') {
            echo '<div class="main-sidebar-wrapper">';
        }
    }
}
```

#### #[DynamicSidebarAfterAction]

**WordPress フック:** `dynamic_sidebar_after`

```php
use WpPack\Component\Hook\Attribute\Widget\Action\DynamicSidebarAfterAction;

class SidebarEnhancer
{
    #[DynamicSidebarAfterAction]
    public function afterSidebarDisplay(int|string $index, bool $had_widgets): void
    {
        if ($index === 'main-sidebar') {
            echo '</div>';
        }

        if (!$had_widgets && $index === 'footer-widgets') {
            echo '<div class="no-widgets-message">';
            echo '<p>' . __('No widgets in this area yet.', 'wppack') . '</p>';
            echo '</div>';
        }
    }
}
```

#### #[DynamicSidebarParamsFilter]

**WordPress フック:** `dynamic_sidebar_params`

```php
use WpPack\Component\Hook\Attribute\Widget\Filter\DynamicSidebarParamsFilter;

class WidgetCustomizer
{
    #[DynamicSidebarParamsFilter]
    public function customizeWidgetParams(array $params): array
    {
        global $wp_registered_widgets;

        $widget_id = $params[0]['widget_id'];
        $widget_obj = $wp_registered_widgets[$widget_id];

        if (isset($widget_obj['classname'])) {
            $params[0]['before_widget'] = str_replace(
                'class="',
                'class="custom-class ',
                $params[0]['before_widget']
            );
        }

        return $params;
    }
}
```

### ウィジェット更新フック

#### #[WidgetUpdateCallbackFilter]

**WordPress フック:** `widget_update_callback`

```php
use WpPack\Component\Hook\Attribute\Widget\Filter\WidgetUpdateCallbackFilter;

class WidgetValidator
{
    #[WidgetUpdateCallbackFilter]
    public function validateWidgetUpdate($instance, $new_instance, $old_instance, $widget)
    {
        if (isset($new_instance['title'])) {
            $instance['title'] = sanitize_text_field($new_instance['title']);
        }

        $instance['_last_validated'] = current_time('timestamp');

        return $instance;
    }
}
```

#### #[WidgetFormCallbackFilter]

**WordPress フック:** `widget_form_callback`

```php
use WpPack\Component\Hook\Attribute\Widget\Filter\WidgetFormCallbackFilter;

class WidgetFormEnhancer
{
    #[WidgetFormCallbackFilter]
    public function enhanceWidgetForm($instance, $widget)
    {
        if ($this->shouldHideWidget($widget)) {
            return false;
        }

        $defaults = $this->getWidgetDefaults(get_class($widget));
        $instance = wp_parse_args($instance, $defaults);

        return $instance;
    }
}
```

#### #[WidgetDisplayCallbackFilter]

**WordPress フック:** `widget_display_callback`

```php
use WpPack\Component\Hook\Attribute\Widget\Filter\WidgetDisplayCallbackFilter;

class WidgetVisibility
{
    #[WidgetDisplayCallbackFilter]
    public function controlWidgetDisplay($instance, $widget, $args)
    {
        if ($this->shouldHideWidget($instance, $widget)) {
            return false;
        }

        if (is_single() && isset($instance['single_post_mode'])) {
            $instance = $this->applySinglePostMode($instance);
        }

        return $instance;
    }
}
```

### ウィジェットタイトルフック

#### #[WidgetTitleFilter]

**WordPress フック:** `widget_title`

```php
use WpPack\Component\Hook\Attribute\Widget\Filter\WidgetTitleFilter;

class WidgetTitleFormatter
{
    #[WidgetTitleFilter]
    public function formatWidgetTitle(string $title, $instance = null, $id_base = null): string
    {
        if ($id_base && $icon = $this->getWidgetIcon($id_base)) {
            $title = sprintf('<i class="%s"></i> %s', $icon, $title);
        }

        return $title;
    }
}
```

### サイドバー登録フック

#### #[RegisterSidebarFilter]

**WordPress フック:** `register_sidebar`

```php
use WpPack\Component\Hook\Attribute\Widget\Filter\RegisterSidebarFilter;

class SidebarRegistrar
{
    #[RegisterSidebarFilter]
    public function onSidebarRegistered(array $sidebar): array
    {
        if ($sidebar['id'] === 'main-sidebar') {
            // additional initialization
        }

        return $sidebar;
    }
}
```
