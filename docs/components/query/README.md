# Query Component

**Package:** `wppack/query`
**Namespace:** `WpPack\Component\Query\`
**Layer:** Abstraction

Query コンポーネントは、WordPress のネイティブクエリ（`WP_Query`、`WP_User_Query`、`WP_Term_Query`）に対する型安全で流暢なラッパーを提供します。

## Query コンポーネント vs Database コンポーネント

| | Query | Database |
|---|---|---|
| **対象** | WordPress ネイティブクエリ | カスタムテーブル |
| **ラップ対象** | `WP_Query`, `WP_User_Query`, `WP_Term_Query` | `$wpdb` |
| **用途** | 投稿・ユーザー・タームの検索 | カスタムテーブルへの CRUD 操作 |
| **使い分け** | WordPress 標準のデータ（投稿、ページ、カスタム投稿タイプ、ユーザー、タクソノミー）を扱う場合 | `dbDelta()` で作成したカスタムテーブルを扱う場合 |

> [!TIP]
> WordPress の `wp_posts`、`wp_users`、`wp_terms` テーブルに対するクエリには **Query コンポーネント**を使用してください。独自に作成したカスタムテーブルに対するクエリには **Database コンポーネント**を使用してください。

## インストール

```bash
composer require wppack/query
```

## このコンポーネントの機能

- **DI ファースト** — `QueryFactory` をコンストラクタ注入して使用
- **流暢なクエリビルダー** — チェーン可能なメソッドで可読性の高いコード
- **Doctrine ORM 式の where** — `where()` / `andWhere()` / `orWhere()` で meta_query を構築
- **専用メソッド** — taxonomy、author、date 等は専用メソッドで明示的に指定
- **統一インターフェース** — 投稿・ユーザー・タームに対する一貫した API
- **実行メソッドで取得形式を選択** — `get()`, `first()`, `getIds()`, `count()`, `exists()`

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress — 複雑な配列構文
$query = new WP_Query([
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'featured',
            'value' => '1',
            'compare' => '='
        ],
        [
            'key' => 'price',
            'value' => 100,
            'compare' => '<='
        ]
    ],
    'tax_query' => [
        [
            'taxonomy' => 'product_category',
            'field' => 'slug',
            'terms' => ['electronics']
        ]
    ],
    'orderby' => 'meta_value_num',
    'meta_key' => 'price',
    'order' => 'ASC'
]);

$products = [];
while ($query->have_posts()) {
    $query->the_post();
    $products[] = get_post();
}
wp_reset_postdata();
```

### After（WpPack）

```php
use WpPack\Component\Query\QueryFactory;

public function __construct(private readonly QueryFactory $query) {}

$result = $this->query->posts('product')
    ->published()
    ->where('featured', true)
    ->andWhere('price', 100, '<=')
    ->taxonomy('product_category', ['electronics'], TaxField::Slug)
    ->orderByMeta('price', Order::Asc, MetaType::Numeric)
    ->limit(10)
    ->get();

foreach ($result as $post) {
    // $post は WP_Post
}
```

## エントリポイント: QueryFactory

`QueryFactory` を DI コンテナから注入して使用します:

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
            ->limit(10)
            ->get();
    }
}
```

### ファクトリメソッド

```php
// 投稿クエリ
$this->query->posts('product');          // post_type 指定
$this->query->posts(['post', 'page']);   // 複数 post_type
$this->query->posts();                   // post_type 未指定

// ユーザークエリ
$this->query->users();

// タームクエリ
$this->query->terms('category');         // taxonomy 指定
$this->query->terms(['category', 'post_tag']); // 複数 taxonomy
$this->query->terms();                  // taxonomy 未指定
```

## where / andWhere / orWhere（meta_query）

`where()` は meta 条件の追加。Doctrine ORM の `where`/`andWhere`/`orWhere` に相当します。

### AND 条件

```php
// where() と andWhere() は同等
$this->query->posts('product')
    ->where('featured', true)              // meta: featured = 1
    ->andWhere('price', 100, '<=')         // AND meta: price <= 100
    ->get();

// → meta_query: ['relation' => 'AND', {featured=1}, {price<=100}]
```

### OR 条件

```php
$this->query->posts('product')
    ->orWhere('featured', true)
    ->orWhere('on_sale', true)
    ->get();

// → meta_query: ['relation' => 'OR', {featured=1}, {on_sale=1}]
```

### 複合条件（ネスト）

```php
use WpPack\Component\Query\Condition\MetaConditionGroup;

