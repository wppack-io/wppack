# Query Component

WordPress のネイティブクエリ（`WP_Query`、`WP_User_Query`、`WP_Term_Query`）に対する型安全で流暢なクエリビルダー。

## インストール

```bash
composer require wppack/query
```

## 使い方

```php
use WpPack\Component\Query\QueryFactory;

class ProductService
{
    public function __construct(
        private readonly QueryFactory $query,
    ) {}

    public function getFeaturedProducts(): PostQueryResult
    {
        return $this->query->posts('product')
            ->published()
            ->where('featured', true)
            ->andWhere('price', 100, '<=')
            ->taxonomy('product_category', ['electronics'], TaxField::Slug)
            ->orderByMeta('price', Order::Asc, MetaType::Numeric)
            ->limit(10)
            ->get();
    }
}
```

## 主な機能

- **DI ファースト** — `QueryFactory` をコンストラクタ注入
- **Doctrine ORM 式の where** — `where()` / `andWhere()` / `orWhere()` で meta_query を構築
- **専用メソッド** — taxonomy、author、date 等は専用メソッドで明示的に指定
- **実行メソッド** — `get()`, `first()`, `getIds()`, `count()`, `exists()`
- **Named Hook アトリビュート** — `PreGetPostsAction`, `PostsWhereFilter` 等

## ドキュメント

詳細は [docs/components/query/](../../../docs/components/query/) を参照してください。
