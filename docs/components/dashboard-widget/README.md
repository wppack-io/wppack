# DashboardWidget コンポーネント

**パッケージ:** `wppack/dashboard-widget`
**名前空間:** `WPPack\Component\DashboardWidget\`
**Category:** Admin

WordPress のダッシュボードウィジェット機能（`wp_add_dashboard_widget()`）をアトリビュートベースで登録・管理するコンポーネントです。

## インストール

```bash
composer require wppack/dashboard-widget
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('wp_dashboard_setup', 'add_my_dashboard_widget');

function add_my_dashboard_widget() {
    wp_add_dashboard_widget(
        'my_dashboard_widget',
        'My Widget',
        'my_dashboard_widget_display',
        'my_dashboard_widget_configure'
    );
}

function my_dashboard_widget_display() {
    echo '<p>Total Posts: ' . wp_count_posts()->publish . '</p>';
}

function my_dashboard_widget_configure() {
    if (isset($_POST['submit'])) {
        update_option('my_widget_settings', sanitize_text_field($_POST['setting']));
    }
    $setting = get_option('my_widget_settings', '');
    echo '<input type="text" name="setting" value="' . esc_attr($setting) . '">';
}
```

### After（WPPack）

```php
use WPPack\Component\DashboardWidget\AbstractDashboardWidget;
use WPPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\Attribute\IsGranted;

#[IsGranted('edit_posts')]
#[AsDashboardWidget(
    id: 'my_dashboard_widget',
    label: 'My Widget',
)]
class MyDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>Total Posts: ' . wp_count_posts()->publish . '</p>';
    }

    public function configure(Request $request): string
    {
        if ($request->isMethod('POST')) {
            update_option('my_widget_settings', $request->request->getString('setting'));
        }
        $setting = get_option('my_widget_settings', '');

        return '<input type="text" name="setting" value="' . esc_attr($setting) . '">';
    }
}
```

## クイックスタート

### 統計ダッシュボードウィジェット

```php
use WPPack\Component\DashboardWidget\AbstractDashboardWidget;
use WPPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WPPack\Component\Security\Attribute\IsGranted;

#[IsGranted('manage_options')]
#[AsDashboardWidget(
    id: 'site_stats_widget',
    label: 'Site Statistics',
    context: 'normal',
    priority: 'high',
)]
class SiteStatsWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        $postCount = wp_count_posts();
        $commentCount = wp_count_comments();
        $userCount = count_users();

        return sprintf(
            '<ul>'
            . '<li>%s</li>'
            . '<li>%s</li>'
            . '<li>%s</li>'
            . '<li>%s</li>'
            . '</ul>',
            sprintf(__('Published Posts: %d', 'my-plugin'), $postCount->publish),
            sprintf(__('Draft Posts: %d', 'my-plugin'), $postCount->draft),
            sprintf(__('Approved Comments: %d', 'my-plugin'), $commentCount->approved),
            sprintf(__('Total Users: %d', 'my-plugin'), $userCount['total_users']),
        );
    }
}
```

### 依存性注入を使用したウィジェット

```php
#[IsGranted('edit_posts')]
#[AsDashboardWidget(
    id: 'recent_activity_widget',
    label: 'Recent Activity',
)]
class RecentActivityWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function __invoke(): string
    {
        $recentPosts = get_posts([
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (empty($recentPosts)) {
            return '<p>' . __('No recent activity.', 'my-plugin') . '</p>';
        }

        $html = '<ul>';
        foreach ($recentPosts as $post) {
            $html .= sprintf(
                '<li><a href="%s">%s</a> — %s</li>',
                esc_url(get_edit_post_link($post->ID)),
                esc_html($post->post_title),
                esc_html(human_time_diff(strtotime($post->post_modified)))
            );
        }
        $html .= '</ul>';

        return $html;
    }
}
```

### 設定（Configure）コールバック付きウィジェット

`configure()` メソッドを定義すると、WordPress のダッシュボードウィジェット設定パネルが有効になります。`configure()` は `string` を返します。

```php
use WPPack\Component\HttpFoundation\Request;

#[IsGranted('manage_options')]
#[AsDashboardWidget(
    id: 'customizable_widget',
    label: 'Customizable Widget',
)]
class CustomizableWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        $options = get_option('customizable_widget_options', [
            'post_count' => 5,
            'post_type' => 'post',
        ]);

        $posts = get_posts([
            'post_type' => $options['post_type'],
            'posts_per_page' => $options['post_count'],
            'post_status' => 'publish',
        ]);

        $html = '<ul>';
        foreach ($posts as $post) {
            $html .= sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_permalink($post)),
                esc_html($post->post_title)
            );
        }
        $html .= '</ul>';

        return $html;
    }

    public function configure(Request $request): string
    {
        $options = get_option('customizable_widget_options', [
            'post_count' => 5,
            'post_type' => 'post',
        ]);

        if ($request->isMethod('POST')) {
            $options['post_count'] = absint($request->request->getInt('widget_post_count', 5));
            $options['post_type'] = $request->request->getString('widget_post_type', 'post');
            update_option('customizable_widget_options', $options);
        }

        return sprintf(
            '<p><label for="widget_post_count">%s</label> '
            . '<input type="number" id="widget_post_count" name="widget_post_count" value="%s" min="1" max="20"></p>'
            . '<p><label for="widget_post_type">%s</label> '
            . '<input type="text" id="widget_post_type" name="widget_post_type" value="%s"></p>',
            __('Number of posts:', 'my-plugin'),
            esc_attr((string) $options['post_count']),
            __('Post type:', 'my-plugin'),
            esc_attr($options['post_type']),
        );
    }
}
```

#### Templating 連携で configure を実装

`configure()` でも `render()` ショートカットが使えます。

```php
#[IsGranted('manage_options')]
#[AsDashboardWidget(id: 'templated_config_widget', label: 'Templated Config Widget')]
class TemplatedConfigWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return $this->render('dashboard/widget.html.twig');
    }

    public function configure(Request $request): string
    {
        if ($request->isMethod('POST')) {
            update_option('my_widget_setting', $request->request->getString('setting'));
        }

        return $this->render('dashboard/widget_configure.html.twig', [
            'setting' => get_option('my_widget_setting', ''),
        ]);
    }
}
```

### Templating 連携

`DashboardWidgetRegistry` に `TemplateRendererInterface` を渡すと、登録時に各ウィジェットへ自動注入されます。`render()` ショートカットメソッドで `__invoke()` 内から簡潔にテンプレートを呼び出せます。

```php
use WPPack\Component\DashboardWidget\DashboardWidgetRegistry;
use WPPack\Component\Templating\TemplateRendererInterface;