// WHERE status = 'active' AND (featured = 1 OR on_sale = 1)
$this->query->posts('product')
    ->where('status', 'active')
    ->andWhere(function (MetaConditionGroup $group): void {
        $group->where('featured', true)
              ->orWhere('on_sale', true);
    })
    ->get();
```

### 比較演算子

文字列または `MetaCompare` enum で指定:

```php
use WpPack\Component\Query\Enum\MetaCompare;

->where('price', 100, '<=')                    // 文字列
->where('price', 100, MetaCompare::LessThanOrEqual)  // enum
->where('stock', [10, 50], MetaCompare::Between)
->whereExists('thumbnail')                     // EXISTS
->whereNotExists('deleted_at')                 // NOT EXISTS
```

### 型指定

```php
use WpPack\Component\Query\Enum\MetaType;

->where('price', 100, '<=', MetaType::Numeric)
->where('event_date', '2024-01-01', '>=', MetaType::Date)
```

## 投稿クエリ（PostQueryBuilder）

### SET パラメータ（上書き）

```php
$this->query->posts('product')
    ->type('product')           // post_type（上書き）
    ->status('publish')         // post_status（上書き）
    ->published()               // status('publish') のショートハンド
    ->draft()                   // status('draft') のショートハンド
    ->author(5)                 // author = 5
    ->author([5, 10])           // author__in = [5, 10]
    ->authorNotIn([3])          // author__not_in = [3]
    ->id(42)                    // p = 42
    ->id([1, 2, 3])             // post__in = [1, 2, 3]
    ->notIn([99])               // post__not_in = [99]
    ->parent(10)                // post_parent = 10
    ->parentIn([10, 20])        // post_parent__in = [10, 20]
    ->search('keyword')         // s = 'keyword'
    ->limit(10)                 // posts_per_page = 10
    ->page(2)                   // paged = 2
    ->offset(5)                 // offset = 5
    ->orderBy('date', 'desc')   // orderby + order
    ->after('2024-01-01')       // date_query
    ->before('2024-12-31')      // date_query
```

### タクソノミー条件

```php
use WpPack\Component\Query\Enum\TaxField;
use WpPack\Component\Query\Enum\TaxOperator;

// 単一条件
->taxonomy('product_category', ['electronics'], TaxField::Slug)

// 複数条件（デフォルト AND）
->taxonomy('product_category', ['electronics'])
->taxonomy('product_tag', ['sale'])

// OR に変更
->taxonomy('product_category', ['electronics'])
->taxonomy('product_tag', ['sale'])
->taxRelation('OR')
```

### ソート

```php
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Enum\MetaType;

->orderBy('date', Order::Desc)        // 通常のソート
->orderBy('title', 'ASC')             // 文字列でも指定可
->orderByMeta('price', Order::Asc, MetaType::Numeric)  // メタ値でソート
```

### パフォーマンスフラグ

```php
$this->query->posts('product')
    ->published()
    ->noMetaCache()    // update_post_meta_cache = false
    ->noTermCache()    // update_post_term_cache = false
    ->withoutCount()   // no_found_rows = true（total/totalPages が 0 になる）
    ->limit(10)
    ->get();
```

### エスケープハッチ

```php
->arg('custom_key', $value)  // 任意の WP_Query arg を直接設定
```

### 実行メソッド

```php
$builder = $this->query->posts('product')->published()->limit(10)->page(2);

$result = $builder->get();       // PostQueryResult（WP_Post[] + pagination）
$post   = $builder->first();     // ?WP_Post（1件取得）
$ids    = $builder->getIds();    // list<int>（ID のみ、高速）
$count  = $builder->count();     // int（件数のみ、オブジェクト生成なし）
$exists = $builder->exists();    // bool（存在チェック、最速）
$args   = $builder->toArray();   // array（WP_Query args をデバッグ用に出力）
```

### PostQueryResult

```php
$result = $builder->get();

$result->all();          // list<WP_Post>
$result->first();        // ?WP_Post
$result->isEmpty();      // bool
$result->count();        // int（ページ内件数）
$result->ids();          // list<int>
$result->total;          // int（全件数）
$result->totalPages;     // int
$result->currentPage;    // int
$result->hasNextPage();  // bool
$result->wpQuery();      // WP_Query（エスケープハッチ）
foreach ($result as $post) { ... }  // IteratorAggregate
```

## ユーザークエリ（UserQueryBuilder）

```php
$result = $this->query->users()
    ->role('author')
    ->where('company', 'Acme')
    ->hasPublishedPosts()
    ->orderBy('display_name')
    ->limit(10)
    ->get();

