# Admin コンポーネント

**パッケージ:** `wppack/admin`
**名前空間:** `WpPack\Component\Admin\`
**レイヤー:** Feature

Admin コンポーネントは、アトリビュートベースの設定、型安全なフォーム、包括的な管理機能を備えた、WordPress 管理画面インターフェースをモダンなオブジェクト指向で構築するためのコンポーネントです。

## インストール

```bash
composer require wppack/admin
```

## このコンポーネントの機能

- **アトリビュートベースの管理ページ登録** - 宣言的な設定
- **型安全なフォーム構築** - 自動バリデーションとサニタイズ
- **高度な管理インターフェース** - リストテーブル、ダッシュボードウィジェット、通知
- **AJAX ハンドラー** - セキュリティと nonce 検証を内蔵
- **メディア統合** - WordPress メディアライブラリとの連携
- **スクリーンオプションとヘルプタブ** - ユーザー体験の向上
- **管理画面アセット管理** - 条件付き読み込み

## 従来の WordPress vs WpPack

### Before（従来の WordPress）

```php
// functions.php に散らばった管理フック
function my_admin_menu() {
    add_menu_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', 'my_plugin_page');
}
add_action('admin_menu', 'my_admin_menu');

function my_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_my-plugin') return;
    wp_enqueue_script('my-script', plugin_dir_url(__FILE__) . 'script.js');
}
add_action('admin_enqueue_scripts', 'my_admin_scripts');

function my_admin_notice() {
    ?>
    <div class="notice notice-info">
        <p>My notice message</p>
    </div>
    <?php
}
add_action('admin_notices', 'my_admin_notice');
```

### After（WpPack）

```php
use WpPack\Component\Admin\Attribute\AdminMenuAction;
use WpPack\Component\Admin\Attribute\AdminEnqueueScriptsAction;
use WpPack\Component\Admin\Attribute\AdminNoticesAction;

class MyPluginAdmin
{
    #[AdminMenuAction]
    public function registerMenu(): void
    {
        add_menu_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', [$this, 'renderPage']);
    }

    #[AdminEnqueueScriptsAction]
    public function enqueueScripts(string $hook): void
    {
        if ($hook !== 'toplevel_page_my-plugin') return;
        wp_enqueue_script('my-script', plugin_dir_url(__FILE__) . 'script.js');
    }

    #[AdminNoticesAction]
    public function displayNotice(): void
    {
        ?>
        <div class="notice notice-info">
            <p>My notice message</p>
        </div>
        <?php
    }
}
```

## クイックスタート

### アトリビュートベースの管理ページ

PHP 8 アトリビュートを使用して、クリーンで宣言的な管理ページを定義します：

```php
use WpPack\Component\Admin\Attribute\AdminPage;
use WpPack\Component\Admin\Attribute\MenuItem;
use WpPack\Component\Admin\Attribute\AdminAsset;
use WpPack\Component\Admin\Attribute\ScreenOption;
use WpPack\Component\Admin\AbstractAdminPage;

#[AdminPage('product-manager')]
#[MenuItem(
    title: 'Product Manager',
    menuTitle: 'Products',
    capability: 'manage_products',
    icon: 'dashicons-products',
    position: 25
)]
#[AdminAsset('script', 'product-manager', '/assets/js/product-manager.js', ['jquery', 'wp-util'])]
#[AdminAsset('style', 'product-manager', '/assets/css/product-manager.css')]
class ProductManagerPage extends AbstractAdminPage
{
    use ListTable;

    #[ScreenOption('per_page', 'Products per page', 20)]
    protected int $perPage;