// Registry に TemplateRendererInterface を渡す
$registry = new DashboardWidgetRegistry($renderer);
$registry->register(new StatsWidget());
```

```php
#[AsDashboardWidget(id: 'stats_widget', label: 'Stats')]
class StatsWidget extends AbstractDashboardWidget
{
    // render() ショートカットを使用
    public function __invoke(): string
    {
        return $this->render('dashboard/stats.html.twig', [
            'post_count' => wp_count_posts()->publish,
        ]);
    }
}
```

DI コンテナで直接 `TemplateRendererInterface` を注入する場合は、コンストラクタインジェクションも引き続き利用できます。

```php
#[AsDashboardWidget(id: 'stats_widget', label: 'Stats')]
class StatsWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly TemplateRendererInterface $renderer,
    ) {}

    public function __invoke(): string
    {
        return $this->renderer->render('dashboard/stats.html.twig', [
            'post_count' => wp_count_posts()->publish,
        ]);
    }
}
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/dashboard-widget.md) を参照してください。

## ウィジェット登録

```php
add_action('init', function () {
    $container = new WPPack\Container();
    $container->register([
        SiteStatsWidget::class,
        RecentActivityWidget::class,
        CustomizableWidget::class,
    ]);
});
```

## このコンポーネントの使用場面

**最適な用途：**
- プラグインの統計情報をダッシュボードに表示
- 管理者向けの概要情報の提供
- 設定可能なダッシュボードウィジェットの作成

**代替を検討すべき場合：**
- ダッシュボードウィジェットが不要な場合
- 複雑な UI が必要な場合（管理画面ページを使用）

## 主要クラス

| クラス | 説明 |
|-------|------|
| `AbstractDashboardWidget` | ダッシュボードウィジェットの基底クラス |
| `Attribute\AsDashboardWidget` | ウィジェット登録アトリビュート |

## プラグイン / テーマでの配置

プラグインやテーマでダッシュボードウィジェットを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── DashboardWidget/
    ├── SiteStatsWidget.php
    ├── RecentActivityWidget.php
    └── QuickActionsWidget.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 推奨
- **DependencyInjection コンポーネント** — サービス注入用
