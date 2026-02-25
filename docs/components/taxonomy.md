# Taxonomy コンポーネント

Taxonomy コンポーネントは、型安全なタクソノミー管理、カスタムタクソノミーフィールド、階層構造、拡張クエリ機能を備えた、WordPress タクソノミー開発へのモダンなオブジェクト指向アプローチを提供します。

## このコンポーネントの機能

Taxonomy コンポーネントは、以下の機能を提供することで WordPress タクソノミー開発を変革します：

- **オブジェクト指向タクソノミー管理** - クラスベースのタクソノミー定義
- **型安全なタクソノミーフィールド** - カスタムメタデータとバリデーション
- **拡張階層構造** - 親子関係の管理
- **高度なタクソノミークエリ** - 流暢なクエリビルダー
- **タクソノミーリレーション** - 投稿、ユーザー、他のタクソノミー間の関連付け
- **カスタムタクソノミーテンプレート** - 表示管理
- **SEO フレンドリーなタクソノミー管理** - メタフィールド付き

## クイック例

従来の WordPress タクソノミー登録の代わりに：

```php
// Traditional WordPress - procedural taxonomy registration
add_action('init', function() {
    register_taxonomy('product_category', 'product', [
        'labels' => [
            'name' => 'Product Categories',
            'singular_name' => 'Product Category'
        ],
        'hierarchical' => true,
        'public' => true
    ]);
});
```

モダンな WpPack アプローチを使用します：

```php
// Modern WpPack - object-oriented taxonomy development
#[Taxonomy(
    name: 'product_category',
    postTypes: ['product'],
    hierarchical: true,
    public: true
)]
class ProductCategory extends AbstractTaxonomy
{
    #[TaxonomyField('color', type: 'string')]
    protected string $color = '';

    #[TaxonomyField('icon', type: 'string')]
    protected string $icon = '';

    #[TaxonomyField('description_long', type: 'text')]
    protected string $longDescription = '';

    public function getColorCode(): string
    {
        return $this->color ?: '#0073aa';
    }
}
```

## インストール

```bash
composer require wppack/taxonomy
```

## コア機能

### 型安全なタクソノミーモデル

カスタムフィールドとビジネスロジックを持つタクソノミーを定義します：

```php
#[Taxonomy(
    name: 'location',
    postTypes: ['event', 'venue'],
    hierarchical: true,
    labels: [
        'name' => 'Locations',
        'singular_name' => 'Location'
    ]
)]
class Location extends AbstractTaxonomy
{
    #[TaxonomyField('coordinates', type: 'array')]
    protected array $coordinates = [];

    #[TaxonomyField('timezone', type: 'string')]
    protected string $timezone = 'UTC';

    #[TaxonomyField('country_code', type: 'string')]
    protected string $countryCode = '';

    #[TaxonomyField('population', type: 'number')]
    protected int $population = 0;

    public function getLatitude(): ?float
    {
        return $this->coordinates['lat'] ?? null;
    }

    public function getLongitude(): ?float
    {
        return $this->coordinates['lng'] ?? null;
    }

    public function getTimezoneObject(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone);
    }
}
```

### 高度なタクソノミークエリ

流暢で型安全なメソッドでタクソノミーをクエリします：

```php
class TaxonomyQueryService
{
    public function __construct(
        private TaxonomyRepository $taxonomyRepository
    ) {}

    public function getPopularCategories(int $limit = 10): Collection
    {
        return $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->withPostCount()
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCategoriesInRegion(string $countryCode): Collection
    {
        return $this->taxonomyRepository
            ->forTaxonomy(Location::class)
            ->where('country_code', $countryCode)
            ->hierarchical()
            ->orderBy('name')
            ->get();
    }
}
```

### 階層リレーション

複雑なタクソノミー階層を管理します：

```php
class TaxonomyHierarchyService
{
    public function buildCategoryTree(ProductCategory $root): array
    {
        $children = $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->where('parent', $root->getId())
            ->orderBy('name')
            ->get();

        $tree = [];
        foreach ($children as $child) {
            $tree[] = [
                'category' => $child,
                'children' => $this->buildCategoryTree($child)
            ];
        }

        return $tree;
    }
}
```

## クイックスタート

### 最初のカスタムタクソノミー

カスタムフィールド、階層構造、拡張機能を備えた完全な商品カテゴリシステムを作成します：