    public function render(): void
    {
        $products = $this->getProducts();
        $this->renderTemplate('admin/products', compact('products'));
    }
}
```

### 型安全なフォーム構築

自動バリデーションとサニタイズを備えた複雑なフォームを構築します：

```php
#[AdminPage('user-profile')]
class UserProfilePage extends AbstractAdminPage
{
    protected function getFields(): array
    {
        return [
            new Section('personal', 'Personal Information')
                ->fields([
                    new TextField('first_name', 'First Name')
                        ->required()
                        ->maxLength(50),
                    new EmailField('email', 'Email Address')
                        ->required()
                        ->unique('users', 'user_email'),
                    new DateField('birth_date', 'Birth Date')
                        ->before('today')
                ]),

            new Section('preferences', 'User Preferences')
                ->fields([
                    new SelectField('timezone', 'Timezone')
                        ->options($this->getTimezones())
                        ->default('UTC'),
                    new CheckboxField('notifications', 'Email Notifications')
                        ->options([
                            'comments' => 'New Comments',
                            'mentions' => 'Mentions',
                            'updates' => 'System Updates'
                        ]),
                    new ColorField('theme_color', 'Theme Color')
                        ->default('#0073aa')
                ])
        ];
    }
}
```

### 高度なリストテーブル

ソート、フィルタリング、一括操作を備えた強力な管理リストテーブルを作成します：

```php
use WpPack\Component\Admin\ListTable\ListTable;
use WpPack\Component\Admin\Attribute\ListTableColumn;
use WpPack\Component\Admin\Attribute\BulkAction;

#[AdminPage('order-management')]
class OrderListPage extends AbstractAdminPage
{
    use ListTable;

    #[ListTableColumn('ID', sortable: true, primary: true)]
    public function columnId($item): string
    {
        return sprintf(
            '<strong><a href="%s">Order #%s</a></strong>',
            $this->getEditUrl($item->ID),
            $item->ID
        );
    }

    #[ListTableColumn('Customer', sortable: true)]
    public function columnCustomer($item): string
    {
        $customer = get_user_by('id', $item->customer_id);
        return esc_html($customer->display_name);
    }

    #[BulkAction('mark_completed', 'Mark as Completed')]
    public function bulkMarkCompleted(array $ids): void
    {
        foreach ($ids as $id) {
            update_post_meta($id, 'order_status', 'completed');
        }
        $this->success(sprintf('%d orders marked as completed', count($ids)));
    }
}
```

### サブメニューページとフォーム

リッチなフォームフィールドを持つ追加/編集ページを作成します：

```php
use WpPack\Component\Admin\Attribute\SubMenuItem;

#[AdminPage('product-add')]
#[SubMenuItem(
    parent: 'product-manager',
    title: 'Add New Product',
    menuTitle: 'Add New',
    capability: 'manage_products'
)]
#[AdminAsset('script', 'product-form', '/assets/js/product-form.js', ['jquery', 'wp-media'])]
class AddProductPage extends AbstractAdminPage
{
    protected function getFields(): array
    {
        return [
            new Section('basic', 'Basic Information')
                ->fields([
                    new TextField('name', 'Product Name')
                        ->required()
                        ->maxLength(200),
                    new TextareaField('description', 'Description')
                        ->rows(8)
                        ->maxLength(2000),
                    new TextField('sku', 'SKU')
                        ->required()
                        ->pattern('[A-Z0-9-]+')
                        ->help('Unique product identifier')
                ]),

            new Section('pricing', 'Pricing')
                ->fields([
                    new NumberField('price', 'Regular Price')
                        ->required()
                        ->min(0)
                        ->step(0.01)
                        ->prefix('$'),
                    new NumberField('sale_price', 'Sale Price')
                        ->min(0)
                        ->step(0.01)
                        ->prefix('$')
                ]),

            new Section('inventory', 'Inventory')
                ->fields([
                    new SelectField('stock_status', 'Stock Status')
                        ->options([
                            'in_stock' => 'In Stock',
                            'out_of_stock' => 'Out of Stock',
                            'on_backorder' => 'On Backorder'
                        ]),
                    new NumberField('stock_quantity', 'Stock Quantity')
                        ->min(0)
                ]),

            new Section('media', 'Product Images')
                ->fields([
                    new MediaField('featured_image', 'Featured Image')
                        ->type('image')
                        ->preview(true),
                    new MediaField('gallery', 'Product Gallery')
                        ->type('image')
                        ->multiple(true)
                        ->sortable(true)
                        ->max(10)
                ]),

            new Section('attributes', 'Product Attributes')
                ->fields([
                    new RepeaterField('attributes', 'Custom Attributes')
                        ->fields([
                            new TextField('name', 'Attribute Name')->required(),
                            new TextField('value', 'Attribute Value')->required(),
                            new ToggleField('visible', 'Show on Product Page')->default(true)
                        ])
                        ->addButtonText('Add Attribute')
                        ->max(20)
                ])
        ];
    }

