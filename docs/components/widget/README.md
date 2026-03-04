# Widget コンポーネント

**パッケージ:** `wppack/widget`
**名前空間:** `WpPack\Component\Widget\`
**レイヤー:** Application

WordPress ウィジェット API のアクション・フィルターフックを Named Hook アトリビュートで型安全に登録するためのコンポーネントです。`AbstractWidget` と `#[AsWidget]` アトリビュートによるウィジェット登録も提供します。

## インストール

```bash
composer require wppack/widget
```

## 基本コンセプト

### Before（従来の WordPress）

```php
class My_Widget extends WP_Widget {
    function __construct() {
        parent::__construct('my_widget', 'My Widget');
    }

    function widget($args, $instance) {
        echo $args['before_widget'];
        echo $args['before_title'] . $instance['title'] . $args['after_title'];
        // Manual rendering...
        echo $args['after_widget'];
    }

    function form($instance) {
        // Manual form HTML...
    }

    function update($new_instance, $old_instance) {
        // Manual sanitization...
    }
}

add_action('widgets_init', function() {
    register_widget('My_Widget');
});
```

### After（WpPack）

```php
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;

#[AsWidget(
    id: 'recent_posts',
    name: 'Recent Posts',
    description: 'Display your most recent posts'
)]
class RecentPostsWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        $posts = get_posts(['numberposts' => 5]);

        ob_start();
        echo $args['before_widget'];
        echo $args['before_title'] . 'Recent Posts' . $args['after_title'];

        echo '<ul>';
        foreach ($posts as $post) {
            printf('<li><a href="%s">%s</a></li>', get_permalink($post), esc_html($post->post_title));
        }
        echo '</ul>';

        echo $args['after_widget'];
        return ob_get_clean();
    }
}
```

## コアクラス

### AbstractWidget

`WP_Widget` を拡張する抽象基底クラスです。`#[AsWidget]` アトリビュートからメタデータ（id / name / description）を自動解決し、`parent::__construct()` に渡します。

```php
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;

#[AsWidget(id: 'social_links', name: 'Social Links', description: 'Social media links')]
class SocialLinksWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        return $args['before_widget'] . '<ul class="social-links">...</ul>' . $args['after_widget'];
    }

    // form() と update() はオプション — デフォルト実装あり
    public function form($instance): void
    {
        $title = $instance['title'] ?? '';
        printf('<input type="text" name="%s" value="%s">', $this->get_field_name('title'), esc_attr($title));
    }

    public function update($newInstance, $oldInstance): array
    {
        return ['title' => sanitize_text_field($newInstance['title'] ?? '')];
    }
}
```

`#[AsWidget]` アトリビュートなしでインスタンス化すると `LogicException` がスローされます。

### WidgetRegistry

WordPress のウィジェット・サイドバー登録関数をラップするサービスクラスです。DI コンテナから注入できます。

```php
use WpPack\Component\Widget\WidgetRegistry;

$registry = new WidgetRegistry();

// ウィジェット登録
$registry->register(RecentPostsWidget::class);
$registry->register(SocialLinksWidget::class);

// ウィジェット登録解除
$registry->unregister(SocialLinksWidget::class);

// サイドバー登録
$registry->registerSidebar([
    'name' => 'Main Sidebar',
    'id' => 'main-sidebar',
    'description' => 'The primary widget area',
    'before_widget' => '<section id="%1$s" class="widget %2$s">',
    'after_widget' => '</section>',
    'before_title' => '<h3 class="widget-title">',
    'after_title' => '</h3>',
]);
```

## Named Hook アトリビュート

Widget コンポーネントは、WordPress ウィジェット機能のための Named Hook アトリビュートを提供します。

### ウィジェット登録フック

#### #[WidgetsInitAction]

**WordPress フック:** `widgets_init`

```php
use WpPack\Component\Widget\Attribute\Action\WidgetsInitAction;
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
use WpPack\Component\Widget\Attribute\Action\DynamicSidebarBeforeAction;

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
use WpPack\Component\Widget\Attribute\Action\DynamicSidebarAfterAction;

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
use WpPack\Component\Widget\Attribute\Filter\DynamicSidebarParamsFilter;

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
use WpPack\Component\Widget\Attribute\Filter\WidgetUpdateCallbackFilter;

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
use WpPack\Component\Widget\Attribute\Filter\WidgetFormCallbackFilter;

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
use WpPack\Component\Widget\Attribute\Filter\WidgetDisplayCallbackFilter;

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
use WpPack\Component\Widget\Attribute\Filter\WidgetTitleFilter;

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
use WpPack\Component\Widget\Attribute\Filter\RegisterSidebarFilter;

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

## Hook アトリビュートリファレンス

```php
// ウィジェット登録
#[WidgetsInitAction(priority?: int = 10)]            // ウィジェットとサイドバーの登録
#[RegisterSidebarFilter(priority?: int = 10)]         // サイドバー登録後のフィルター

// ウィジェット表示
#[DynamicSidebarBeforeAction(priority?: int = 10)]   // サイドバー表示前
#[DynamicSidebarAfterAction(priority?: int = 10)]    // サイドバー表示後
#[DynamicSidebarParamsFilter(priority?: int = 10)]   // ウィジェット表示パラメータの変更

// ウィジェット更新
#[WidgetUpdateCallbackFilter(priority?: int = 10)]   // 保存時のウィジェット設定フィルター
#[WidgetFormCallbackFilter(priority?: int = 10)]     // ウィジェットフォームの変更
#[WidgetDisplayCallbackFilter(priority?: int = 10)]  // ウィジェット表示の制御

// ウィジェットコンテンツ
#[WidgetTitleFilter(priority?: int = 10)]            // ウィジェットタイトルのフィルター
#[WidgetTextFilter(priority?: int = 10)]             // テキストウィジェットコンテンツのフィルター
#[WidgetContentFilter(priority?: int = 10)]          // カスタム HTML ウィジェットコンテンツのフィルター

// その他
#[DynamicSidebarHasWidgetsFilter(priority?: int = 10)] // サイドバーのウィジェット有無
#[WidgetAreaPreviewFilter(priority?: int = 10)]        // ウィジェットエリアプレビュー
#[WidgetsPrefetchingFilter(priority?: int = 10)]       // ウィジェットプリフェッチ
```

## クラスリファレンス

| クラス | 説明 |
|-------|------|
| `AbstractWidget` | `WP_Widget` 抽象ラッパー。`#[AsWidget]` からメタデータ自動解決 |
| `WidgetRegistry` | ウィジェット/サイドバー登録サービス |
| `Attribute\AsWidget` | クラスレベルアトリビュート（id / name / description） |

## WordPress 統合

- ウィジェットは通常通り **外観 > ウィジェット** に表示されます
- **ブロックエディタのウィジェットエリア** と互換性があります
- すべての **ウィジェット対応テーマ** で動作します
- **WordPress ウィジェット API** との互換性を維持します

## 依存関係

### 必須
- **Hook コンポーネント** — WordPress ウィジェット登録用

### 推奨
- **Cache コンポーネント** — パフォーマンス最適化用
- **Security コンポーネント** — フォームフィールドのサニタイズ用
- **Option コンポーネント** — 拡張設定ストレージ用
