# Widget コンポーネント

**パッケージ:** `wppack/widget`
**名前空間:** `WpPack\Component\Widget\`
**レイヤー:** Application

アトリビュートベースの設定、自動フォーム生成、強化されたウィジェット管理機能を備えた、WordPress ウィジェット開発のためのモダンなオブジェクト指向フレームワーク。

## インストール

```bash
composer require wppack/widget
```

## 基本コンセプト

### 従来の WordPress コード

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

### WpPack コード

```php
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\Widget;
use WpPack\Component\Widget\Attribute\WidgetField;

#[Widget(
    id: 'recent_posts',
    name: 'Recent Posts',
    description: 'Display your most recent posts'
)]
class RecentPostsWidget extends AbstractWidget
{
    #[WidgetField(type: 'text', label: 'Title', default: 'Recent Posts')]
    protected string $title;

    #[WidgetField(type: 'number', label: 'Number of posts', default: 5)]
    protected int $count;

    public function render(array $args): string
    {
        $posts = get_posts(['numberposts' => $this->count]);

        return $this->renderTemplate('recent-posts', [
            'title' => $this->title,
            'posts' => $posts,
            'args' => $args
        ]);
    }
}
```

## 機能

- **型安全なウィジェットプロパティ** - PHP 8 アトリビュートによる定義
- **自動フォーム生成** - プロパティ定義からの自動生成
- **依存性注入** - サービスやリポジトリの注入
- **組み込みキャッシュ** - パフォーマンス最適化
- **テンプレートサポート** - 複雑なウィジェット出力に対応
- **表示制御** - 条件付き表示
- **ブロックエディタウィジェットエリア** - 互換性対応

## クイックスタート

### 最初のウィジェット

選択した投稿をカスタマイズ可能なオプション付きで表示する「Featured Post」ウィジェットを作成しましょう：

```php
<?php
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\Widget;
use WpPack\Component\Widget\Attribute\WidgetField;

#[Widget(
    id: 'featured_post',
    name: 'Featured Post',
    description: 'Display a featured post with custom styling'
)]
class FeaturedPostWidget extends AbstractWidget
{
    #[WidgetField(
        type: 'text',
        label: 'Widget Title',
        default: 'Featured Post'
    )]
    protected string $title;

    #[WidgetField(
        type: 'select',
        label: 'Select Post',
        options: 'getPostOptions'
    )]
    protected int $postId;

    #[WidgetField(
        type: 'checkbox',
        label: 'Show Featured Image',
        default: true
    )]
    protected bool $showImage;

    #[WidgetField(
        type: 'checkbox',
        label: 'Show Excerpt',
        default: false
    )]
    protected bool $showExcerpt;

    public function render(array $args): string
    {
        if (!$this->postId) {
            return '';
        }

        $post = get_post($this->postId);
        if (!$post) {
            return '';
        }

        ob_start();
        ?>
        <?php echo $args['before_widget']; ?>

        <?php if ($this->title): ?>
            <?php echo $args['before_title'] . esc_html($this->title) . $args['after_title']; ?>
        <?php endif; ?>

        <div class="featured-post-widget">
            <?php if ($this->showImage && has_post_thumbnail($post)): ?>
                <div class="featured-post-image">
                    <?php echo get_the_post_thumbnail($post, 'medium'); ?>
                </div>
            <?php endif; ?>

            <h3 class="featured-post-title">
                <a href="<?php echo get_permalink($post); ?>">
                    <?php echo esc_html($post->post_title); ?>
                </a>
            </h3>

            <?php if ($this->showExcerpt): ?>
                <div class="featured-post-excerpt">
                    <?php echo wp_trim_words($post->post_content, 20); ?>
                </div>
            <?php endif; ?>

            <a href="<?php echo get_permalink($post); ?>" class="featured-post-link">
                Read More
            </a>
        </div>

        <?php echo $args['after_widget']; ?>
        <?php

        return ob_get_clean();
    }

    protected function getPostOptions(): array
    {
        $posts = get_posts([
            'numberposts' => 20,
            'post_status' => 'publish'
        ]);

        $options = [];
        foreach ($posts as $post) {
            $options[$post->ID] = $post->post_title;
        }

        return $options;
    }
}
```

### ウィジェットの登録

```php
<?php
add_action('widgets_init', function() {
    $container = new WpPack\Container();
    $container->register(FeaturedPostWidget::class);
});
```

### キャッシュの追加

```php
use WpPack\Component\Widget\Attribute\Cache;

