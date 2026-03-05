# Taxonomy コンポーネント

**パッケージ:** `wppack/taxonomy`
**名前空間:** `WpPack\Component\Taxonomy\`
**レイヤー:** Feature

WordPress のタクソノミー登録関数 `register_taxonomy()` をアトリビュートでラップし、タームの CRUD フックを Named Hook アトリビュートとして提供するコンポーネントです。

## インストール

```bash
composer require wppack/taxonomy
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// Traditional WordPress - procedural taxonomy registration
add_action('init', function() {
    register_taxonomy('product_category', 'product', [
        'labels' => [
            'name' => 'Product Categories',
            'singular_name' => 'Product Category',
        ],
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
    ]);
});

add_action('create_product_category', function($term_id) {
    add_term_meta($term_id, 'brand_logo', '', true);
});

add_action('pre_get_terms', function($query) {
    if (in_array('product_category', $query->query_vars['taxonomy'])) {
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

class ProductTaxonomy
{
    public function __construct(
        private readonly TaxonomyRegistry $registry,
    ) {}

    #[InitAction]
    public function register(): void
    {
        $this->registry->register('product_category', ['product'], [
            'labels' => [
                'name' => __('Product Categories', 'wppack'),
                'singular_name' => __('Product Category', 'wppack'),
            ],
            'hierarchical' => true,
            'public' => true,
            'show_in_rest' => true,
        ]);
    }

    #[CreateTermAction(taxonomy: 'product_category')]
    public function setupCategory(int $term_id): void
    {
        add_term_meta($term_id, 'brand_logo', '', true);
    }

    #[PreGetTermsAction]
    public function orderCategories(\WP_Term_Query $query): void
    {
        if (in_array('product_category', (array) $query->query_vars['taxonomy'])) {
            $query->query_vars['orderby'] = 'name';
        }
    }
}
```

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Taxonomy/Subscriber/`

### タクソノミー登録フック

タクソノミー登録には、Hook コンポーネントの `#[InitAction]` アトリビュートを使用します。

### #[RegisteredTaxonomyAction]

**WordPress フック:** `registered_taxonomy`
**使用場面:** タクソノミーが登録された後にアクションを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\RegisteredTaxonomyAction;

class TaxonomySetup
{
    #[RegisteredTaxonomyAction]
    public function onTaxonomyRegistered(string $taxonomy, array|string $object_type, array $args): void
    {
        if (!empty($args['show_in_rest'])) {
            register_rest_field($taxonomy, 'featured_image', [
                'get_callback' => [$this, 'getTermFeaturedImage'],
                'schema' => [
                    'type' => 'integer',
                    'description' => __('Featured image ID', 'wppack'),
                ],
            ]);
        }
    }
}
```

### #[CreateTermAction]

**WordPress フック:** `create_{$taxonomy}`
**使用場面:** タームが作成されたときにアクションを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\CreateTermAction;

class TermManager
{
    #[CreateTermAction(taxonomy: 'product_category')]
    public function onCategoryCreated(int $term_id, int $tt_id): void
    {
        add_term_meta($term_id, 'display_type', 'default', true);
        add_term_meta($term_id, 'thumbnail_id', 0, true);

        wp_cache_delete('product_categories', 'wppack');
    }

    #[CreateTermAction(taxonomy: 'brand')]
    public function onBrandCreated(int $term_id, int $tt_id): void
    {
        add_term_meta($term_id, 'brand_logo', '', true);
    }
}
```

### #[EditTermAction]

**WordPress フック:** `edit_{$taxonomy}`
**使用場面:** タームが更新されたときにアクションを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\EditTermAction;

class TermUpdateHandler
{
    #[EditTermAction(taxonomy: 'product_category')]
    public function onCategoryUpdated(int $term_id, int $tt_id): void
    {
        wp_cache_delete("category_{$term_id}", 'wppack');
        wp_cache_delete('product_categories', 'wppack');
    }

    #[EditTermAction(taxonomy: 'product_tag')]
    public function onTagUpdated(int $term_id): void
    {
        wp_cache_delete("tag_{$term_id}", 'wppack');
    }
}
```

### #[DeleteTermAction]

**WordPress フック:** `delete_{$taxonomy}`
**使用場面:** タームが削除されたときにクリーンアップを実行する場合。

```php
use WpPack\Component\Taxonomy\Attribute\DeleteTermAction;

class TermDeletionHandler
{
    #[DeleteTermAction(taxonomy: 'product_category')]
    public function onCategoryDeleted(int $term_id, int $tt_id, \WP_Term $deleted_term): void
    {
        $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        if ($thumbnail_id) {
            wp_delete_attachment($thumbnail_id, true);
        }

        wp_cache_delete('product_categories', 'wppack');
    }
}
```

### #[PreGetTermsAction]

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
    }
}
```

### #[TermsClausesFilter]

**WordPress フック:** `terms_clauses`
**使用場面:** タームクエリの SQL 句を変更する場合。

```php
use WpPack\Component\Taxonomy\Attribute\TermsClausesFilter;

class TermQueryOptimizer
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    #[TermsClausesFilter]
    public function optimizeTermQueries(array $clauses, array $taxonomies, array $args): array
    {
        if (!empty($args['orderby_menu_order'])) {
            $clauses['join'] .= " LEFT JOIN {$this->db->termmeta} AS tm_order
                                  ON (t.term_id = tm_order.term_id
                                  AND tm_order.meta_key = 'menu_order')";
            $clauses['orderby'] = "CAST(tm_order.meta_value AS SIGNED) ASC, " . $clauses['orderby'];
        }

        return $clauses;
    }
}
```

### #[TermLinkFilter]

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
            $ancestors = array_reverse(get_ancestors($term->term_id, $taxonomy, 'taxonomy'));
            $slugs = array_map(fn($id) => get_term($id, $taxonomy)->slug, $ancestors);
            $slugs[] = $term->slug;
            $termlink = home_url('/products/category/' . implode('/', $slugs) . '/');
        }

        return $termlink;
    }
}
```

### #[GetTermsFilter]

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
                $term->thumbnail_url = wp_get_attachment_url(
                    get_term_meta($term->term_id, 'thumbnail_id', true)
                ) ?: '';
            }
        }

        return $terms;
    }
}
```

## Hook アトリビュートリファレンス

```php
// 登録
#[RegisteredTaxonomyAction(priority?: int = 10)]      // タクソノミー登録後

// ターム管理
#[CreateTermAction(taxonomy: string, priority?: int = 10)]   // ターム作成時
#[EditTermAction(taxonomy: string, priority?: int = 10)]     // ターム編集時
#[DeleteTermAction(taxonomy: string, priority?: int = 10)]   // ターム削除時
#[CreatedTermAction(priority?: int = 10)]                    // ターム作成後（全タクソノミー）
#[EditedTermAction(priority?: int = 10)]                     // ターム編集後（全タクソノミー）

// タームクエリ
#[PreGetTermsAction(priority?: int = 10)]             // タームクエリ実行前
#[TermsClausesFilter(priority?: int = 10)]            // タームクエリ SQL の変更
#[GetTermsFilter(priority?: int = 10)]                // 取得されたタームのフィルタリング

// ターム表示
#[TermLinkFilter(priority?: int = 10)]                // タームパーマリンクの変更
#[GetTheTermsFilter(priority?: int = 10)]             // 投稿タームのフィルタリング
#[TermNameFilter(priority?: int = 10)]                // ターム名のフィルタリング
```

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress タクソノミー登録用

### 推奨
- **Cache コンポーネント** - タクソノミーデータのキャッシュ用