```php
<?php
use WpPack\Component\Taxonomy\Attribute\Taxonomy;
use WpPack\Component\Taxonomy\Attribute\TaxonomyField;
use WpPack\Component\Taxonomy\AbstractTaxonomy;

#[Taxonomy(
    name: 'product_category',
    postTypes: ['product'],
    hierarchical: true,
    public: true,
    showInRest: true,
    labels: [
        'name' => 'Product Categories',
        'singular_name' => 'Product Category',
        'search_items' => 'Search Categories',
        'all_items' => 'All Categories',
        'parent_item' => 'Parent Category',
        'parent_item_colon' => 'Parent Category:',
        'edit_item' => 'Edit Category',
        'update_item' => 'Update Category',
        'add_new_item' => 'Add New Category',
        'new_item_name' => 'New Category Name',
        'menu_name' => 'Categories'
    ],
    rewrite: [
        'slug' => 'category',
        'with_front' => false,
        'hierarchical' => true
    ]
)]
class ProductCategory extends AbstractTaxonomy
{
    #[TaxonomyField('color', type: 'string', default: '#0073aa')]
    protected string $color;

    #[TaxonomyField('icon', type: 'string')]
    protected string $icon = '';

    #[TaxonomyField('description_long', type: 'text')]
    protected string $longDescription = '';

    #[TaxonomyField('featured_image', type: 'number')]
    protected int $featuredImage = 0;

    #[TaxonomyField('banner_image', type: 'number')]
    protected int $bannerImage = 0;

    #[TaxonomyField('sort_order', type: 'number', default: 0)]
    protected int $sortOrder;

    #[TaxonomyField('is_featured', type: 'boolean', default: false)]
    protected bool $isFeatured;

    #[TaxonomyField('display_type', type: 'string', default: 'products', enum: ['products', 'subcategories', 'both'])]
    protected string $displayType;

    #[TaxonomyField('seo_title', type: 'string')]
    protected string $seoTitle = '';

    #[TaxonomyField('seo_description', type: 'text')]
    protected string $seoDescription = '';

    #[TaxonomyField('commission_rate', type: 'number', default: 0)]
    protected float $commissionRate;

    public function getColorCode(): string
    {
        return $this->color ?: '#0073aa';
    }

    public function getIconHtml(): string
    {
        if (!$this->icon) {
            return '';
        }

        if (strpos($this->icon, 'dashicons-') === 0) {
            return sprintf('<span class="dashicons %s"></span>', esc_attr($this->icon));
        }

        return sprintf('<i class="%s"></i>', esc_attr($this->icon));
    }

    public function getFeaturedImageUrl(string $size = 'medium'): string
    {
        if (!$this->featuredImage) {
            return '';
        }

        return wp_get_attachment_image_url($this->featuredImage, $size) ?: '';
    }

    public function getBannerImageUrl(string $size = 'large'): string
    {
        if (!$this->bannerImage) {
            return '';
        }

        return wp_get_attachment_image_url($this->bannerImage, $size) ?: '';
    }

    public function getUrl(): string
    {
        return get_term_link($this->getId(), $this->getTaxonomyName()) ?: '';
    }

    public function getSEOTitle(): string
    {
        return $this->seoTitle ?: $this->getName();
    }

    public function getSEODescription(): string
    {
        return $this->seoDescription ?: $this->getDescription();
    }

    public function shouldDisplayProducts(): bool
    {
        return in_array($this->displayType, ['products', 'both']);
    }

    public function shouldDisplaySubcategories(): bool
    {
        return in_array($this->displayType, ['subcategories', 'both']);
    }

    public function getChildren(): array
    {
        return get_terms([
            'taxonomy' => $this->getTaxonomyName(),
            'parent' => $this->getId(),
            'orderby' => 'meta_value_num',
            'meta_key' => 'sort_order',
            'order' => 'ASC',
            'hide_empty' => false
        ]);
    }

    public function getParent(): ?self
    {
        $parentId = $this->getParentId();

        if (!$parentId) {
            return null;
        }

        $parentTerm = get_term($parentId, $this->getTaxonomyName());

        if (!$parentTerm || is_wp_error($parentTerm)) {
            return null;
        }

        return self::fromTerm($parentTerm);
    }

    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumb, $current);
            $current = $current->getParent();
        }

        return $breadcrumb;
    }

    public function getProductCount(): int
    {
        return $this->getCount();
    }

    public function hasProducts(): bool
    {
        return $this->getProductCount() > 0;
    }

    public function calculateCommission(float $amount): float
    {
        return $amount * ($this->commissionRate / 100);
    }
}
```

### イベント用ロケーションタクソノミー

```php
<?php
#[Taxonomy(
    name: 'event_location',
    postTypes: ['event', 'venue'],
    hierarchical: true,
    public: true,
    labels: [
        'name' => 'Locations',
        'singular_name' => 'Location'
    ]
)]
class EventLocation extends AbstractTaxonomy
{
    #[TaxonomyField('country_code', type: 'string')]
    protected string $countryCode = '';

    #[TaxonomyField('region', type: 'string')]
    protected string $region = '';

    #[TaxonomyField('coordinates', type: 'array')]
    protected array $coordinates = [];

    #[TaxonomyField('timezone', type: 'string', default: 'UTC')]
    protected string $timezone;

    #[TaxonomyField('population', type: 'number')]
    protected int $population = 0;

    #[TaxonomyField('currency', type: 'string')]
    protected string $currency = '';

    #[TaxonomyField('language', type: 'string')]
    protected string $language = '';

    public function getLatitude(): ?float
    {
        return isset($this->coordinates['lat']) ? (float) $this->coordinates['lat'] : null;
    }

    public function getLongitude(): ?float
    {
        return isset($this->coordinates['lng']) ? (float) $this->coordinates['lng'] : null;
    }

    public function getTimezoneObject(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->timezone);
        } catch (\Exception $e) {
            return new \DateTimeZone('UTC');
        }
    }

    public function getCountryName(): string
    {
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'FR' => 'France',
            'DE' => 'Germany',
            'JP' => 'Japan',
        ];

        return $countries[$this->countryCode] ?? $this->countryCode;
    }

    public function getFullLocationName(): string
    {
        $parts = [];
        $current = $this;

        while ($current) {
            array_unshift($parts, $current->getName());
            $current = $current->getParent();
        }

        return implode(', ', $parts);
    }

    public function getLocalTime(\DateTime $utcTime = null): \DateTime
    {
        if (!$utcTime) {
            $utcTime = new \DateTime('now', new \DateTimeZone('UTC'));
        }

        $localTime = clone $utcTime;
        $localTime->setTimezone($this->getTimezoneObject());

        return $localTime;
    }

}
```

