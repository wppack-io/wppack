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
            ->status(PostStatus::Publish)
            ->where('m.featured = :feat')
            ->andWhere('m.price:numeric <= :price')
            ->andWhere('t.product_category:slug IN :cats')
            ->setParameter('feat', true)
            ->setParameter('price', 100)
            ->setParameter('cats', ['electronics'])
            ->orderByMeta('price', Order::Asc, MetaType::Numeric)
            ->limit(10)
            ->get();
    }
}
```

## 主な機能

- **DI ファースト** — `QueryFactory` をコンストラクタ注入
- **WQL（WordPress Query Language）** — `where('(m.a = :a OR m.b = :b) AND m.c = :c')` で複合条件を単一文字列で表現
- **プレフィックスルーティング** — `m.` で meta_query、`t.` / `tax.` / `taxonomy.` で tax_query を自動ルーティング
- **実行メソッド** — `get()`, `first()`, `getIds()`, `count()`, `exists()`
- **Named Hook アトリビュート** — `PreGetPostsAction`, `PostsWhereFilter` 等

## ドキュメント

詳細は [docs/components/query/](../../../docs/components/query/) を参照してください。
