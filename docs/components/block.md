# Block コンポーネント

**パッケージ:** `wppack/block`
**名前空間:** `WPPack\Component\Block\`
**Category:** Content

サーバーサイドレンダリング、ダイナミックブロック、ブロックパターン、ブロックバリエーション、WordPress ブロックエディター（Gutenberg）との完全統合を備えた、モダンなオブジェクト指向のブロック開発フレームワークです。

> [!WARNING]
> このコンポーネントは設計段階です。ソースコードの実装はまだありません。以下は設計仕様として参照してください。

## インストール

```bash
composer require wppack/block
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('init', function() {
    register_block_type('my-plugin/featured-posts', [
        'attributes' => [
            'numberOfPosts' => ['type' => 'number', 'default' => 5],
            'displayFeaturedImage' => ['type' => 'boolean', 'default' => true]
        ],
        'render_callback' => 'render_featured_posts_block'
    ]);
});

function render_featured_posts_block($attributes, $content) {
    ob_start();
    // 複雑なレンダリングロジック...
    return ob_get_clean();
}
```

### After（WPPack）

```php
use WPPack\Component\Block\AbstractBlock;
use WPPack\Component\Block\Attribute\Block;
use WPPack\Component\Block\Attribute\BlockAttribute;

#[Block(
    name: 'featured-posts',
    namespace: 'my-plugin',
    title: 'Featured Posts',
    category: 'widgets'
)]
class FeaturedPostsBlock extends AbstractBlock
{
    #[BlockAttribute('numberOfPosts', type: 'number', default: 5)]
    protected int $numberOfPosts;

    #[BlockAttribute('displayFeaturedImage', type: 'boolean', default: true)]
    protected bool $displayFeaturedImage;

    public function render(array $attributes, string $content): string
    {
        $posts = $this->queryService->getFeaturedPosts($this->numberOfPosts);
        return $this->renderTemplate('blocks/featured-posts', compact('posts'));
    }
}
```

## 機能

- **オブジェクト指向のブロック開発** - クラスベースのブロック定義
- **アトリビュートベースのブロック登録** - 宣言的な設定
- **サーバーサイドレンダリング（SSR）** - PHP テンプレート統合
- **ダイナミックブロック対応** - リアルタイムデータバインディング
- **ブロックバリエーションとスタイル** - カスタマイズの強化
- **InnerBlocks 管理** - 複雑なネストブロック構造
- **ブロックパターンとコレクション** - 再利用可能なブロック構成
- **フルサイト編集対応** - テンプレートパーツ統合

## クイックスタート

### テスティモニアルブロック

```php
<?php
use WPPack\Component\Block\Attribute\Block;
use WPPack\Component\Block\Attribute\BlockAttribute;
use WPPack\Component\Block\Attribute\BlockStyle;
use WPPack\Component\Block\AbstractBlock;

#[Block(
    name: 'customer-testimonial',
    namespace: 'company',
    title: 'Customer Testimonial',
    description: 'Display customer testimonials with ratings and avatars',
    category: 'widgets',
    icon: 'format-quote',
    keywords: ['testimonial', 'review', 'customer', 'rating']
)]
#[BlockStyle('default', 'Default Style')]
#[BlockStyle('minimal', 'Minimal Style')]
#[BlockStyle('card', 'Card Style')]
class CustomerTestimonialBlock extends AbstractBlock
{
    #[BlockAttribute('customerName', type: 'string', required: true)]
    protected string $customerName;

    #[BlockAttribute('testimonial', type: 'string', required: true)]
    protected string $testimonial;

    #[BlockAttribute('rating', type: 'number', default: 5, min: 1, max: 5)]
    protected int $rating;

    #[BlockAttribute('customerTitle', type: 'string')]
    protected string $customerTitle = '';

    #[BlockAttribute('company', type: 'string')]
    protected string $company = '';

    #[BlockAttribute('avatarUrl', type: 'string')]
    protected string $avatarUrl = '';

    #[BlockAttribute('showAvatar', type: 'boolean', default: true)]
    protected bool $showAvatar;

    #[BlockAttribute('backgroundColor', type: 'string')]
    protected string $backgroundColor = '';

    #[BlockAttribute('textColor', type: 'string')]
    protected string $textColor = '';