#[Widget(id: 'featured_post', name: 'Featured Post')]
#[Cache(duration: 3600)] // 1時間キャッシュ
class FeaturedPostWidget extends AbstractWidget
{
    // Your widget code...
}
```

### テンプレートの使用

```php
public function render(array $args): string
{
    return $this->renderTemplate('featured-post', [
        'post' => get_post($this->postId),
        'title' => $this->title,
        'showImage' => $this->showImage,
        'args' => $args
    ]);
}
```

## フィールドタイプ

WpPack ウィジェットで利用可能なすべてのフィールドタイプの完全ガイドです。各フィールドタイプは適切なフォーム入力を自動生成し、データバリデーションを処理します。

### 基本入力フィールド

#### Text フィールド

```php
#[WidgetField(
    type: 'text',
    label: 'Widget Title',
    default: 'My Widget',
    placeholder: 'Enter title...'
)]
protected string $title;
```

**オプション:** `placeholder`, `maxlength`

#### Textarea フィールド

```php
#[WidgetField(
    type: 'textarea',
    label: 'Description',
    rows: 4,
    default: 'Enter description...'
)]
protected string $description;
```

**オプション:** `rows`, `cols`

#### Number フィールド

```php
#[WidgetField(
    type: 'number',
    label: 'Number of Items',
    min: 1,
    max: 20,
    step: 1,
    default: 5
)]
protected int $count;
```

**オプション:** `min`, `max`, `step`

#### Email フィールド

```php
#[WidgetField(
    type: 'email',
    label: 'Contact Email',
    placeholder: 'admin@example.com'
)]
protected string $email;
```

#### URL フィールド

```php
#[WidgetField(
    type: 'url',
    label: 'Website URL',
    placeholder: 'https://example.com'
)]
protected string $website;
```

#### Password フィールド

```php
#[WidgetField(
    type: 'password',
    label: 'API Secret Key'
)]
protected string $apiSecret;
```

### 選択フィールド

#### チェックボックス

```php
#[WidgetField(
    type: 'checkbox',
    label: 'Show Featured Image',
    default: true
)]
protected bool $showImage;
```

#### セレクトドロップダウン

```php
#[WidgetField(
    type: 'select',
    label: 'Post Order',
    options: [
        'date' => 'Date Published',
        'title' => 'Title',
        'menu_order' => 'Menu Order',
        'rand' => 'Random'
    ],
    default: 'date'
)]
protected string $orderBy;
```

**動的オプション:**

```php
#[WidgetField(
    type: 'select',
    label: 'Category',
    options: 'getCategoryOptions'
)]
protected int $categoryId;

protected function getCategoryOptions(): array
{
    $categories = get_categories();
    $options = [];

    foreach ($categories as $category) {
        $options[$category->term_id] = $category->name;
    }

    return $options;
}
```

#### マルチセレクト

```php
#[WidgetField(
    type: 'multiselect',
    label: 'Post Types',
    options: [
        'post' => 'Posts',
        'page' => 'Pages',
        'product' => 'Products'
    ]
)]
protected array $postTypes = ['post'];
```

#### ラジオボタン

```php
#[WidgetField(
    type: 'radio',
    label: 'Layout Style',
    options: [
        'list' => 'List View',
        'grid' => 'Grid View',
        'carousel' => 'Carousel'
    ],
    default: 'list'
)]
protected string $layout;
```

### 日付と時間フィールド

```php
#[WidgetField(type: 'date', label: 'Event Date')]
protected string $eventDate;

#[WidgetField(type: 'time', label: 'Event Time')]
protected string $eventTime;

#[WidgetField(type: 'datetime', label: 'Event Start')]
protected string $eventStart;
```

### メディアとカラーフィールド

#### カラーピッカー

```php
#[WidgetField(
    type: 'color',
    label: 'Background Color',
    default: '#ffffff'
)]
protected string $backgroundColor;
```

#### メディアアップロード

```php
#[WidgetField(
    type: 'media',
    label: 'Featured Image',
    mediaType: 'image',
    buttonText: 'Select Image'
)]
protected int $featuredImageId;
```

**オプション:** `mediaType` ('image', 'video', 'audio', 'file'), `buttonText`

#### 画像アップロード

```php
#[WidgetField(
    type: 'image',
    label: 'Logo',
    previewSize: 'thumbnail'
)]
protected int $logoId;
```

### 条件付きフィールド

他のフィールドの値に基づいてフィールドの表示/非表示を制御します：

```php
#[WidgetField(type: 'checkbox', label: 'Enable Custom Styling')]
protected bool $enableStyling = false;

