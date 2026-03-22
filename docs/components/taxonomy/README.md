# Taxonomy コンポーネント

**パッケージ:** `wppack/taxonomy`
**名前空間:** `WpPack\Component\Taxonomy\`
**レイヤー:** Application

WordPress のタクソノミー登録関数 `register_taxonomy()` をアトリビュートでラップし、タームの CRUD フックを Named Hook アトリビュートとして提供するコンポーネントです。

> [!NOTE]
> このコンポーネントは設計段階です。Repository（`TermRepository`）は実装済みです。タクソノミー登録・Hook アトリビュート等の機能は設計仕様として参照してください。

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

→ 詳細は [Hook コンポーネント — Taxonomy](../hook/taxonomy.md) を参照してください。

## Repository

`TermRepositoryInterface` / `TermRepository` は、WordPress タームの CRUD 操作、メタデータ操作、オブジェクト-ターム関係操作を提供します。

```php
use WpPack\Component\Taxonomy\TermRepository;
use WpPack\Component\Taxonomy\TermRepositoryInterface;

$repository = new TermRepository();

// タームの取得
$terms = $repository->findAll(['taxonomy' => 'category']);  // list<WP_Term>|WP_Error
$term = $repository->find($termId, 'category');   // WP_Term|null
$term = $repository->findBySlug('my-term', 'category');
$term = $repository->findByName('My Term', 'category');
$termId = $repository->exists('My Term', 'category');  // int|null

// タームの作成・更新・削除
$result = $repository->insert('New Category', 'category', ['slug' => 'new-cat']);
$repository->update($termId, 'category', ['name' => 'Updated Name']);
$repository->delete($termId, 'category');

// メタデータ操作
$repository->addMeta($termId, 'custom_key', 'value');
$value = $repository->getMeta($termId, 'custom_key', single: true);
$repository->updateMeta($termId, 'custom_key', 'new_value');
$repository->deleteMeta($termId, 'custom_key');

// オブジェクト-ターム関係操作
$repository->setObjectTerms($postId, [$termId1, $termId2], 'category');
$repository->addObjectTerms($postId, [$termId3], 'category');
$repository->removeObjectTerms($postId, [$termId1], 'category');
$terms = $repository->getObjectTerms($postId, 'category');
```

## 依存関係

### 推奨
- **Cache コンポーネント** - タクソノミーデータのキャッシュ用