    public function render(array $attributes, string $content): string
    {
        $this->extractAttributes($attributes);
        $classes = $this->buildClasses($attributes);
        $starsHtml = $this->generateStarRating($this->rating);

        ob_start();
        ?>
        <div class="<?= esc_attr(implode(' ', $classes)) ?>">
            <blockquote class="testimonial-quote">
                <p class="testimonial-text"><?= esc_html($this->testimonial) ?></p>
            </blockquote>

            <div class="testimonial-rating">
                <?= $starsHtml ?>
            </div>

            <div class="testimonial-author">
                <?php if ($this->showAvatar && $this->avatarUrl): ?>
                    <div class="author-avatar">
                        <img src="<?= esc_url($this->avatarUrl) ?>"
                             alt="<?= esc_attr($this->customerName) ?>"
                             loading="lazy" />
                    </div>
                <?php endif; ?>

                <div class="author-info">
                    <cite class="author-name"><?= esc_html($this->customerName) ?></cite>
                    <?php if ($this->customerTitle): ?>
                        <span class="author-title"><?= esc_html($this->customerTitle) ?></span>
                    <?php endif; ?>
                    <?php if ($this->company): ?>
                        <span class="author-company"><?= esc_html($this->company) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function getSupports(): array
    {
        return [
            'align' => ['left', 'center', 'right', 'wide', 'full'],
            'anchor' => true,
            'className' => true,
            'color' => ['background' => true, 'text' => true, 'gradients' => true],
            'spacing' => ['margin' => true, 'padding' => true],
            'typography' => ['fontSize' => true, 'lineHeight' => true]
        ];
    }
}
```

### ダイナミック商品ショーケースブロック

```php
<?php
#[Block(
    name: 'product-showcase',
    namespace: 'shop',
    title: 'Product Showcase',
    description: 'Display featured products dynamically',
    category: 'widgets',
    icon: 'products',
    keywords: ['products', 'shop', 'ecommerce'],
    dynamic: true
)]
class ProductShowcaseBlock extends AbstractBlock
{
    public function __construct(
        private ProductRepository $productRepository,
        private CacheInterface $cache
    ) {}

    #[BlockAttribute('numberOfProducts', type: 'number', default: 6, min: 1, max: 12)]
    protected int $numberOfProducts;

    #[BlockAttribute('categoryId', type: 'number')]
    protected ?int $categoryId = null;

    #[BlockAttribute('showPrice', type: 'boolean', default: true)]
    protected bool $showPrice;

    #[BlockAttribute('showDescription', type: 'boolean', default: true)]
    protected bool $showDescription;

    #[BlockAttribute('layout', type: 'string', default: 'grid', enum: ['grid', 'list', 'carousel'])]
    protected string $layout;

    #[BlockAttribute('featuredOnly', type: 'boolean', default: false)]
    protected bool $featuredOnly;

    public function render(array $attributes, string $content): string
    {
        $this->extractAttributes($attributes);

        $cacheKey = sprintf('product_showcase_%d_%s_%s',
            $this->numberOfProducts,
            $this->categoryId ?? 'all',
            $this->layout
        );

        $products = $this->cache->get($cacheKey, function() {
            return $this->productRepository->findBy([
                'limit' => $this->numberOfProducts,
                'category_id' => $this->categoryId,
                'featured' => $this->featuredOnly,
                'status' => 'published'
            ]);
        });

        if (empty($products)) {
            return '<div class="wp-block-shop-product-showcase empty">' .
                   '<p>' . __('No products found.', 'textdomain') . '</p></div>';
        }

        return $this->renderTemplate("blocks/products-{$this->layout}", [
            'products' => $products,
            'showPrice' => $this->showPrice,
            'showDescription' => $this->showDescription,
            'attributes' => $attributes,
        ]);
    }
}
```

## ブロックバリエーションとスタイル

```php
use WPPack\Component\Block\Attribute\BlockVariation;
use WPPack\Component\Block\Attribute\BlockStyle;

#[Block(name: 'card', namespace: 'ui')]
#[BlockVariation('product-card', 'Product Card', [
    'category' => 'product',
    'attributes' => ['type' => 'product']
])]
#[BlockVariation('service-card', 'Service Card', [
    'category' => 'service',
    'attributes' => ['type' => 'service']
])]
#[BlockStyle('rounded', 'Rounded Corners')]
#[BlockStyle('shadow', 'Drop Shadow')]
class CardBlock extends AbstractBlock
{
    #[BlockAttribute('type', type: 'string', default: 'default')]
    protected string $type;

    #[BlockAttribute('title', type: 'string', required: true)]
    protected string $title;

    public function render(array $attributes, string $content): string
    {
        $template = match($this->type) {
            'product' => 'blocks/product-card',
            'service' => 'blocks/service-card',
            default => 'blocks/card'
        };

        return $this->renderTemplate($template, $attributes);
    }
}
```

## InnerBlocks サポート

```php
#[Block(
    name: 'container',
    namespace: 'layout',
    title: 'Content Container',
    supports: ['innerBlocks' => true]
)]
class ContainerBlock extends AbstractBlock
{
    #[BlockAttribute('backgroundColor', type: 'string')]
    protected ?string $backgroundColor;