    protected function onSubmit(array $data): void
    {
        try {
            $productId = $this->createProduct($data);
            $this->success('Product created successfully!');
            wp_redirect(admin_url('admin.php?page=product-edit&id=' . $productId));
            exit;
        } catch (Exception $e) {
            $this->error('Error saving product: ' . $e->getMessage());
        }
    }
}
```

### ダッシュボードウィジェット

リッチなコンテンツを持つカスタムダッシュボードウィジェットを作成します：

```php
use WpPack\Component\Admin\Attribute\AsDashboardWidget;
use WpPack\Component\Admin\AbstractDashboardWidget;

#[AsDashboardWidget(
    id: 'products_overview',
    title: 'Products Overview',
    position: 'normal',
    priority: 'high'
)]
class ProductsOverviewWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        $stats = $this->getProductStats();
        ?>
        <div class="products-overview-widget">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($stats['total']) ?></span>
                    <span class="stat-label">Total Products</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($stats['active']) ?></span>
                    <span class="stat-label">Active</span>
                </div>
            </div>
        </div>
        <?php
    }

    public function configure(): void
    {
        // ウィジェット設定オプション
    }
}
```

### AJAX ハンドラー

セキュリティを内蔵した AJAX リクエストを処理します：

```php
use WpPack\Component\Admin\Ajax\AjaxHandler;
use WpPack\Component\Admin\Attribute\Ajax;

class ProductAjaxHandler extends AjaxHandler
{
    #[Ajax('update_product_status', public: false)]
    public function updateProductStatus(): JsonResponse
    {
        $this->verify(); // 自動 nonce 検証
        $this->requireCapability('manage_products');

        $productId = $this->request->getInt('product_id');
        $status = $this->request->get('status');

        if (!in_array($status, ['active', 'inactive', 'discontinued'])) {
            return $this->error('Invalid status');
        }

        update_post_meta($productId, '_product_status', $status);
        return $this->success(['message' => 'Product status updated']);
    }

    #[Ajax('search_products', public: false)]
    public function searchProducts(): JsonResponse
    {
        $this->verify();
        $this->requireCapability('read');

        $query = $this->request->get('query');
        $limit = $this->request->getInt('limit', 10);

        $products = $this->productRepository->search($query, $limit);
        return $this->json(['products' => $products]);
    }
}
```

### 管理画面通知

コンテキストに応じた管理画面通知を表示します：

```php
class NotificationManager
{
    public function __construct(
        private NoticeManager $notices
    ) {}

    public function displayWelcomeNotice(): void
    {
        $this->notices->info('Welcome to WpPack! Configure your settings to get started.')
            ->id('wppack_welcome')
            ->dismissible()
            ->action('Configure', admin_url('admin.php?page=wppack-settings'))
            ->capability('manage_options');
    }

