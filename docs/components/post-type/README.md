# PostType Component

**Package:** `wppack/post-type`
**Namespace:** `WpPack\Component\PostType\`
**Layer:** Feature

PostType コンポーネントは、WordPress カスタム投稿タイプをモダンなオブジェクト指向で開発するためのフレームワークです。PHP 8 アトリビュートによる型安全なメタフィールド定義、REST API の自動統合、流暢なクエリインターフェースを提供します。

> [!NOTE]
> このコンポーネントは設計段階です。Repository（`PostRepository`）は実装済みです。投稿タイプ定義・メタフィールド等の機能は設計仕様として参照してください。

## インストール

```bash
composer require wppack/post-type
```

## このコンポーネントの機能

- **オブジェクト指向の投稿タイプ定義** — PHP 8 アトリビュートによる宣言的な定義
- **型安全なメタフィールド** — 自動サニタイズ・バリデーション付き
- **REST API 自動統合** — エンドポイントの自動設定
- **拡張クエリ** — 流暢なクエリビルダーによるメタクエリ
- **カスタムフィールド管理** — UI の自動生成
- **階層的な投稿タイプ** — 親子関係のサポート

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('init', function () {
    register_post_type('product', [
        'labels' => [
            'name' => 'Products',
            'singular_name' => 'Product',
            'add_new' => 'Add New Product',
            'edit_item' => 'Edit Product',
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true,
    ]);
});

add_action('add_meta_boxes', function () {
    add_meta_box('product_details', 'Product Details', 'product_meta_box_callback', 'product');
});

function product_meta_box_callback($post) {
    $price = get_post_meta($post->ID, 'price', true);
    echo '<input type="number" name="price" value="' . esc_attr($price) . '">';
}
```

### After（WpPack）

```php
use WpPack\Component\PostType\AbstractPostType;
use WpPack\Component\PostType\Attribute\PostType;
use WpPack\Component\PostType\Attribute\Meta;
use WpPack\Component\PostType\Attribute\MetaValidate;

#[PostType(
    name: 'product',
    labels: ['name' => 'Products', 'singular_name' => 'Product'],
    public: true,
    hasArchive: true,
    showInRest: true
)]
class Product extends AbstractPostType
{
    protected array $supports = ['title', 'editor', 'thumbnail'];

    #[Meta(type: 'number', label: 'Price', required: true)]
    #[MetaValidate('min:0|max:99999.99')]
    protected float $price = 0.0;

    #[Meta(type: 'text', label: 'SKU', maxLength: 50)]
    protected string $sku = '';

    #[Meta(type: 'checkbox', label: 'Featured Product')]
    protected bool $featured = false;

    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }
}
```

> [!IMPORTANT]
> **メタプロパティの初期値について:** `#[Meta]` アトリビュートが付与されたプロパティには、必ずデフォルト値を設定してください（例: `protected float $price = 0.0;`）。`AbstractPostType` は投稿のロード時にリフレクションを使って `#[Meta]` プロパティを自動的にデータベースの値で初期化しますが、新規投稿の作成時やメタ値が未保存の場合に備え、デフォルト値が必要です。

## クイックスタート

### カスタム投稿タイプの定義

```php
use WpPack\Component\PostType\AbstractPostType;
use WpPack\Component\PostType\Attribute\PostType;
use WpPack\Component\PostType\Attribute\Meta;
use WpPack\Component\PostType\Attribute\MetaValidate;

#[PostType(
    name: 'product',
    labels: [
        'name' => 'Products',
        'singular_name' => 'Product',
        'add_new' => 'Add New Product',
        'add_new_item' => 'Add New Product',
        'edit_item' => 'Edit Product',
        'new_item' => 'New Product',
        'view_item' => 'View Product',
        'search_items' => 'Search Products',
    ],
    public: true,
    hasArchive: true,
    showInRest: true,
    supports: ['title', 'editor', 'thumbnail', 'excerpt'],
    menuIcon: 'dashicons-cart',
    menuPosition: 20
)]
class Product extends AbstractPostType
{
    #[Meta(type: 'number', label: 'Price', description: 'Product price in USD', step: 0.01, required: true)]
    #[MetaValidate('required|min:0|max:99999.99')]
    protected float $price = 0.0;

    #[Meta(type: 'text', label: 'SKU', description: 'Unique product identifier', maxLength: 50, placeholder: 'e.g. PROD-001')]
    #[MetaValidate('required|alphanumeric_dash|max:50')]
    protected string $sku = '';

    #[Meta(type: 'number', label: 'Stock Quantity', min: 0, default: 0)]
    #[MetaValidate('integer|min:0')]
    protected int $stockQuantity = 0;

    #[Meta(type: 'checkbox', label: 'Featured Product')]
    protected bool $featured = false;

    #[Meta(type: 'select', label: 'Product Status', options: [
        'active' => 'Active',
        'discontinued' => 'Discontinued',
        'coming_soon' => 'Coming Soon',
    ], default: 'active')]
    protected string $status = 'active';

    #[Meta(type: 'textarea', label: 'Short Description', maxLength: 200, rows: 3)]
    protected string $shortDescription = '';

    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    public function isInStock(): bool
    {
        return $this->stockQuantity > 0;
    }

    public function getStockStatus(): string
    {
        if ($this->stockQuantity > 10) return 'in_stock';
        if ($this->stockQuantity > 0) return 'low_stock';
        return 'out_of_stock';
    }

    protected function onSave(): void
    {
        if (empty($this->sku)) {
            $this->sku = 'PROD-' . str_pad($this->ID, 6, '0', STR_PAD_LEFT);
        }
    }
}
```