    #[BlockAttribute('padding', type: 'string', default: 'medium')]
    protected string $padding;

    public function render(array $attributes, string $content): string
    {
        $classes = ['wp-block-layout-container'];

        if ($this->backgroundColor) {
            $classes[] = "has-{$this->backgroundColor}-background-color";
        }

        $classes[] = "has-{$this->padding}-padding";

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr(implode(' ', $classes)),
            $content // InnerBlocks コンテンツ
        );
    }

    public function getInnerBlocksTemplate(): array
    {
        return [
            ['core/heading', ['level' => 2]],
            ['core/paragraph'],
            ['core/button']
        ];
    }
}
```

## ブロックパターン

```php
use WPPack\Component\Block\Attribute\BlockPattern;
use WPPack\Component\Block\AbstractBlockPattern;

#[BlockPattern(
    name: 'hero-section',
    title: 'Hero Section',
    description: 'A hero section with heading, text, and call-to-action',
    categories: ['header'],
    keywords: ['hero', 'banner', 'cta']
)]
class HeroSectionPattern extends AbstractBlockPattern
{
    public function getContent(): array
    {
        return [
            ['core/group', [
                'className' => 'hero-section',
                'style' => [
                    'spacing' => ['padding' => ['top' => '4rem', 'bottom' => '4rem']]
                ]
            ], [
                ['core/heading', [
                    'level' => 1,
                    'content' => 'Welcome to Our Amazing Product',
                    'textAlign' => 'center'
                ]],
                ['core/paragraph', [
                    'content' => 'Discover the power of innovation.',
                    'textAlign' => 'center',
                    'fontSize' => 'large'
                ]],
                ['core/buttons', [
                    'layout' => ['type' => 'flex', 'justifyContent' => 'center']
                ], [
                    ['core/button', [
                        'text' => 'Get Started',
                        'className' => 'is-style-fill'
                    ]]
                ]]
            ]]
        ];
    }
}
```

## ブロックコレクション

```php
use WPPack\Component\Block\Attribute\BlockCollection;
use WPPack\Component\Block\AbstractBlockCollection;

#[BlockCollection(
    name: 'e-commerce',
    title: 'E-commerce Blocks',
    icon: 'cart'
)]
class ECommerceBlockCollection extends AbstractBlockCollection
{
    public function getBlocks(): array
    {
        return [
            ProductGridBlock::class,
            ProductCardBlock::class,
            ShoppingCartBlock::class,
            CheckoutFormBlock::class,
        ];
    }

    public function getPatterns(): array
    {
        return [
            ProductShowcasePattern::class,
            ShopHomepagePattern::class,
        ];
    }
}
```

### ブロックバリデーション

```php
#[Block(name: 'contact-form', namespace: 'forms')]
class ContactFormBlock extends AbstractBlock
{
    #[BlockAttribute('email', type: 'string', required: true)]
    #[Validate('email')]
    protected string $email;

    #[BlockAttribute('fields', type: 'array')]
    #[Validate('custom', callback: [self::class, 'validateFields'])]
    protected array $fields;

    public static function validateFields(array $fields): bool
    {
        return count($fields) <= 20 && !empty($fields);
    }
}
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](./hook/block.md) を参照してください。

## このコンポーネントの使用場面

**最適な用途：**
- カスタム WordPress ブロック
- ダイナミックコンテンツブロック
- EC サイトの商品表示
- コンテンツ管理システム
- フルサイト編集テーマ
- ブロックベースのページビルダー
- 再利用可能なコンテンツコンポーネント

**代替を検討すべき場合：**
- シンプルな静的コンテンツ（コアブロックを使用）
- 複雑なインタラクティブアプリケーション（専用コンポーネントを使用）
- ブロックエディターを使用しない WordPress サイト

## WordPress との統合

- **WordPress ブロック上に構築** - WordPress コアのブロックインフラを使用
- **ブロックテーマと互換** - フルサイト編集に対応
- **プラグイン互換性** - ブロック関連プラグインと連携
- **標準的な動作** - WordPress のブロックパターンを維持
- **エディター統合** - Gutenberg エディターを完全サポート

## 依存関係

### 推奨
- **DependencyInjection コンポーネント** - ブロックへのサービスインジェクション用
- **Templating コンポーネント** - ブロックテンプレートレンダリング用
- **Cache コンポーネント** - ダイナミックブロックのキャッシュ用