// 実行メソッド
$result = $builder->get();       // UserQueryResult
$user   = $builder->first();     // ?WP_User
$ids    = $builder->getIds();    // list<int>
$count  = $builder->count();     // int
$exists = $builder->exists();    // bool
```

## タームクエリ（TermQueryBuilder）

```php
$result = $this->query->terms('category')
    ->hideEmpty()
    ->where('featured', true)
    ->orderBy('count', Order::Desc)
    ->limit(10)
    ->get();

// 実行メソッド
$result = $builder->get();       // TermQueryResult
$term   = $builder->first();     // ?WP_Term
$ids    = $builder->getIds();    // list<int>
$count  = $builder->count();     // int
$exists = $builder->exists();    // bool
```

## クイックスタート

### ブログクエリ

```php
<?php

use WpPack\Component\Query\QueryFactory;

class BlogService
{
    public function __construct(
        private readonly QueryFactory $query,
    ) {}

    public function getRecentPosts(int $limit = 10): PostQueryResult
    {
        return $this->query->posts('post')
            ->published()
            ->orderBy('date', Order::Desc)
            ->limit($limit)
            ->get();
    }

    public function getFeaturedPosts(string $category, int $limit = 5): PostQueryResult
    {
        return $this->query->posts('post')
            ->published()
            ->where('featured', true)
            ->taxonomy('category', [$category], TaxField::Slug)
            ->orderBy('date', Order::Desc)
            ->limit($limit)
            ->get();
    }

    public function getAuthorPosts(int $authorId, int $limit = 20): PostQueryResult
    {
        return $this->query->posts('post')
            ->published()
            ->author($authorId)
            ->orderBy('date', Order::Desc)
            ->limit($limit)
            ->get();
    }

    public function searchPosts(string $searchTerm, int $limit = 15): PostQueryResult
    {
        return $this->query->posts('post')
            ->published()
            ->search($searchTerm)
            ->limit($limit)
            ->get();
    }
}
```

### EC クエリ

```php
<?php

use WpPack\Component\Query\Condition\MetaConditionGroup;
use WpPack\Component\Query\QueryFactory;

class ProductService
{
    public function __construct(
        private readonly QueryFactory $query,
    ) {}

    public function getProducts(array $filters = []): PostQueryResult
    {
        $builder = $this->query->posts('product')
            ->published()
            ->where('status', 'active');

        if (isset($filters['min_price'])) {
            $builder->andWhere('price', $filters['min_price'], '>=');
        }

        if (isset($filters['max_price'])) {
            $builder->andWhere('price', $filters['max_price'], '<=');
        }

        if (!empty($filters['categories'])) {
            $builder->taxonomy('product_category', $filters['categories'], TaxField::Slug);
        }

        if ($filters['in_stock_only'] ?? false) {
            $builder->andWhere('stock_quantity', 0, '>');
        }

        return $builder
            ->orderBy($filters['sort_by'] ?? 'date', $filters['order'] ?? 'desc')
            ->limit($filters['limit'] ?? 24)
            ->get();
    }

    public function getProductsWithComplexConditions(): PostQueryResult
    {
        // WHERE status = 'active' AND (featured = 1 OR on_sale = 1)
        return $this->query->posts('product')
            ->published()
            ->where('status', 'active')
            ->andWhere(function (MetaConditionGroup $group): void {
                $group->where('featured', true)
                      ->orWhere('on_sale', true);
            })
            ->limit(24)
            ->get();
    }
}
```

### ユーザー・著者クエリ

```php
<?php

use WpPack\Component\Query\QueryFactory;

class UserService
{
    public function __construct(
        private readonly QueryFactory $query,
    ) {}

    public function getActiveAuthors(int $limit = 10): UserQueryResult
    {
        return $this->query->users()
            ->role(['author', 'editor', 'administrator'])
            ->hasPublishedPosts()
            ->orderBy('display_name')
            ->limit($limit)
            ->get();
    }
}
```

### タクソノミー・タームクエリ

```php
<?php

use WpPack\Component\Query\QueryFactory;

class CategoryService
{
    public function __construct(
        private readonly QueryFactory $query,
    ) {}

    public function getPopularCategories(int $limit = 10): TermQueryResult
    {
        return $this->query->terms('category')
            ->hideEmpty()
            ->orderBy('count', Order::Desc)
            ->limit($limit)
            ->get();
    }
}
```

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Query/Subscriber/`

Query コンポーネントは、WordPress のクエリ機能に対する Named Hook アトリビュートを提供します。すべての Named Hook アトリビュートはオプションの `priority` パラメータ（デフォルト: `10`）を受け取ります。