### 登録と使用

```php
add_action('init', function () {
    $container = new WpPack\Container();
    $container->register(Product::class);
});
```

### 拡張クエリ

```php
// 50ドル以下のフィーチャー商品を取得
$featuredProducts = Product::query()
    ->where('featured', true)
    ->where('price', '<=', 50)
    ->where('status', 'active')
    ->orderBy('price', 'asc')
    ->limit(8)
    ->get();

// 在庫の少ない商品を取得
$lowStockProducts = Product::query()
    ->where('stockQuantity', '>', 0)
    ->where('stockQuantity', '<=', 10)
    ->orderBy('stockQuantity', 'asc')
    ->get();

// SKU で検索
$product = Product::query()
    ->where('sku', 'PROD-001')
    ->first();

// タクソノミーフィルタリング付きクエリ
$electronicsProducts = Product::query()
    ->whereTerms('product_category', ['electronics'])
    ->where('status', 'active')
    ->get();
```

### テンプレートでの表示

```php
// archive-product.php または single-product.php
if (have_posts()) :
    while (have_posts()) : the_post();
        $product = new Product(get_post());
        ?>
        <div class="product-card">
            <h2><?php the_title(); ?></h2>
            <p class="price"><?php echo $product->getFormattedPrice(); ?></p>
            <p class="sku">SKU: <?php echo esc_html($product->sku); ?></p>

            <div class="stock-status status-<?php echo $product->getStockStatus(); ?>">
                <?php if ($product->isInStock()) : ?>
                    <span>In Stock (<?php echo $product->stockQuantity; ?>)</span>
                <?php else : ?>
                    <span>Out of Stock</span>
                <?php endif; ?>
            </div>

        </div>
        <?php
    endwhile;
endif;
```

### REST API 統合

カスタム投稿タイプは自動的に REST API エンドポイントを持ちます:

```bash
# 全商品を取得
GET /wp-json/wp/v2/product

# 特定の商品をメタ付きで取得
GET /wp-json/wp/v2/product/123

# 商品を作成（認証済み）
POST /wp-json/wp/v2/product
{
  "title": "New Product",
  "content": "Description",
  "meta": {
    "price": 29.99,
    "sku": "PROD-002",
    "stockQuantity": 100,
    "featured": false,
    "status": "active"
  }
}
```

## 高度な機能

### 階層的な投稿タイプ

親子関係を持つ投稿タイプを定義できます:

```php
#[PostType('documentation', hierarchical: true)]
class Documentation extends AbstractPostType
{
    public function getChildren(): Collection
    {
        return static::query()
            ->where('post_parent', $this->ID)
            ->orderBy('menu_order')
            ->get();
    }
}
```

### カスタムタクソノミーとの連携

```php
#[PostType('product')]
class Product extends AbstractPostType
{
    public function getCategories(): array
    {
        return wp_get_post_terms($this->ID, 'product_category', ['fields' => 'names']);
    }

    public function getTags(): array
    {
        return wp_get_post_terms($this->ID, 'product_tag', ['fields' => 'names']);
    }
}
```

## WordPress との統合

- **WordPress 投稿タイプシステムを使用** — 既存コードと完全互換
- **テーマとの連携** — テンプレート階層に統合
- **プラグイン互換性** — 他の WordPress プラグインと連携
- **SEO 対応** — WordPress の SEO 機能をサポート
- **マルチサイト互換** — WordPress ネットワーク全体で動作

## このコンポーネントの使用場面

**最適な用途:**
- カスタムコンテンツタイプ（商品、イベント、ポートフォリオ）
- 構造化データが必要なサイト
- 複雑なメタフィールドを持つアプリケーション
- REST API 統合が必要なプロジェクト
- EC サイトやカタログサイト

**別の方法を検討:**
- デフォルトの投稿タイプを使用するシンプルなブログ
- カスタムフィールドが不要なサイト

## Repository

`PostRepositoryInterface` / `PostRepository` は、WordPress 投稿の CRUD 操作とメタデータ操作を提供します。

```php
use WpPack\Component\PostType\PostRepository;
use WpPack\Component\PostType\PostRepositoryInterface;

$repository = new PostRepository();

// 投稿の取得
$post = $repository->find($postId);              // WP_Post|null
$posts = $repository->findAll(['post_status' => 'publish']);

// 投稿の作成・更新・削除
$newId = $repository->insert(['post_title' => 'New Post', 'post_status' => 'draft']);
$repository->update(['ID' => $newId, 'post_title' => 'Updated Title']);
$repository->delete($newId, force: true);

// ゴミ箱操作
$repository->trash($postId);
$repository->untrash($postId);

// メタデータ操作
$repository->addMeta($postId, 'custom_key', 'value');
$value = $repository->getMeta($postId, 'custom_key', single: true);
$repository->updateMeta($postId, 'custom_key', 'new_value');
$repository->deleteMeta($postId, 'custom_key');

// メタキーによる投稿検索
$foundId = $repository->findOneByMeta('_wp_attached_file', '2024/01/photo.jpg', 'attachment');
```

## 依存関係

### 必須
- **Hook Component** — WordPress 登録フック用

### 推奨
- **Query Component** — 拡張クエリ機能
- **Security Component** — メタフィールドのサニタイズ
- **Cache Component** — クエリ結果のキャッシュ
