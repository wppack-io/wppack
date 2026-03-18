# Taxonomy コンポーネント

**パッケージ:** `wppack/taxonomy`
**名前空間:** `WpPack\Component\Taxonomy\`
**レイヤー:** Feature

WordPress のタクソノミー登録関数 `register_taxonomy()` をアトリビュートでラップし、タームの CRUD フックを Named Hook アトリビュートとして提供するコンポーネントです。

> [!WARNING]
> このコンポーネントは設計段階です。ソースコードの実装はまだありません。以下は設計仕様として参照してください。

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

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Taxonomy](./hook/taxonomy.md) を参照してください。

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress タクソノミー登録用

### 推奨
- **Cache コンポーネント** - タクソノミーデータのキャッシュ用