### タクソノミーリポジトリサービス

```php
<?php
use WpPack\Component\Taxonomy\TaxonomyRepository;

class ProductCategoryRepository
{
    public function __construct(
        private TaxonomyRepository $taxonomyRepository
    ) {}

    public function getFeaturedCategories(): array
    {
        return $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getRootCategories(): array
    {
        return $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->where('parent', 0)
            ->orderBy('sort_order')
            ->get();
    }

    public function getCategoriesByColor(string $color): array
    {
        return $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->where('color', $color)
            ->orderBy('name')
            ->get();
    }

    public function searchCategories(string $query): array
    {
        return $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->search($query)
            ->limit(10)
            ->get();
    }

    public function getCategoryTree(ProductCategory $parent = null): array
    {
        $parentId = $parent ? $parent->getId() : 0;

        $categories = $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->where('parent', $parentId)
            ->orderBy('sort_order')
            ->get();

        $tree = [];
        foreach ($categories as $category) {
            $tree[] = [
                'category' => $category,
                'children' => $this->getCategoryTree($category)
            ];
        }

        return $tree;
    }

    public function createCategory(array $data): ProductCategory
    {
        if (empty($data['name'])) {
            throw new \Exception('Category name is required');
        }

        $existing = $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->where('name', $data['name'])
            ->first();

        if ($existing) {
            throw new \Exception('Category with this name already exists');
        }

        $category = new ProductCategory();
        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? '');
        $category->setSlug($data['slug'] ?? sanitize_title($data['name']));

        if (!empty($data['parent_id'])) {
            $category->setParentId($data['parent_id']);
        }

        $category->setColor($data['color'] ?? '#0073aa');
        $category->setIcon($data['icon'] ?? '');
        $category->setLongDescription($data['long_description'] ?? '');
        $category->setFeaturedImage($data['featured_image'] ?? 0);
        $category->setBannerImage($data['banner_image'] ?? 0);
        $category->setSortOrder($data['sort_order'] ?? 0);
        $category->setIsFeatured($data['is_featured'] ?? false);
        $category->setDisplayType($data['display_type'] ?? 'products');
        $category->setSeoTitle($data['seo_title'] ?? '');
        $category->setSeoDescription($data['seo_description'] ?? '');
        $category->setCommissionRate($data['commission_rate'] ?? 0);

        $this->taxonomyRepository->save($category);

        return $category;
    }

    public function updateSortOrders(array $orders): void
    {
        foreach ($orders as $categoryId => $sortOrder) {
            $category = $this->taxonomyRepository->find($categoryId);
            if ($category) {
                $category->setSortOrder($sortOrder);
                $this->taxonomyRepository->save($category);
            }
        }
    }

    public function getPopularCategories(int $limit = 10): array
    {
        return $this->taxonomyRepository
            ->forTaxonomy(ProductCategory::class)
            ->withProductCount()
            ->having('product_count', '>', 0)
            ->orderBy('product_count', 'desc')
            ->limit($limit)
            ->get();
    }

}
```

### 管理画面統合

