# Hook Component

**パッケージ:** `wppack/hook`
**名前空間:** `WpPack\Component\Hook\`
**レイヤー:** Infrastructure

アトリビュートベースのWordPressフック（アクション/フィルター）管理コンポーネント。PHP 8アトリビュートを使用して、型安全で宣言的なフック登録を実現します。自動ディスカバリ、条件付き登録、一般的なWordPressフック用の名前付きフックアトリビュートを提供します。

## インストール

```bash
composer require wppack/hook
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('init', 'my_init_function', 10);
add_filter('the_content', 'my_content_filter', 20);
add_action('wp_enqueue_scripts', 'my_enqueue_function');

function my_init_function() {
    register_post_type('custom', [...]);
}

function my_content_filter($content) {
    return $content . '<p>Additional content</p>';
}

function my_enqueue_function() {
    wp_enqueue_style('theme-style', get_stylesheet_uri());
}
```

### After（WpPack）

```php
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Templating\Attribute\Filter\TheContentFilter;
use WpPack\Component\Theme\Attribute\Action\WpEnqueueScriptsAction;

final class ThemeManager
{
    #[InitAction]
    public function initialize(): void
    {
        register_post_type('custom', [...]);
    }

    #[TheContentFilter(priority: 20)]
    public function enhanceContent(string $content): string
    {
        return $content . '<p>Additional content</p>';
    }

    #[WpEnqueueScriptsAction]
    public function enqueueAssets(): void
    {
        wp_enqueue_style('theme-style', get_stylesheet_uri());
    }
}
```

## アトリビュート

### 汎用アトリビュート

#### `#[Action]`

WordPressアクションフックを登録します。

```php
use WpPack\Component\Hook\Attribute\Action;

#[Action(hook: 'wp_loaded', priority: 20)]
public function onWpLoaded(): void
{
    // ...
}
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|---------|------|
| `hook` | `string` | （必須） | フック名 |
| `priority` | `int` | `10` | 実行優先度 |

#### `#[Filter]`

WordPressフィルターフックを登録します。戻り値が必要です。

```php
use WpPack\Component\Hook\Attribute\Filter;

#[Filter(hook: 'the_title', priority: 10)]
public function filterTitle(string $title, int $postId): string
{
    return strtoupper($title);
}
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|---------|------|
| `hook` | `string` | （必須） | フック名 |
| `priority` | `int` | `10` | 実行優先度 |

### 名前付きフックアトリビュート

名前付きフックアトリビュートにより、フック名を文字列で指定する必要がなくなり、型安全性とIDEの自動補完を提供します。

#### Hook コンポーネント所有のアクション（ライフサイクル）

```php
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\Action\AdminInitAction;
use WpPack\Component\Hook\Attribute\Action\PluginsLoadedAction;
use WpPack\Component\Hook\Attribute\Action\AfterSetupThemeAction;
use WpPack\Component\Hook\Attribute\Action\WpLoadedAction;
```

すべてのアクションアトリビュートは共通パラメータ `priority?: int = 10` を持ちます。

| アトリビュート | フック名 | 追加パラメータ |
|--------------|---------|--------------|
| `#[InitAction]` | `init` | — |
| `#[AdminInitAction]` | `admin_init` | — |
| `#[PluginsLoadedAction]` | `plugins_loaded` | — |
| `#[AfterSetupThemeAction]` | `after_setup_theme` | — |
| `#[WpLoadedAction]` | `wp_loaded` | — |

```php
#[InitAction]
public function onInit(): void { /* ... */ }

#[AdminInitAction(priority: 5)]
public function onAdminInit(): void { /* ... */ }

#[PluginsLoadedAction]
public function onPluginsLoaded(): void { /* ... */ }
```

#### 各コンポーネント所有の Named Hook の使用例

ドメイン固有のフックは各コンポーネントが所有します。`Action` / `Filter` を継承しているため、`IS_INSTANCEOF` により自動検出されます。