    public function displayMaintenanceWarning(): void
    {
        $this->notices->warning('Scheduled maintenance will begin in 1 hour.')
            ->capability('administrator');
    }
}
```

## Named Hook アトリビュート

Admin コンポーネントは、WordPress 管理画面機能用の Named Hook アトリビュートを提供します。

### メニュー管理

```php
#[AdminMenuAction(priority?: int = 10)]              // admin_menu - 管理メニューの追加
#[NetworkAdminMenuAction(priority?: int = 10)]       // network_admin_menu - ネットワーク管理メニュー
#[UserAdminMenuAction(priority?: int = 10)]          // user_admin_menu - ユーザー管理メニュー
```

### 管理画面初期化

```php
#[AdminInitAction(priority?: int = 10)]              // admin_init - 管理画面の初期化
#[CurrentScreenAction(priority?: int = 10)]          // current_screen - 現在のスクリーンが読み込まれた時
```

### アセット

```php
#[AdminEnqueueScriptsAction(priority?: int = 10)]    // admin_enqueue_scripts - 管理画面スクリプト/スタイルのエンキュー
#[AdminPrintStylesAction(priority?: int = 10)]       // admin_print_styles - 管理画面スタイルの出力
#[AdminPrintScriptsAction(priority?: int = 10)]      // admin_print_scripts - 管理画面スクリプトの出力
```

### 通知

```php
#[AdminNoticesAction(priority?: int = 10)]           // admin_notices - 管理画面通知の表示
#[NetworkAdminNoticesAction(priority?: int = 10)]    // network_admin_notices - ネットワーク管理通知
#[UserAdminNoticesAction(priority?: int = 10)]       // user_admin_notices - ユーザー管理通知
#[AllAdminNoticesAction(priority?: int = 10)]        // all_admin_notices - すべての管理通知
```

### ダッシュボード

```php
#[WpDashboardSetupAction(priority?: int = 10)]       // wp_dashboard_setup - ダッシュボードウィジェットのセットアップ
#[WpNetworkDashboardSetupAction(priority?: int = 10)] // wp_network_dashboard_setup - ネットワークダッシュボード
```

### ヘッダー/フッター

```php
#[AdminHeadAction(priority?: int = 10)]              // admin_head - 管理画面ヘッドコンテンツ
#[AdminFooterAction(priority?: int = 10)]            // admin_footer - 管理画面フッターコンテンツ
#[AdminPrintFooterScriptsAction(priority?: int = 10)] // admin_print_footer_scripts - フッタースクリプト
```

### リストテーブル

```php
#[ManagePostsColumnsFilter(priority?: int = 10)]     // manage_posts_columns - 投稿カラムの変更
#[ManagePostsCustomColumnAction(priority?: int = 10)] // manage_posts_custom_column - カラムコンテンツの表示
#[ManagePagesColumnsFilter(priority?: int = 10)]     // manage_pages_columns - 固定ページカラムの変更
#[ManageUsersColumnsFilter(priority?: int = 10)]     // manage_users_columns - ユーザーカラムの変更
```

### 管理バー

```php
#[AdminBarMenuAction(priority?: int = 10)]           // admin_bar_menu - 管理バーの変更
#[WpBeforeAdminBarRenderAction(priority?: int = 10)] // wp_before_admin_bar_render - レンダリング前
```

### Named Hook の使用例

```php
use WpPack\Component\Admin\Attribute\AdminMenuAction;
use WpPack\Component\Admin\Attribute\AdminInitAction;
use WpPack\Component\Admin\Attribute\AdminEnqueueScriptsAction;
use WpPack\Component\Admin\Attribute\AdminNoticesAction;

class AdminInterface
{
    #[AdminMenuAction]
    public function setupMenus(): void
    {
        add_menu_page(
            'WpPack Dashboard',
            'WpPack',
            'manage_options',
            'wppack-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-admin-generic',
            25
        );
    }

    #[AdminInitAction]
    public function initializeSettings(): void
    {
        register_setting('wppack_settings', 'wppack_options', [
            'sanitize_callback' => [$this, 'sanitizeOptions'],
            'show_in_rest' => true,
        ]);
    }

    #[AdminEnqueueScriptsAction]
    public function enqueueAssets(string $hookSuffix): void
    {
        wp_enqueue_style('wppack-admin', plugins_url('assets/css/admin.css', WPPACK_PLUGIN_FILE));

        if (strpos($hookSuffix, 'wppack') !== false) {
            wp_enqueue_script(
                'wppack-admin-app',
                plugins_url('assets/js/admin-app.js', WPPACK_PLUGIN_FILE),
                ['wp-element', 'wp-components', 'wp-api'],
                WPPACK_VERSION,
                true
            );
        }
    }

    #[AdminNoticesAction]
    public function displayNotices(): void
    {
        if (!get_option('wppack_api_key')) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    'WpPack requires an API key. <a href="%s">Configure it now</a>.',
                    admin_url('admin.php?page=wppack-settings')
                )
            );
        }
    }
}
```

### カスタム管理カラム

```php
use WpPack\Component\Admin\Attribute\ManagePostsColumnsFilter;
use WpPack\Component\Admin\Attribute\ManagePostsCustomColumnAction;