```php
<?php
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;

class TaxonomyAdminIntegration
{
    public function __construct(
        private ProductCategoryRepository $categoryRepository
    ) {}

    #[Action('product_category_edit_form_fields', priority: 10)]
    public function addCategoryFields(\WP_Term $term): void
    {
        $category = ProductCategory::fromTerm($term);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="category-color">Color</label></th>
            <td>
                <input type="color" name="color" id="category-color" value="<?= esc_attr($category->getColorCode()) ?>" />
                <p class="description">Choose a color for this category</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="category-icon">Icon Class</label></th>
            <td>
                <input type="text" name="icon" id="category-icon" value="<?= esc_attr($category->getIcon()) ?>" />
                <p class="description">CSS class for category icon (e.g., dashicons-star-filled)</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="category-long-description">Long Description</label></th>
            <td>
                <textarea name="long_description" id="category-long-description" rows="5" cols="50"><?= esc_textarea($category->getLongDescription()) ?></textarea>
                <p class="description">Detailed description for category page</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="category-featured-image">Featured Image</label></th>
            <td>
                <div class="category-image-field">
                    <input type="hidden" name="featured_image" id="category-featured-image" value="<?= esc_attr($category->getFeaturedImage()) ?>" />
                    <button type="button" class="button upload-image-button">Choose Image</button>
                    <button type="button" class="button remove-image-button" style="<?= $category->getFeaturedImage() ? '' : 'display:none;' ?>">Remove</button>
                    <div class="image-preview">
                        <?php if ($category->getFeaturedImageUrl()): ?>
                            <img src="<?= esc_url($category->getFeaturedImageUrl('thumbnail')) ?>" alt="Featured Image" />
                        <?php endif; ?>
                    </div>
                </div>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="category-sort-order">Sort Order</label></th>
            <td>
                <input type="number" name="sort_order" id="category-sort-order" value="<?= esc_attr($category->getSortOrder()) ?>" min="0" />
                <p class="description">Order for displaying categories (lower numbers first)</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">Featured Category</th>
            <td>
                <label>
                    <input type="checkbox" name="is_featured" value="1" <?= checked($category->isFeatured(), true, false) ?> />
                    Feature this category on homepage
                </label>
            </td>
        </tr>
        <?php
    }

    #[Action('edited_product_category', priority: 10)]
    public function saveCategoryFields(int $termId): void
    {
        if (!current_user_can('manage_categories')) {
            return;
        }

        $term = get_term($termId, 'product_category');
        if (!$term || is_wp_error($term)) {
            return;
        }

        $category = ProductCategory::fromTerm($term);

        if (isset($_POST['color'])) {
            $category->setColor(sanitize_hex_color($_POST['color']));
        }

        if (isset($_POST['icon'])) {
            $category->setIcon(sanitize_text_field($_POST['icon']));
        }

        if (isset($_POST['long_description'])) {
            $category->setLongDescription(sanitize_textarea_field($_POST['long_description']));
        }

        if (isset($_POST['featured_image'])) {
            $category->setFeaturedImage(intval($_POST['featured_image']));
        }

        if (isset($_POST['sort_order'])) {
            $category->setSortOrder(intval($_POST['sort_order']));
        }

        $category->setIsFeatured(isset($_POST['is_featured']));

        $this->categoryRepository->taxonomyRepository->save($category);
    }

    #[Filter('manage_edit-product_category_columns', priority: 10)]
    public function addCategoryColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;

            if ($key === 'name') {
                $newColumns['color'] = 'Color';
                $newColumns['featured'] = 'Featured';
                $newColumns['sort_order'] = 'Sort Order';
            }
        }

        return $newColumns;
    }

    #[Action('manage_product_category_custom_column', priority: 10)]
    public function displayCategoryColumnContent(string $content, string $columnName, int $termId): string
    {
        $term = get_term($termId, 'product_category');
        if (!$term || is_wp_error($term)) {
            return $content;
        }

        $category = ProductCategory::fromTerm($term);

        switch ($columnName) {
            case 'color':
                return sprintf(
                    '<span class="color-indicator" style="background-color: %s; width: 20px; height: 20px; display: inline-block; border-radius: 3px;"></span>',
                    esc_attr($category->getColorCode())
                );

            case 'featured':
                return $category->isFeatured() ? 'Featured' : '-';

            case 'sort_order':
                return $category->getSortOrder();
        }

        return $content;
    }
}
```

## Named Hook アトリビュート

### タクソノミー登録フック

タクソノミー登録には、Hook コンポーネントの `#[InitAction]` アトリビュートを使用します：

```php
use WpPack\Component\Hook\Attribute\InitAction;
use WpPack\Component\Taxonomy\TaxonomyRegistry;

class TaxonomyManager
{
    private TaxonomyRegistry $taxonomies;

    public function __construct(TaxonomyRegistry $taxonomies)
    {
        $this->taxonomies = $taxonomies;
    }

    #[InitAction(priority: 0)]
    public function registerTaxonomies(): void
    {
        $this->taxonomies->register('product_category', ['product'], [
            'labels' => $this->getProductCategoryLabels(),
            'hierarchical' => true,
            'public' => true,
            'show_in_rest' => true,
            'rewrite' => [
                'slug' => 'products/category',
                'with_front' => false,
                'hierarchical' => true,
            ],
            'capabilities' => [
                'manage_terms' => 'manage_product_categories',
                'edit_terms' => 'edit_product_categories',
                'delete_terms' => 'delete_product_categories',
                'assign_terms' => 'assign_product_categories',
            ],
        ]);

        $this->taxonomies->register('product_tag', ['product'], [
            'labels' => $this->getProductTagLabels(),
            'hierarchical' => false,
            'show_in_rest' => true,
            'rewrite' => [
                'slug' => 'products/tag',
                'with_front' => false,
            ],
        ]);

        $this->taxonomies->register('brand', ['product', 'post'], [
            'labels' => $this->getBrandLabels(),
            'hierarchical' => false,
            'show_admin_column' => true,
            'show_in_quick_edit' => true,
            'meta_box_cb' => [$this, 'renderBrandMetaBox'],
        ]);
    }

    private function getProductCategoryLabels(): array
    {
        return [
            'name' => __('Product Categories', 'wppack'),
            'singular_name' => __('Product Category', 'wppack'),
            'search_items' => __('Search Categories', 'wppack'),
            'all_items' => __('All Categories', 'wppack'),
            'parent_item' => __('Parent Category', 'wppack'),
            'parent_item_colon' => __('Parent Category:', 'wppack'),
            'edit_item' => __('Edit Category', 'wppack'),
            'update_item' => __('Update Category', 'wppack'),
            'add_new_item' => __('Add New Category', 'wppack'),
            'new_item_name' => __('New Category Name', 'wppack'),
            'menu_name' => __('Categories', 'wppack'),
        ];
    }
}
```

