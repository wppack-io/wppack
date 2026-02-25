# DashboardWidget コンポーネント

**パッケージ:** `wppack/dashboard-widget`
**名前空間:** `WpPack\Component\DashboardWidget\`
**レイヤー:** Application

WordPress のダッシュボードウィジェット機能（`wp_add_dashboard_widget()`）をアトリビュートベースで登録・管理するコンポーネントです。

## インストール

```bash
composer require wppack/dashboard-widget
```

## 従来の WordPress と WpPack の比較

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

### After（WpPack）

```php
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

#[AsDashboardWidget(
    id: 'my_dashboard_widget',
    title: 'My Widget',
    capability: 'edit_posts',
)]
class MyDashboardWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>Total Posts: ' . wp_count_posts()->publish . '</p>';
    }

    public function configure(): void
    {
        // WordPress の configure callback をラップ
        if (isset($_POST['submit'])) {
            update_option('my_widget_settings', sanitize_text_field($_POST['setting']));
        }
        $setting = get_option('my_widget_settings', '');
        echo '<input type="text" name="setting" value="' . esc_attr($setting) . '">';
    }
}
```

## クイックスタート

### 統計ダッシュボードウィジェット

```php
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

#[AsDashboardWidget(
    id: 'site_stats_widget',
    title: 'Site Statistics',
    capability: 'manage_options',
    context: 'normal',
    priority: 'high',
)]
class SiteStatsWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        $postCount = wp_count_posts();
        $commentCount = wp_count_comments();
        $userCount = count_users();

        ?>
        <ul>
            <li><?php printf(__('Published Posts: %d', 'my-plugin'), $postCount->publish); ?></li>
            <li><?php printf(__('Draft Posts: %d', 'my-plugin'), $postCount->draft); ?></li>
            <li><?php printf(__('Approved Comments: %d', 'my-plugin'), $commentCount->approved); ?></li>
            <li><?php printf(__('Total Users: %d', 'my-plugin'), $userCount['total_users']); ?></li>
        </ul>
        <?php
    }
}
```

### 依存性注入を使用したウィジェット

```php
#[AsDashboardWidget(
    id: 'recent_activity_widget',
    title: 'Recent Activity',
    capability: 'edit_posts',
)]
class RecentActivityWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function render(): void
    {
        $recentPosts = get_posts([
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (empty($recentPosts)) {
            echo '<p>' . __('No recent activity.', 'my-plugin') . '</p>';
            return;
        }

        echo '<ul>';
        foreach ($recentPosts as $post) {
            printf(
                '<li><a href="%s">%s</a> — %s</li>',
                esc_url(get_edit_post_link($post->ID)),
                esc_html($post->post_title),
                esc_html(human_time_diff(strtotime($post->post_modified)))
            );
        }
        echo '</ul>';
    }
}
```

### 設定（Configure）コールバック付きウィジェット

```php
#[AsDashboardWidget(
    id: 'customizable_widget',
    title: 'Customizable Widget',
    capability: 'manage_options',
)]
class CustomizableWidget extends AbstractDashboardWidget
{
    public function render(): void
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

        echo '<ul>';
        foreach ($posts as $post) {
            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_permalink($post)),
                esc_html($post->post_title)
            );
        }
        echo '</ul>';
    }

    public function configure(): void
    {
        $options = get_option('customizable_widget_options', [
            'post_count' => 5,
            'post_type' => 'post',
        ]);

        if (isset($_POST['widget_post_count'])) {
            $options['post_count'] = absint($_POST['widget_post_count']);
            $options['post_type'] = sanitize_text_field($_POST['widget_post_type']);
            update_option('customizable_widget_options', $options);
        }

        ?>
        <p>
            <label for="widget_post_count"><?php _e('Number of posts:', 'my-plugin'); ?></label>
            <input type="number" id="widget_post_count" name="widget_post_count"
                   value="<?php echo esc_attr($options['post_count']); ?>" min="1" max="20">
        </p>
        <p>
            <label for="widget_post_type"><?php _e('Post type:', 'my-plugin'); ?></label>
            <select id="widget_post_type" name="widget_post_type">
                <?php foreach (get_post_types(['public' => true], 'objects') as $pt): ?>
                    <option value="<?php echo esc_attr($pt->name); ?>"
                        <?php selected($options['post_type'], $pt->name); ?>>
                        <?php echo esc_html($pt->label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
}
```

## Named Hook アトリビュート

```php
// ダッシュボードセットアップ
#[WpDashboardSetupAction(priority?: int = 10)]           // wp_dashboard_setup — ウィジェット登録
#[WpNetworkDashboardSetupAction(priority?: int = 10)]    // wp_network_dashboard_setup — ネットワークダッシュボード

// ダッシュボードウィジェット
#[DashboardGlanceItemsFilter(priority?: int = 10)]       // dashboard_glance_items — 概要アイテム
#[ActivityBoxEndAction(priority?: int = 10)]              // activity_box_end — アクティビティボックス末尾
```

## ウィジェット登録

```php
add_action('init', function () {
    $container = new WpPack\Container();
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

## 依存関係

### 必須
- **Hook コンポーネント** — フック登録用

### 推奨
- **DependencyInjection コンポーネント** — サービス注入用