class PostColumns
{
    #[ManagePostsColumnsFilter]
    public function addCustomColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $value) {
            $newColumns[$key] = $value;
            if ($key === 'title') {
                $newColumns['wppack_views'] = 'Views';
            }
        }
        $newColumns['wppack_featured'] = '<span class="dashicons dashicons-star-filled"></span>';
        return $newColumns;
    }

    #[ManagePostsCustomColumnAction]
    public function displayCustomColumns(string $columnName, int $postId): void
    {
        match ($columnName) {
            'wppack_views' => printf('%s', number_format_i18n(
                get_post_meta($postId, '_wppack_view_count', true) ?: 0
            )),
            'wppack_featured' => get_post_meta($postId, '_wppack_featured', true)
                ? print '<span class="dashicons dashicons-star-filled" style="color: #f0ad4e;"></span>'
                : null,
            default => null,
        };
    }
}
```

### ネットワーク管理とダッシュボードウィジェット

```php
use WpPack\Component\Admin\Attribute\NetworkAdminMenuAction;
use WpPack\Component\Admin\Attribute\WpDashboardSetupAction;

class NetworkAdmin
{
    #[NetworkAdminMenuAction]
    public function registerNetworkMenus(): void
    {
        add_menu_page(
            'Network WpPack',
            'WpPack Network',
            'manage_network_options',
            'wppack-network',
            [$this, 'renderNetworkPage'],
            'dashicons-networking',
            30
        );
    }
}

class DashboardWidgets
{
    #[WpDashboardSetupAction]
    public function registerDashboardWidgets(): void
    {
        wp_add_dashboard_widget(
            'wppack_status_widget',
            'WpPack Status',
            [$this, 'renderStatusWidget'],
            null,
            null,
            'normal',
            'high'
        );
    }
}
```

## 管理コンポーネントの登録

```php
add_action('init', function () {
    if (!is_admin()) {
        return;
    }

    $container = new WpPack\Container();

    $container->register([
        ProductManagerPage::class,
        AddProductPage::class,
        ProductsOverviewWidget::class,
        ProductAjaxHandler::class,
    ]);

    $adminManager = $container->get(WpPack\Component\Admin\AdminManager::class);
    $adminManager->discoverAdminComponents();
});
```

## パフォーマンス機能

### 条件付きアセット読み込み

```php
#[AdminPage('advanced-editor')]
#[AdminAsset('script', 'advanced-editor', '/assets/js/editor.js',
    dependencies: ['wp-blocks', 'wp-editor'],
    condition: 'current_user_can("edit_posts")'
)]
class AdvancedEditorPage extends AbstractAdminPage
{
    protected function shouldLoadAssets(): bool
    {
        return $this->isCurrentPage() && current_user_can('edit_posts');
    }
}
```

## セキュリティ機能

- **自動 nonce 検証** - CSRF 保護を内蔵
- **権限チェック** - WordPress パーミッションとの統合
- **入力サニタイズ** - 自動データサニタイズ
- **SQL インジェクション防止** - 安全なデータベースクエリ
- **XSS 保護** - 出力エスケープ
- **ファイルアップロードセキュリティ** - 安全なメディア処理

## このコンポーネントの使用場面

**最適な用途：**
- 複雑な WordPress 管理インターフェース
- プラグイン管理パネル
- カスタム投稿タイプの管理
- ユーザー管理システム
- EC サイト管理パネル
- コンテンツ管理ダッシュボード

**代替を検討すべき場合：**
- シンプルな設定ページ（Setting コンポーネントを使用）
- フロントエンドのユーザーインターフェース
- 基本的な WordPress カスタマイズ

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress アクション/フィルター登録用

### 推奨
- **DependencyInjection コンポーネント** - サービスコンテナと管理サービス用
- **Security コンポーネント** - 権限チェックと nonce 検証用
- **Option コンポーネント** - 管理設定ストレージ用
- **EventDispatcher コンポーネント** - 管理ライフサイクルイベント用