```php
use WpPack\Component\PostType\Attribute\Action\SavePostAction;
use WpPack\Component\Admin\Attribute\Action\AdminMenuAction;
use WpPack\Component\Theme\Attribute\Action\WpEnqueueScriptsAction;
use WpPack\Component\Query\Attribute\Action\PreGetPostsAction;

#[AdminMenuAction]
public function addAdminMenus(): void
{
    add_menu_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', [$this, 'render']);
}

#[SavePostAction]
public function onSavePost(int $postId, \WP_Post $post, bool $update): void
{
    if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
        return;
    }
    $this->clearCache($postId);
}

#[PreGetPostsAction]
public function modifyMainQuery(\WP_Query $query): void
{
    if ($query->is_main_query() && !is_admin()) {
        $query->set('posts_per_page', 20);
    }
}

#[WpEnqueueScriptsAction]
public function enqueueAssets(): void
{
    wp_enqueue_style('theme-style', get_stylesheet_uri());
}
```

#### フィルターアトリビュートの使用例

```php
use WpPack\Component\Templating\Attribute\Filter\TheContentFilter;
use WpPack\Component\Theme\Attribute\Filter\BodyClassFilter;
use WpPack\Component\Query\Attribute\Filter\PostsWhereFilter;
```

すべてのフィルターアトリビュートは共通パラメータ `priority?: int = 10` を持ちます。

```php
#[TheContentFilter]
public function filterContent(string $content): string
{
    if (!is_singular()) {
        return $content;
    }

    $readingTime = ceil(str_word_count(strip_tags($content)) / 200);
    return "<div class=\"reading-time\">{$readingTime} min read</div>" . $content;
}

#[BodyClassFilter]
public function addBodyClass(array $classes): array
{
    $classes[] = 'my-custom-class';
    return $classes;
}

#[PostsWhereFilter]
public function filterPostsWhere(string $where, \WP_Query $query): string
{
    if ($query->get('featured_only')) {
        global $wpdb;
        $where .= " AND {$wpdb->posts}.ID IN (
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'featured' AND meta_value = '1'
        )";
    }
    return $where;
}
```

#### AJAX フックアトリビュート

```php
use WpPack\Component\Hook\Attribute\Action\WpAjaxAction;
use WpPack\Component\Hook\Attribute\Action\WpAjaxNoprivAction;
```

| アトリビュート | フック名 | パラメータ |
|--------------|---------|----------|
| `#[WpAjaxAction]` | `wp_ajax_{action}` | `action: string`, `priority?: int = 10` |
| `#[WpAjaxNoprivAction]` | `wp_ajax_nopriv_{action}` | `action: string`, `priority?: int = 10` |

```php
use WpPack\Component\Hook\Attribute\Action\WpAjaxAction;
use WpPack\Component\Hook\Attribute\Action\WpAjaxNoprivAction;
use WpPack\Component\HttpFoundation\Request;

class AjaxHandler
{
    #[WpAjaxAction('load_more_posts')]
    public function handleLoadMore(Request $request): void
    {
        check_ajax_referer('load_more_nonce', 'nonce');

        $page = $request->request->getInt('page', 1);
        $query = new \WP_Query([
            'post_type' => 'post',
            'posts_per_page' => 10,
            'paged' => $page,
        ]);

        $items = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $items[] = [
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'permalink' => get_permalink(),
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success([
            'items' => $items,
            'has_more' => $page < $query->max_num_pages,
        ]);
    }

    #[WpAjaxNoprivAction('submit_contact')]
    public function handleContact(Request $request): void
    {
        check_ajax_referer('contact_nonce', 'nonce');

        $name = sanitize_text_field($request->request->get('name', ''));
        $email = sanitize_email($request->request->get('email', ''));
        $message = sanitize_textarea_field($request->request->get('message', ''));

        wp_mail(get_option('admin_email'), 'Contact Form', "{$name}\n{$email}\n\n{$message}");
        wp_send_json_success(['message' => 'Thank you!']);
    }
}
```

## 自動ディスカバリ

`HookDiscovery` はクラスをスキャンしてフックアトリビュートを検出し、自動的に登録します。

```php
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;

$registry = new HookRegistry();
$discovery = new HookDiscovery($registry);

// フックサブスクライバーを登録
$discovery->register(new ContentHooks());
$discovery->register(new AdminHooks());

// 検出されたすべてのフックをWordPressにバインド
$registry->bind();
```

### DIコンテナ連携

```php
use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\PostType\Attribute\Action\SavePostAction;

#[AsHookSubscriber]
final class ContentHooks
{
    public function __construct(
        private readonly PostRepository $postRepository,
    ) {}

    #[SavePostAction]
    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        $this->postRepository->invalidateCache($postId);
    }
}
```