### #[RegisteredTaxonomyAction(priority?: int = 10)]

**WordPress フック:** `registered_taxonomy`
**使用場面:** タクソノミーが登録された後にアクションを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\RegisteredTaxonomyAction;

class TaxonomySetup
{
    #[RegisteredTaxonomyAction]
    public function setupTaxonomyFeatures(string $taxonomy, array|string $object_type, array $args): void
    {
        if (in_array($taxonomy, ['product_category', 'brand'])) {
            $this->addTaxonomyFields($taxonomy);
        }

        if (!empty($args['capabilities'])) {
            $this->setupCapabilities($taxonomy, $args['capabilities']);
        }

        if (!empty($args['show_in_rest'])) {
            $this->registerRestFields($taxonomy);
        }
    }

    private function registerRestFields(string $taxonomy): void
    {
        register_rest_field($taxonomy, 'featured_image', [
            'get_callback' => [$this, 'getTermFeaturedImage'],
            'update_callback' => [$this, 'updateTermFeaturedImage'],
            'schema' => [
                'type' => 'integer',
                'description' => __('Featured image ID', 'wppack'),
            ],
        ]);
    }
}
```

### #[CreateTermAction(taxonomy: string, priority?: int = 10)]

**WordPress フック:** `create_{$taxonomy}`
**使用場面:** タームが作成されたときにアクションを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\CreateTermAction;

class TermManager
{
    #[CreateTermAction(taxonomy: 'product_category')]
    public function onCategoryCreated(int $term_id, int $tt_id, array $args): void
    {
        add_term_meta($term_id, 'display_type', 'default', true);
        add_term_meta($term_id, 'thumbnail_id', 0, true);

        if (!empty($args['create_page'])) {
            $this->createCategoryPage($term_id);
        }

        wp_cache_delete('product_categories', 'wppack');

        $this->logger->info('Product category created', [
            'term_id' => $term_id,
            'name' => $args['name'] ?? '',
            'slug' => $args['slug'] ?? '',
        ]);
    }

    #[CreateTermAction(taxonomy: 'brand')]
    public function onBrandCreated(int $term_id, int $tt_id): void
    {
        $this->generateBrandPlaceholder($term_id);
        $this->notifyBrandCreation($term_id);
    }
}
```

### #[EditTermAction(taxonomy: string, priority?: int = 10)]

**WordPress フック:** `edit_{$taxonomy}`
**使用場面:** タームが更新されたときにアクションを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\EditTermAction;

class TermUpdateHandler
{
    #[EditTermAction(taxonomy: 'product_category')]
    public function onCategoryUpdated(int $term_id, int $tt_id, array $args): void
    {
        $this->clearCategoryCache($term_id);

        if (isset($args['parent'])) {
            $this->updateHierarchyCache($term_id, $args['parent']);
        }

        if ($this->shouldSyncExternal()) {
            $this->queueExternalSync('category', $term_id);
        }
    }

    #[EditTermAction(taxonomy: 'product_tag')]
    public function onTagUpdated(int $term_id): void
    {
        $this->reindexTaggedProducts($term_id);
    }
}
```

### #[DeleteTermAction(taxonomy: string, priority?: int = 10)]

**WordPress フック:** `delete_{$taxonomy}`
**使用場面:** タームが削除されたときにクリーンアップを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\DeleteTermAction;

class TermDeletionHandler
{
    #[DeleteTermAction(taxonomy: 'product_category')]
    public function onCategoryDeleted(int $term_id, int $tt_id, \WP_Term $deleted_term): void
    {
        $this->cleanupTermMeta($term_id);

        $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        if ($thumbnail_id) {
            wp_delete_attachment($thumbnail_id, true);
        }

        $this->logger->info('Product category deleted', [
            'term_id' => $term_id,
            'name' => $deleted_term->name,
            'product_count' => $deleted_term->count,
        ]);
    }
}
```

### #[PreGetTermsAction(priority?: int = 10)]

**WordPress フック:** `pre_get_terms`
**使用場面:** タームクエリが実行される前に変更する場合。

```php
use WpPack\Component\Taxonomy\Attribute\PreGetTermsAction;

class TermQueryModifier
{
    #[PreGetTermsAction]
    public function modifyTermQueries(\WP_Term_Query $query): void
    {
        if (!is_admin() && in_array('product_category', (array) $query->query_vars['taxonomy'])) {
            $query->query_vars['hide_empty'] = true;
        }

        if (in_array('brand', (array) $query->query_vars['taxonomy'])) {
            $query->query_vars['orderby'] = 'name';
            $query->query_vars['order'] = 'ASC';
        }

        if (!empty($query->query_vars['featured_only'])) {
            $query->query_vars['meta_query'] = [
                [
                    'key' => 'is_featured',
                    'value' => '1',
                ],
            ];
        }
    }
}
```

### #[TermsClausesFilter(priority?: int = 10)]

**WordPress フック:** `terms_clauses`
**使用場面:** タームクエリの SQL 句を変更する場合。

