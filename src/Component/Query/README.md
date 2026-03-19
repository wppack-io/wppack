# Query Component

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=query)](https://codecov.io/github/wppack-io/wppack)

A type-safe, fluent query builder for WordPress native queries (`WP_Query`, `WP_User_Query`, `WP_Term_Query`).

## Installation

```bash
composer require wppack/query
```

## Usage

```php
use WpPack\Component\Query\QueryFactory;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Result\PostQueryResult;

class ProductService
{
    public function __construct(
        private readonly QueryFactory $query,
    ) {}

    public function getFeaturedProducts(): PostQueryResult
    {
        return $this->query->posts()
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->andWhere('m.featured = :feat')
            ->andWhere('m.price:numeric <= :price')
            ->andWhere('t.product_category:slug IN :cats')
            ->setParameters([
                'type' => 'product',
                'status' => 'publish',
                'feat' => true,
                'price' => 100,
                'cats' => ['electronics'],
            ])
            ->orderBy('m.price:numeric', Order::Asc)
            ->setMaxResults(10)
            ->get();
    }
}
```

## Key Features

- **DI First** -- Inject `QueryFactory` via constructor
- **Unified `where()` API** -- All conditions (standard fields, meta, taxonomy) via `where()` / `andWhere()` / `orWhere()`
- **WQL (WordPress Query Language)** -- Express compound conditions in a single string with `where('(m.a = :a OR m.b = :b) AND m.c = :c')`
- **Prefix Routing** -- Each builder maps prefixes to the appropriate query type
- **Doctrine-compatible API** -- `setParameter()`, `setParameters()`, `setMaxResults()`, `setFirstResult()`
- **Execution Methods** -- `get()`, `first()`, `getIds()`, `count()`, `exists()`
- **Named Hook Attributes** -- `PreGetPostsAction`, `PostsWhereFilter`, etc.

## Prefix Reference

Each builder has its own prefix map for `where()` expressions:

### PostQueryBuilder

| Prefix | Resolves to | Description |
|--------|-------------|-------------|
| `m` / `meta` | `meta` | Meta query (`meta_query`) |
| `t` / `tax` / `taxonomy` | `tax` | Taxonomy query (`tax_query`) |
| `p` / `post` | `post` | Standard post fields |

**Standard post fields:**

| Field | Operators | WP_Query arg |
|-------|-----------|-------------|
| `type` | `=`, `IN` | `post_type` |
| `status` | `=`, `IN` | `post_status` |
| `author` | `=`, `IN`, `NOT IN` | `author` / `author__in` / `author__not_in` |
| `id` | `=`, `IN`, `NOT IN` | `p` / `post__in` / `post__not_in` |
| `parent` | `=`, `IN` | `post_parent` / `post_parent__in` |

### UserQueryBuilder

| Prefix | Resolves to | Description |
|--------|-------------|-------------|
| `m` / `meta` | `meta` | Meta query (`meta_query`) |
| `u` / `user` | `user` | Standard user fields |

**Standard user fields:**

| Field | Operators | WP_User_Query arg |
|-------|-----------|-------------------|
| `role` | `=`, `IN`, `NOT IN` | `role` / `role__in` / `role__not_in` |
| `id` | `=`, `IN`, `NOT IN` | `include` / `exclude` |

### TermQueryBuilder

| Prefix | Resolves to | Description |
|--------|-------------|-------------|
| `m` / `meta` | `meta` | Meta query (`meta_query`) |
| `t` / `term` | `term` | Standard term fields |

**Standard term fields:**

| Field | Operators | WP_Term_Query arg |
|-------|-----------|-------------------|
| `taxonomy` | `=`, `IN` | `taxonomy` |
| `id` | `=`, `IN`, `NOT IN` | `include` / `exclude` |
| `slug` | `=`, `IN` | `slug` |
| `parent` | `=` | `parent` |

## Standard Field Constraints

- **OR not supported** -- Standard field conditions cannot be used in `orWhere()` or compound OR expressions. `InvalidArgumentException` is thrown.
- **Limited operators** -- Only `=`, `IN`, and `NOT IN` are supported (varies by field). Other operators throw `InvalidArgumentException`.
- **Fixed field list** -- Unknown fields throw `InvalidArgumentException`.
- **`search`, `date` etc.** -- Use dedicated methods (`search()`, `after()`, `before()`, `date()`).
- **`hideEmpty`, `childOf`, `hasPublishedPosts`** -- Use dedicated methods for boolean/tree operations.

## Examples

```php
// PostQueryBuilder
$builder
    ->where('p.type = :type')
    ->andWhere('p.status = :status')
    ->andWhere('m.price:numeric <= :price')
    ->andWhere('t.category:slug IN :cats')
    ->setParameters([
        'type' => 'product',
        'status' => 'publish',
        'price' => 100,
        'cats' => ['electronics'],
    ]);

// UserQueryBuilder
$builder
    ->where('u.role = :role')
    ->andWhere('m.company = :company')
    ->setParameters(['role' => 'author', 'company' => 'Acme']);

// TermQueryBuilder
$builder
    ->where('t.taxonomy = :tax')
    ->andWhere('m.featured = :feat')
    ->setParameters(['tax' => 'category', 'feat' => true]);

// Pagination (Doctrine-compatible)
$builder->setMaxResults(10)->setFirstResult(20);
```

## Documentation

For details, see [docs/components/query/](../../../docs/components/query/).