`#[AsHookSubscriber]` を付与したクラスは、DIコンテナによって自動的に検出・登録されます。

## 単一メソッドへの複数フック適用

同じメソッドに複数のフックを適用できます。

```php
use WpPack\Component\PostType\Attribute\Action\SavePostAction;
use WpPack\Component\PostType\Attribute\Action\DeletePostAction;

class CacheManager
{
    #[SavePostAction]
    #[DeletePostAction]
    public function clearPostCache(int $postId): void
    {
        wp_cache_delete($postId, 'posts');
        delete_transient("post_data_{$postId}");
    }
}
```

## カスタムフックアトリビュート

プラグイン用の専用フックアトリビュートを作成できます。

```php
use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WooCommerceAction extends Action
{
    public function __construct(
        public readonly string $action,
        int $priority = 10,
    ) {
        parent::__construct("woocommerce_{$action}", $priority);
    }
}

// 使用例
class WooCommerceIntegration
{
    #[WooCommerceAction('product_options_general_product_data')]
    public function addCustomProductFields(): void
    {
        woocommerce_wp_text_input([
            'id' => 'custom_field',
            'label' => 'Custom Field',
        ]);
    }
}
```

## フックの優先度と実行順序

```php
use WpPack\Component\PostType\Attribute\Action\SavePostAction;

class OrderedHooks
{
    #[SavePostAction(priority: 5)]
    public function validatePost(int $postId): void
    {
        // バリデーションのために早い段階で実行
    }

    #[SavePostAction(priority: 15)]
    public function processPost(int $postId): void
    {
        // バリデーション後に実行
    }

    #[SavePostAction(priority: 25)]
    public function notifyServices(int $postId): void
    {
        // 通知のために最後に実行
    }
}
```

## HookRegistry

`HookRegistry` はフック登録の状態を管理し、テストを容易にします。

```php
use WpPack\Component\Hook\HookRegistry;

$registry = new HookRegistry();

// 手動登録
$registry->addAction('init', $callable, priority: 10);
$registry->addFilter('the_content', $callable, priority: 10);

// すべてのフックをWordPressにバインド
$registry->bind();

// 登録済みフックの確認
$actions = $registry->getActions();
$filters = $registry->getFilters();
```

## パフォーマンス機能

### コンパイル済みフック登録

フックはコンテナビルド時にディスカバリされ、本番環境用にコンパイルされます。

```php
// 開発環境: 動的フックディスカバリ
$hookManager->discoverHooks(); // クラスをスキャンしてフックアトリビュートを検出

// 本番環境: コンパイル済みフック登録
$hookManager->registerCompiledHooks(); // キャッシュされたフック定義を使用
```

### 遅延フック読み込み

フックハンドラーはフックが発火した時にのみインスタンス化されます。

```php
use WpPack\Component\PostType\Attribute\Action\SavePostAction;

#[AsHookSubscriber]
final class ExpensiveHookHandler
{
    #[SavePostAction(postType: 'product')]
    public function handleProductSave(int $postId): void
    {
        // product が保存された時のみサービスがインスタンス化される
        $this->performExpensiveOperation($postId);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Attribute\Action` | アクションフック登録アトリビュート（基底クラス） |
| `Attribute\Filter` | フィルターフック登録アトリビュート（基底クラス） |
| `Attribute\AsHookSubscriber` | フックサブスクライバー自動検出マーカー |
| `Attribute\Action\InitAction` | `init` アクションアトリビュート |
| `Attribute\Action\AdminInitAction` | `admin_init` アクションアトリビュート |
| `Attribute\Action\PluginsLoadedAction` | `plugins_loaded` アクションアトリビュート |
| `Attribute\Action\AfterSetupThemeAction` | `after_setup_theme` アクションアトリビュート |
| `Attribute\Action\WpLoadedAction` | `wp_loaded` アクションアトリビュート |
| `Attribute\Action\WpAjaxAction` | `wp_ajax_{action}` アクションアトリビュート |
| `Attribute\Action\WpAjaxNoprivAction` | `wp_ajax_nopriv_{action}` アクションアトリビュート |
| `Attribute\Condition\ConditionInterface` | フック登録条件の契約 |
| `HookRegistry` | フック登録管理 |
| `HookDiscovery` | アトリビュートベースのフック自動ディスカバリ |