```php
use WpPack\Component\Taxonomy\Attribute\TermsClausesFilter;

class TermQueryOptimizer
{
    #[TermsClausesFilter]
    public function optimizeTermQueries(array $clauses, array $taxonomies, array $args): array
    {
        global $wpdb;

        if (!empty($args['orderby_menu_order'])) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS tm_order
                                  ON (t.term_id = tm_order.term_id
                                  AND tm_order.meta_key = 'menu_order')";
            $clauses['orderby'] = "CAST(tm_order.meta_value AS SIGNED) ASC, " . $clauses['orderby'];
        }

        if (!empty($args['lang'])) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS tm_lang
                                  ON (t.term_id = tm_lang.term_id
                                  AND tm_lang.meta_key = 'language')";
            $clauses['where'] .= $wpdb->prepare(
                " AND (tm_lang.meta_value = %s OR tm_lang.meta_value IS NULL)",
                $args['lang']
            );
        }

        return $clauses;
    }
}
```

### #[TermLinkFilter(priority?: int = 10)]

**WordPress フック:** `term_link`
**使用場面:** タームのパーマリンクを変更する場合。

```php
use WpPack\Component\Taxonomy\Attribute\TermLinkFilter;

class TermLinkCustomizer
{
    #[TermLinkFilter]
    public function customizeTermLinks(string $termlink, \WP_Term $term, string $taxonomy): string
    {
        if ($taxonomy === 'product_category' && $term->parent) {
            $ancestors = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
            $ancestors = array_reverse($ancestors);

            $hierarchy = [];
            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $taxonomy);
                $hierarchy[] = $ancestor->slug;
            }

            $hierarchy[] = $term->slug;
            $termlink = home_url('/products/category/' . implode('/', $hierarchy) . '/');
        }

        if ($lang = get_term_meta($term->term_id, 'language', true)) {
            $termlink = str_replace(home_url(), home_url('/' . $lang), $termlink);
        }

        return $termlink;
    }
}
```

### #[GetTermsFilter(priority?: int = 10)]

**WordPress フック:** `get_terms`
**使用場面:** 取得されたターム一覧をフィルタリングする場合。

```php
use WpPack\Component\Taxonomy\Attribute\GetTermsFilter;

class TermFilterHandler
{
    #[GetTermsFilter]
    public function filterTermResults(array $terms, array $taxonomies, array $args, \WP_Term_Query $term_query): array
    {
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                $term->thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

                $term->color = get_term_meta($term->term_id, 'color', true);
                $term->icon = get_term_meta($term->term_id, 'icon', true);
            }
        }

        if (!empty($args['has_products'])) {
            $terms = array_filter($terms, function($term) {
                return $term->count > 0;
            });
        }

        return $terms;
    }
}
```

### 完全なタクソノミーシステム

```php
use WpPack\Component\Hook\Attribute\InitAction;
use WpPack\Component\Taxonomy\Attribute\CreateTermAction;
use WpPack\Component\Taxonomy\Attribute\EditTermAction;
use WpPack\Component\Taxonomy\Attribute\PreGetTermsAction;
use WpPack\Component\Taxonomy\TaxonomyRegistry;

class WpPackTaxonomySystem
{
    private TaxonomyRegistry $registry;
    private TermMetaManager $metaManager;
    private CacheManager $cache;
    private Logger $logger;

    public function __construct(
        TaxonomyRegistry $registry,
        TermMetaManager $metaManager,
        CacheManager $cache,
        Logger $logger
    ) {
        $this->registry = $registry;
        $this->metaManager = $metaManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    #[InitAction]
    public function registerAllTaxonomies(): void
    {
        $this->registerProductTaxonomies();

        $this->registry->register('location', ['store', 'event'], [
            'labels' => $this->getLocationLabels(),
            'hierarchical' => true,
            'show_in_rest' => true,
            'capabilities' => [
                'manage_terms' => 'manage_locations',
                'edit_terms' => 'edit_locations',
                'delete_terms' => 'delete_locations',
                'assign_terms' => 'edit_posts',
            ],
        ]);

        $this->registry->register('skill', ['team_member'], [
            'labels' => $this->getSkillLabels(),
            'hierarchical' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'meta_box_cb' => false,
        ]);
    }

    private function registerProductTaxonomies(): void
    {
        $this->registry->register('product_cat', ['product'], [
            'labels' => [
                'name' => __('Product Categories', 'wppack'),
                'singular_name' => __('Product Category', 'wppack'),
                'menu_name' => __('Categories', 'wppack'),
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite' => [
                'slug' => 'shop/category',
                'with_front' => false,
                'hierarchical' => true,
            ],
        ]);

        $attributes = [
            'color' => ['label' => __('Colors', 'wppack'), 'slug' => 'color'],
            'size' => ['label' => __('Sizes', 'wppack'), 'slug' => 'size'],
            'material' => ['label' => __('Materials', 'wppack'), 'slug' => 'material'],
        ];

        foreach ($attributes as $attribute => $config) {
            $this->registry->register("pa_$attribute", ['product'], [
                'labels' => [
                    'name' => $config['label'],
                    'singular_name' => $config['label'],
                ],
                'hierarchical' => false,
                'show_in_nav_menus' => false,
                'show_admin_column' => true,
                'rewrite' => [
                    'slug' => "shop/{$config['slug']}",
                    'with_front' => false,
                ],
            ]);
        }
    }

    #[CreateTermAction(taxonomy: 'product_cat')]
    public function setupNewCategory(int $term_id, int $tt_id): void
    {
        $defaults = [
            'thumbnail_id' => 0,
            'display_type' => 'default',
            'category_layout' => 'grid',
            'products_per_page' => 12,
        ];

        foreach ($defaults as $key => $value) {
            add_term_meta($term_id, $key, $value, true);
        }

        $this->createCategoryPlaceholder($term_id);

        $this->cache->delete('product_categories');
        $this->cache->delete('category_hierarchy');
    }

    #[EditTermAction(taxonomy: 'product_cat')]
    public function updateCategoryCache(int $term_id): void
    {
        $this->cache->delete("category_$term_id");
        $this->cache->delete('product_categories');

        $this->updateSearchIndex($term_id);

        do_action('wppack_category_updated', $term_id);
    }

    #[PreGetTermsAction]
    public function enhanceTermQueries(\WP_Term_Query $query): void
    {
        if (in_array('product_cat', (array) $query->query_vars['taxonomy'])) {
            add_filter('get_terms', [$this, 'addThumbnailData'], 10, 4);
        }

        if (strpos($query->query_vars['taxonomy'], 'pa_') === 0) {
            if (empty($query->query_vars['orderby'])) {
                $query->query_vars['orderby'] = 'menu_order';
                $query->query_vars['order'] = 'ASC';
            }
        }
    }

    public function addThumbnailData($terms, $taxonomies, $args, $term_query)
    {
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                $term->thumbnail = [
                    'id' => $thumbnail_id,
                    'url' => wp_get_attachment_url($thumbnail_id),
                    'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
                ];
            }
        }

        return $terms;
    }
}
```

