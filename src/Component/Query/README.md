# Query Component

A type-safe, fluent query builder for WordPress native queries (`WP_Query`, `WP_User_Query`, `WP_Term_Query`).

## Installation

```bash
composer require wppack/query
```

## Usage

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

## Key Features

- **DI First** -- Inject `QueryFactory` via constructor
- **WQL (WordPress Query Language)** -- Express compound conditions in a single string with `where('(m.a = :a OR m.b = :b) AND m.c = :c')`
- **Prefix Routing** -- `m.` auto-routes to meta_query, `t.` / `tax.` / `taxonomy.` auto-routes to tax_query
- **Execution Methods** -- `get()`, `first()`, `getIds()`, `count()`, `exists()`
- **Named Hook Attributes** -- `PreGetPostsAction`, `PostsWhereFilter`, etc.

## Documentation

For details, see [docs/components/query/](../../../docs/components/query/).
