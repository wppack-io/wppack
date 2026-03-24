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
    label: 'Recent Posts',
    description: 'Display your most recent posts'
)]
class RecentPostsWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        $posts = get_posts(['numberposts' => 5]);

        $html = $args['before_widget'];
        $html .= $args['before_title'] . 'Recent Posts' . $args['after_title'];

        $html .= '<ul>';
        foreach ($posts as $post) {
            $html .= sprintf('<li><a href="%s">%s</a></li>', get_permalink($post), esc_html($post->post_title));
        }
        $html .= '</ul>';

        $html .= $args['after_widget'];
        return $html;
    }
}
```

## コアクラス

### AbstractWidget

`WP_Widget` を拡張する抽象基底クラスです。`#[AsWidget]` アトリビュートからメタデータ（id / label / description）を自動解決し、`parent::__construct()` に渡します。

サブクラスは `__invoke(array $args, array $instance): string` を実装してウィジェット出力を返します。`widget()` メソッドが WordPress コールバックとして `__invoke()` を呼び出し、戻り値を echo します。

```php
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;

#[AsWidget(id: 'social_links', label: 'Social Links', description: 'Social media links')]
class SocialLinksWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return $args['before_widget'] . '<ul class="social-links">...</ul>' . $args['after_widget'];
    }

    // update() はオプション — デフォルト実装あり
    public function update($newInstance, $oldInstance): array
    {
        return ['title' => sanitize_text_field($newInstance['title'] ?? '')];
    }
}
```

`#[AsWidget]` アトリビュートなしでインスタンス化すると `LogicException` がスローされます。

### configure() パターン（フォーム設定）

サブクラスでオプショナルな `configure(array $instance): string` メソッドを定義すると、`form()` がその戻り値を自動的に echo します。`configure()` を定義しない場合、`form()` は何も出力しません。

```php
#[AsWidget(id: 'social_links', label: 'Social Links', description: 'Social media links')]
class SocialLinksWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return $args['before_widget'] . '<ul class="social-links">...</ul>' . $args['after_widget'];
    }

    // オプショナル — 定義すると form() が自動的に echo する
    public function configure(array $instance): string
    {
        $title = $instance['title'] ?? '';
        return sprintf('<input type="text" name="%s" value="%s">', $this->get_field_name('title'), esc_attr($title));
    }
}
```

### Templating サポート

`wppack/templating` をインストールすると、`render()` メソッドでテンプレートエンジンに委譲できます。`__invoke()` と `configure()` の両方で利用可能です。

```php
#[AsWidget(id: 'recent_posts', label: 'Recent Posts', description: 'Display recent posts')]
class RecentPostsWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        $posts = get_posts(['numberposts' => 5]);
        return $this->render('widget/recent-posts.html.twig', [
            'args' => $args,
            'posts' => $posts,
        ]);
    }

    public function configure(array $instance): string
    {
        return $this->render('widget/recent-posts-form.html.twig', [
            'instance' => $instance,
        ]);
    }
}
```

`TemplateRendererInterface` が設定されていない状態で `render()` を呼ぶと `LogicException` がスローされます。

### DI パラメータ注入

`__invoke()` と `configure()` の両方で、`Request` や `#[CurrentUser]` による DI パラメータ注入が利用可能です。`array` 型のパラメータ（`$args`, `$instance`）はスキップされるため衝突しません。

```php
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Attribute\CurrentUser;

#[AsWidget(id: 'user_greeting', label: 'User Greeting')]
class UserGreetingWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance, Request $request, #[CurrentUser] \WP_User $user): string
    {
        return sprintf('<p>Hello, %s!</p>', esc_html($user->display_name));
    }

    public function configure(array $instance, Request $request): string
    {
        return '<input type="text" name="greeting">';
    }
}
```

### WidgetRegistry

WordPress のウィジェット・サイドバー登録関数をラップするサービスクラスです。DI コンテナから注入できます。`register()` は `AbstractWidget` インスタンスを受け取り、`TemplateRendererInterface` と `ArgumentResolver` によるパラメータリゾルバを自動注入します。

```php
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\RequestValueResolver;
use WpPack\Component\Security\ValueResolver\CurrentUserValueResolver;
use WpPack\Component\Templating\TemplateRendererInterface;
use WpPack\Component\Widget\WidgetRegistry;

$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
    new CurrentUserValueResolver($security),
]);

$registry = new WidgetRegistry(
    renderer: $templateRenderer,      // optional
    argumentResolver: $argumentResolver,  // optional
);

// ウィジェット登録（インスタンス）
$registry->register(new RecentPostsWidget());
$registry->register(new SocialLinksWidget());

// ウィジェット登録解除（クラス名）
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
| `AbstractWidget` | `WP_Widget` 抽象ラッパー。`__invoke()` でウィジェット出力、`configure()` でフォーム設定、`render()` で Templating 委譲 |
| `WidgetRegistry` | ウィジェット/サイドバー登録サービス。Templating・DI パラメータ注入対応 |
| `Attribute\AsWidget` | クラスレベルアトリビュート（id / label / description） |

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

### 推奨（suggest）
- **wppack/http-foundation** — `__invoke()` / `configure()` での Request パラメータ注入
- **wppack/templating** — `render()` によるテンプレートレンダリング
- **wppack/security** — `#[CurrentUser]` パラメータ注入

### その他
- **Cache コンポーネント** — パフォーマンス最適化用
- **Option コンポーネント** — 拡張設定ストレージ用