### 高度なタームメタシステム

```php
use WpPack\Component\Taxonomy\Attribute\CreatedTermAction;
use WpPack\Component\Taxonomy\Attribute\EditedTermAction;
use WpPack\Component\Taxonomy\TermMetaField;

class AdvancedTermMetaManager
{
    private array $fields = [];

    #[InitAction]
    public function registerTermFields(): void
    {
        $this->addField('product_cat', new TermMetaField([
            'id' => 'featured_products',
            'label' => __('Featured Products', 'wppack'),
            'type' => 'post_select',
            'post_type' => 'product',
            'multiple' => true,
            'max_items' => 5,
        ]));

        $this->addField('product_cat', new TermMetaField([
            'id' => 'banner_settings',
            'label' => __('Banner Settings', 'wppack'),
            'type' => 'group',
            'fields' => [
                'image' => ['type' => 'media', 'label' => __('Banner Image', 'wppack')],
                'title' => ['type' => 'text', 'label' => __('Banner Title', 'wppack')],
                'subtitle' => ['type' => 'text', 'label' => __('Banner Subtitle', 'wppack')],
                'link' => ['type' => 'url', 'label' => __('Banner Link', 'wppack')],
            ],
        ]));

        $this->addField('brand', new TermMetaField([
            'id' => 'brand_info',
            'label' => __('Brand Information', 'wppack'),
            'type' => 'group',
            'fields' => [
                'logo' => ['type' => 'media', 'label' => __('Brand Logo', 'wppack')],
                'website' => ['type' => 'url', 'label' => __('Website', 'wppack')],
                'founded' => ['type' => 'number', 'label' => __('Founded Year', 'wppack')],
                'country' => ['type' => 'select', 'label' => __('Country', 'wppack')],
            ],
        ]));
    }

    #[CreatedTermAction(priority: 10)]
    public function initializeTermMeta(int $term_id, int $tt_id, string $taxonomy): void
    {
        if (!isset($this->fields[$taxonomy])) {
            return;
        }

        foreach ($this->fields[$taxonomy] as $field) {
            $default = $field->getDefault();
            if ($default !== null) {
                add_term_meta($term_id, $field->getId(), $default, true);
            }
        }
    }

    #[EditedTermAction(priority: 10)]
    public function saveTermMeta(int $term_id, int $tt_id, string $taxonomy): void
    {
        if (!isset($_POST['term_meta']) || !isset($this->fields[$taxonomy])) {
            return;
        }

        foreach ($this->fields[$taxonomy] as $field) {
            $value = $_POST['term_meta'][$field->getId()] ?? null;

            if ($field->validate($value)) {
                $sanitized = $field->sanitize($value);
                update_term_meta($term_id, $field->getId(), $sanitized);
            }
        }
    }
}
```

## Hook アトリビュートリファレンス

### 利用可能な Hook アトリビュート

```php
// 登録
#[RegisteredTaxonomyAction(priority?: int = 10)]      // タクソノミー登録後

// ターム管理
#[CreateTermAction(taxonomy: string, priority?: int = 10)]              // ターム作成時
#[EditTermAction(taxonomy: string, priority?: int = 10)]                // ターム編集時
#[DeleteTermAction(taxonomy: string, priority?: int = 10)]              // ターム削除時
#[CreatedTermAction(priority?: int = 10)]             // ターム作成後（全タクソノミー）
#[EditedTermAction(priority?: int = 10)]              // ターム編集後（全タクソノミー）

// タームクエリ
#[PreGetTermsAction(priority?: int = 10)]             // タームクエリ実行前
#[TermsClausesFilter(priority?: int = 10)]            // タームクエリ SQL の変更
#[GetTermsFilter(priority?: int = 10)]                // 取得されたタームのフィルタリング

// ターム表示
#[TermLinkFilter(priority?: int = 10)]                // タームパーマリンクの変更
#[GetTheTermsFilter(priority?: int = 10)]             // 投稿タームのフィルタリング
#[TermNameFilter(priority?: int = 10)]                // ターム名のフィルタリング
```