#[WidgetField(type: 'color', label: 'Custom Color')]
protected string $customColor = '#333333';

#[WidgetField(
    type: 'select',
    label: 'Display Mode',
    options: ['simple' => 'Simple', 'advanced' => 'Advanced']
)]
protected string $displayMode = 'simple';

#[WidgetField(type: 'number', label: 'Max Items', min: 1, max: 50)]
protected int $maxItems = 10;
```

### フィールドバリデーション

```php
use WpPack\Component\Widget\Attribute\Validate;

#[WidgetField(type: 'text', label: 'Username')]
#[Validate('required|alphanumeric|min:3|max:20')]
protected string $username;

#[WidgetField(type: 'email', label: 'Email')]
#[Validate('required|email')]
protected string $email;
```

### フィールドスタイリング

```php
#[WidgetField(
    type: 'text',
    label: 'Title',
    cssClass: 'large-text',
    description: 'This will be displayed as the widget title',
    helpText: 'Leave empty to hide the title section'
)]
protected string $title;
```

### カスタムフィールドタイプ

```php
use WpPack\Component\Widget\Fields\AbstractField;

class ColorSchemeField extends AbstractField
{
    public function render(array $args): string
    {
        return '<div class="color-scheme-picker">...</div>';
    }

    public function sanitize($value): array
    {
        return $this->sanitizeColorScheme($value);
    }
}

// Use in widget
#[WidgetField(type: ColorSchemeField::class, label: 'Color Scheme')]
protected array $colorScheme;
```

## Named Hook アトリビュート

Widget コンポーネントは、WordPress ウィジェット機能のための Named Hook アトリビュートを提供します。

### ウィジェット登録フック

#### #[WidgetsInitAction]

**WordPress フック:** `widgets_init`

```php
use WpPack\Component\Widget\Attribute\WidgetsInitAction;
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
use WpPack\Component\Widget\Attribute\DynamicSidebarBeforeAction;

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
use WpPack\Component\Widget\Attribute\DynamicSidebarAfterAction;

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
use WpPack\Component\Widget\Attribute\DynamicSidebarParamsFilter;

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
use WpPack\Component\Widget\Attribute\WidgetUpdateCallbackFilter;

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
use WpPack\Component\Widget\Attribute\WidgetFormCallbackFilter;

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
use WpPack\Component\Widget\Attribute\WidgetDisplayCallbackFilter;

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
use WpPack\Component\Widget\Attribute\WidgetTitleFilter;

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

#### #[RegisterSidebarAction]

**WordPress フック:** `register_sidebar`

```php
use WpPack\Component\Widget\Attribute\RegisterSidebarAction;

class SidebarRegistrar
{
    #[RegisterSidebarAction]
    public function onSidebarRegistered(array $sidebar): void
    {
        // サイドバー登録後の処理
        if ($sidebar['id'] === 'main-sidebar') {
            // 追加の初期化処理
        }
    }
}
```

## Hook アトリビュートリファレンス

```php
// ウィジェット登録
#[WidgetsInitAction(priority?: int = 10)]            // ウィジェットとサイドバーの登録
#[RegisterSidebarAction(priority?: int = 10)]        // サイドバー登録後のアクション

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

// ウィジェット管理
```

## このコンポーネントの使用場面

**最適な用途：**
- 再利用可能なコンテンツブロックの作成
- 管理画面から設定可能な表示コンポーネントの構築
- テーマに依存しない機能の開発
- ブロックエディタとの互換性の追加

**代替を検討すべき場合：**
- 単純な静的コンテンツ（代わりにブロックを使用）
- 管理画面専用の機能（管理コンポーネントを使用）
- 複雑なインタラクティブ機能（カスタムソリューションが必要な場合）

## WordPress 統合

- ウィジェットは通常通り **外観 > ウィジェット** に表示されます
- **ブロックエディタのウィジェットエリア** と互換性があります
- すべての **ウィジェット対応テーマ** で動作します
- **WordPress ウィジェット API** との互換性を維持します

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress ウィジェット登録用

### 推奨
- **Cache コンポーネント** - パフォーマンス最適化用
- **Security コンポーネント** - フォームフィールドのサニタイズ用
- **Option コンポーネント** - 拡張設定ストレージ用