### クエリ変更フック

#### #[PreGetPostsAction(priority?: int = 10)]

**WordPress フック:** `pre_get_posts`
クエリ実行前にクエリパラメータを変更します。

```php
use WpPack\Component\Query\Attribute\Action\PreGetPostsAction;

class QueryManager
{
    #[PreGetPostsAction(priority: 10)]
    public function modifyMainQuery(\WP_Query $query): void
    {
        if (!$query->is_main_query()) {
            return;
        }

        if ($query->is_home()) {
            $query->set('post_type', ['post', 'product', 'event']);
        }
    }
}
```

#### #[PostsWhereFilter(priority?: int = 10)]

**WordPress フック:** `posts_where`
クエリの WHERE 句を変更します。

```php
use WpPack\Component\Query\Attribute\Filter\PostsWhereFilter;

class QueryWhereModifier
{
    #[PostsWhereFilter(priority: 10)]
    public function customizeWhereClause(string $where, \WP_Query $query): string
    {
        // カスタム WHERE 句の追加
        return $where;
    }
}
```

### Hook アトリビュートリファレンス

すべてのアトリビュートは `priority?: int = 10` パラメータを受け取ります。

```php
// クエリ設定
#[ParseQueryAction(priority?: int = 10)]              // クエリ解析後
#[PreGetPostsAction(priority?: int = 10)]             // クエリ実行前

// クエリ変更
#[PostsWhereFilter(priority?: int = 10)]              // WHERE 句
#[PostsJoinFilter(priority?: int = 10)]               // JOIN 句
#[PostsOrderbyFilter(priority?: int = 10)]            // ORDER BY 句
#[PostsFieldsFilter(priority?: int = 10)]             // SELECT フィールド
#[PostsGroupbyFilter(priority?: int = 10)]            // GROUP BY 句
#[PostsDistinctFilter(priority?: int = 10)]           // DISTINCT 句

// クエリ結果
#[ThePostsFilter(priority?: int = 10)]                // 投稿オブジェクトの変更
#[PostsResultsFilter(priority?: int = 10)]            // 生のデータベース結果
#[FoundPostsFilter(priority?: int = 10)]              // 検出された投稿の総数
#[FoundPostsQueryFilter(priority?: int = 10)]         // カウント SQL クエリ

// クエリ SQL
#[PostsRequestFilter(priority?: int = 10)]            // 最終 SQL クエリ
#[PostsClausesFilter(priority?: int = 10)]            // すべての SQL 句
#[PostsWherePagedFilter(priority?: int = 10)]         // ページング付き WHERE

// 検索・フィルタリング
#[PostsSearchFilter(priority?: int = 10)]             // 検索 SQL
#[PostsSearchOrderbyFilter(priority?: int = 10)]      // 検索並び順
#[PostsSearchColumnsFilter(priority?: int = 10)]      // 検索カラム

// パフォーマンス・キャッシュ
#[UpdatePostMetaCacheFilter(priority?: int = 10)]     // メタキャッシュ更新
#[UpdatePostTermCacheFilter(priority?: int = 10)]     // タームキャッシュ更新
#[PostsCacheResultsFilter(priority?: int = 10)]       // キャッシュ結果
```

## 2層設計（PostType との関係）

```
PostType (高レベル)  : Product::query()->where('price', 50, '<=')->get()  → Product[]
                       #[Meta] フィールドを知っているので where() = meta condition
                       ↓ 内部で PostQueryBuilder を利用
Query    (低レベル)  : $this->query->posts('product')->where('price', 50, '<=')->get()  → WP_Post[]
                       where/andWhere/orWhere = meta_query（Doctrine ORM 式）
                       taxonomy/author 等は専用メソッド
```

## このコンポーネントの使用場面

**最適な用途:**
- 複数条件を持つ複雑な投稿クエリ
- DI を活用したテスタブルなクエリ構築
- カスタム投稿タイプを多用するプロジェクト
- 統一インターフェースで投稿・ユーザー・タームを検索

**別の方法を検討:**
- 基本的な `WP_Query` で十分なシンプルなクエリ
- カスタムテーブルへのクエリ（→ Database コンポーネントを使用）
- 型付きオブジェクトが必要な場合（→ PostType コンポーネントと組み合わせ）

## 依存関係

### 必須
- **なし** — WordPress コアクエリで動作

### 推奨
- **PostType Component** — 高レベルの型付きクエリ
- **Hook Component** — Named Hook アトリビュート