## 従来の WordPress と WpPack の比較

### Before（従来の WordPress）
```php
add_action('init', function() {
    register_taxonomy('product_brand', 'product', [
        'labels' => [
            'name' => 'Brands',
            'singular_name' => 'Brand',
        ],
        'hierarchical' => false,
        'show_admin_column' => true,
    ]);
});

add_action('create_product_brand', function($term_id) {
    add_term_meta($term_id, 'brand_logo', '', true);
});

add_action('pre_get_terms', function($query) {
    if (in_array('product_brand', $query->query_vars['taxonomy'])) {
        $query->query_vars['orderby'] = 'name';
    }
});
```

### After（WpPack）
```php
use WpPack\Component\Hook\Attribute\InitAction;
use WpPack\Component\Taxonomy\Attribute\CreateTermAction;
use WpPack\Component\Taxonomy\Attribute\PreGetTermsAction;
use WpPack\Component\Taxonomy\TaxonomyRegistry;

class BrandTaxonomy
{
    private TaxonomyRegistry $registry;

    public function __construct(TaxonomyRegistry $registry)
    {
        $this->registry = $registry;
    }

    #[InitAction]
    public function register(): void
    {
        $this->registry->register('product_brand', ['product'], [
            'labels' => [
                'name' => __('Brands', 'wppack'),
                'singular_name' => __('Brand', 'wppack'),
            ],
            'hierarchical' => false,
            'show_admin_column' => true,
        ]);
    }

    #[CreateTermAction(taxonomy: 'product_brand')]
    public function setupBrand(int $term_id): void
    {
        add_term_meta($term_id, 'brand_logo', '', true);
    }

    #[PreGetTermsAction]
    public function orderBrands(\WP_Term_Query $query): void
    {
        if (in_array('product_brand', (array) $query->query_vars['taxonomy'])) {
            $query->query_vars['orderby'] = 'name';
        }
    }
}
```

### メリット
- **整理** - タクソノミーロジックがまとまる
- **型安全** - パラメータが型付けされる
- **対象を絞ったフック** - 正確なタクソノミーを指定可能
- **依存性注入** - サービスの注入が容易
- **テスタビリティ** - メソッドをユニットテスト可能

## このコンポーネントの使用場面

**最適な用途：**
- EC サイトのカテゴリ管理
- コンテンツ整理システム
- 地理的なロケーションタクソノミー
- 多階層分類システム
- 商品属性管理
- コンテンツタグ付けシステム

**代替を検討すべき場合：**
- シンプルな WordPress カテゴリ（コアの WordPress を使用）
- カスタムフィールドのない単一階層タクソノミー
- 基本的なタグ付けシステム

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress タクソノミー登録用

### 推奨
- **Query コンポーネント** - タクソノミークエリの拡張用
- **Cache コンポーネント** - タクソノミーデータのキャッシュ用
- **EventDispatcher コンポーネント** - タクソノミーイベント用

## 高度な機能

### タクソノミーテンプレート

異なるコンテキストに応じたカスタム表示テンプレート：

```php
#[TaxonomyTemplate('archive')]
class ProductCategoryArchiveTemplate
{
    public function render(ProductCategory $category): string
    {
        return $this->view->render('taxonomy/product-category-archive', [
            'category' => $category,
            'products' => $this->getProductsInCategory($category),
            'subcategories' => $this->getSubcategories($category)
        ]);
    }
}
```

### SEO 統合

タクソノミーページ向けの組み込み SEO 機能：

```php
#[Taxonomy(name: 'product_category')]
class ProductCategory extends AbstractTaxonomy
{
    #[TaxonomyField('seo_title', type: 'string')]
    protected string $seoTitle = '';

    #[TaxonomyField('seo_description', type: 'text')]
    protected string $seoDescription = '';

    #[TaxonomyField('canonical_url', type: 'url')]
    protected string $canonicalUrl = '';

    public function getSEOTitle(): string
    {
        return $this->seoTitle ?: $this->getName();
    }
}
```

## ベストプラクティス

1. **パフォーマンス**
   - タームクエリをキャッシュする
   - ターム メタを効率的に使用する
   - カスタムタームメタにインデックスを作成する
   - データベースクエリを最小限にする

2. **構造**
   - カテゴリには階層型を使用する
   - タグには非階層型を使用する
   - URL 構造を慎重に計画する
   - マルチサイトのニーズを考慮する

3. **ユーザー体験**
   - 明確なラベルを提供する
   - 有用な説明を追加する
   - 適切な UI コントロールを使用する
   - 一括操作をサポートする

4. **データ整合性**
   - タームメタをバリデーションする
   - タームのマージを処理する
   - 削除時にクリーンアップする
   - ターム数を維持する
