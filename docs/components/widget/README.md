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

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Widget](../hook/widget.md) を参照してください。

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

## プラグイン / テーマでの配置

プラグインやテーマでウィジェットクラスを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Widget/
    ├── RecentPostsWidget.php
    ├── SocialLinksWidget.php
    └── NewsletterWidget.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 推奨
- **Cache コンポーネント** — パフォーマンス最適化用
- **Security コンポーネント** — フォームフィールドのサニタイズ用
- **Option コンポーネント** — 拡張設定ストレージ用
