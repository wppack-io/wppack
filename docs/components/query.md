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

> **使い分けの指針:** WordPress の `wp_posts`、`wp_users`、`wp_terms` テーブルに対するクエリには **Query コンポーネント**を使用してください。独自に作成したカスタムテーブルに対するクエリには **Database コンポーネント**を使用してください。

## インストール

```bash
composer require wppack/query
```

## このコンポーネントの機能

- **流暢なクエリビルダー** — チェーン可能なメソッドで可読性の高いコード
- **型安全なクエリ構築** — 一般的なクエリエラーの防止
- **キャッシュ統合** — パフォーマンス向上のためのクエリキャッシュ
- **投稿タイプオブジェクトマッピング** — 強い型付けのオブジェクトを返却
- **統一インターフェース** — 投稿・ユーザー・タームに対する一貫した API

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
// WpPack — 流暢で読みやすいクエリ
use WpPack\Component\Query\Query;

$products = Query::posts('product')
    ->published()
    ->where('featured', true)
    ->where('price', '<=', 100)
    ->whereTerms('product_category', ['electronics'])
    ->orderBy('price', 'asc')
    ->limit(10)
    ->get(); // Product オブジェクトを返却（WP_Post ではない）
```

## コア機能

### 流暢なクエリビルダー

チェーン可能なメソッドで複雑なクエリを構築できます:

```php
$query = Query::posts('event')
    ->published()
    ->where('event_date', '>=', date('Y-m-d'))
    ->whereIn('event_type', ['workshop', 'conference'])
    ->whereTerms('event_category', ['technology'])
    ->orderBy('event_date', 'asc')
    ->limit(20);
```

### 型安全な結果

汎用的な `WP_Post` ではなく、強い型付けのオブジェクトを取得できます:

```php
$products = Query::posts('product')->get(); // Product[] オブジェクトを返却
$events = Query::posts('event')->get();     // Event[] オブジェクトを返却
$posts = Query::posts('post')->get();       // Post[] オブジェクトを返却
```

### クエリキャッシュ

自動無効化付きのクエリキャッシュ:

```php
$products = Query::posts('product')
    ->cache(3600) // 1時間キャッシュ
    ->tags(['products', 'featured'])
    ->get();
```

### マルチタイプクエリ

WordPress の各オブジェクトタイプに対する統一インターフェース:

```php
// 投稿・カスタム投稿タイプ
$posts = Query::posts('post')->published()->recent()->get();

// ユーザー
$authors = Query::users()->role('author')->orderBy('display_name')->get();

// ターム
$categories = Query::terms('category')->hideEmpty()->orderBy('count', 'desc')->get();
```

## クイックスタート

### ブログクエリ

```php
<?php
use WpPack\Component\Query\Query;

class BlogService
{
    public function getRecentPosts(int $limit = 10): array
    {
        return Query::posts('post')
            ->published()
            ->hasThumbnail()
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getFeaturedPosts(string $category, int $limit = 5): array
    {
        return Query::posts('post')
            ->published()
            ->where('featured', true)
            ->whereTerms('category', [$category])
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getAuthorPosts(int $authorId, int $limit = 20): array
    {
        return Query::posts('post')
            ->published()
            ->where('post_author', $authorId)
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function searchPosts(string $searchTerm, int $limit = 15): array
    {
        return Query::posts('post')
            ->published()
            ->search($searchTerm)
            ->orderBy('relevance', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRelatedPosts(int $postId, int $limit = 4): array
    {
        $tags = wp_get_post_tags($postId, ['fields' => 'slugs']);

        if (empty($tags)) {
            return [];
        }

        return Query::posts('post')
            ->published()
            ->whereTerms('post_tag', $tags)
            ->where('ID', '!=', $postId)
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();
    }
}
```

### EC クエリ

```php
<?php
class ProductService
{
    public function getProducts(array $filters = []): array
    {
        $query = Query::posts('product')
            ->published()
            ->where('status', 'active');

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['categories'])) {
            $query->whereTerms('product_category', $filters['categories']);
        }

        if (!empty($filters['brands'])) {
            $query->whereTerms('product_brand', $filters['brands']);
        }

        if ($filters['in_stock_only'] ?? false) {
            $query->where('stock_quantity', '>', 0);
        }

        if ($filters['featured_first'] ?? false) {
            $query->orderBy('featured', 'desc')
                  ->orderBy('date', 'desc');
        } else {
            $query->orderBy($filters['sort_by'] ?? 'date', $filters['order'] ?? 'desc');
        }

        return $query->limit($filters['limit'] ?? 24)->get();
    }

    public function getBestSellers(int $limit = 8): array
    {
        return Query::posts('product')
            ->published()
            ->where('status', 'active')
            ->where('sales_count', '>', 0)
            ->orderBy('sales_count', 'desc')
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->cache(1800)
            ->tags(['products', 'bestsellers'])
            ->get();
    }
}
```

### ユーザー・著者クエリ

```php
<?php
class UserService
{
    public function getActiveAuthors(int $limit = 10): array
    {
        return Query::users()
            ->role(['author', 'editor', 'administrator'])
            ->where('user_status', 0)
            ->hasPublishedPosts()
            ->orderBy('display_name', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getTopContributors(int $limit = 5): array
    {
        return Query::users()
            ->role(['author', 'editor'])
            ->hasPublishedPosts(10)
            ->orderBy('post_count', 'desc')
            ->limit($limit)
            ->cache(3600)
            ->get();
    }
}
```

### タクソノミー・タームクエリ

```php
<?php
class CategoryService
{
    public function getPopularCategories(int $limit = 10): array
    {
        return Query::terms('category')
            ->hideEmpty()
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getProductCategoryTree(): array
    {
        return Query::terms('product_category')
            ->hideEmpty()
            ->orderBy('name', 'asc')
            ->get();
    }
}
```

### クエリスコープ

再利用可能なクエリロジックを定義できます:

```php
class ProductQuery extends AbstractQuery
{
    public function featured(): self
    {
        return $this->where('featured', true);
    }

    public function inStock(): self
    {
        return $this->where('stock_quantity', '>', 0);
    }

    public function priceRange(float $min, float $max): self
    {
        return $this->where('price', '>=', $min)
                    ->where('price', '<=', $max);
    }
}

// 使用例
$products = ProductQuery::make()
    ->featured()
    ->inStock()
    ->priceRange(10, 100)
    ->get();
```

## キャッシュとパフォーマンス

```php
<?php
class OptimizedBlogService
{
    public function getHomepageContent(): array
    {
        $featuredPosts = Query::posts('post')
            ->published()
            ->where('featured', true)
            ->limit(3)
            ->cache(7200)
            ->tags(['homepage', 'featured-posts'])
            ->get();

        $recentPosts = Query::posts('post')
            ->published()
            ->where('featured', false)
            ->limit(6)
            ->cache(1800)
            ->tags(['homepage', 'recent-posts'])
            ->get();

        $categories = Query::terms('category')
            ->hideEmpty()
            ->orderBy('count', 'desc')
            ->limit(8)
            ->cache(21600)
            ->tags(['homepage', 'categories'])
            ->get();

        return [
            'featured_posts' => $featuredPosts,
            'recent_posts' => $recentPosts,
            'categories' => $categories
        ];
    }

    public function onPostSave(int $postId): void
    {
        Cache::invalidateTag('homepage');

        if (get_post_meta($postId, 'featured', true)) {
            Cache::invalidateTag('featured-posts');
        } else {
            Cache::invalidateTag('recent-posts');
        }

        Cache::invalidateTag('categories');
    }
}
```

## Named Hook アトリビュート

Query コンポーネントは、WordPress のクエリ機能に対する Named Hook アトリビュートを提供します。すべての Named Hook アトリビュートはオプションの `priority` パラメータ（デフォルト: `10`）を受け取ります。

### クエリ変更フック

#### #[PreGetPostsAction(priority?: int = 10)]

**WordPress フック:** `pre_get_posts`
クエリ実行前にクエリパラメータを変更します。

```php
use WpPack\Component\Hook\Attribute\PreGetPostsAction;
use WpPack\Component\Query\QueryModifier;

class QueryManager
{
    public function __construct(private QueryModifier $modifier) {}

    #[PreGetPostsAction(priority: 10)]
    public function modifyMainQuery(\WP_Query $query): void
    {
        if (!$query->is_main_query()) {
            return;
        }

        if ($query->is_search()) {
            $this->enhanceSearchQuery($query);
        }

        if ($query->is_archive()) {
            $this->optimizeArchiveQuery($query);
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
use WpPack\Component\Query\Attribute\PostsWhereFilter;

class QueryWhereModifier
{
    #[PostsWhereFilter(priority: 10)]
    public function customizeWhereClause(string $where, \WP_Query $query): string
    {
        global $wpdb;

        if ($date_after = $query->get('wppack_date_after')) {
            $where .= $wpdb->prepare(
                " AND {$wpdb->posts}.post_date > %s",
                $date_after
            );
        }

        return $where;
    }
}
```

#### #[PostsJoinFilter(priority?: int = 10)]

**WordPress フック:** `posts_join`
クエリにカスタム JOIN 句を追加します。

```php
use WpPack\Component\Query\Attribute\PostsJoinFilter;

class QueryJoinManager
{
    #[PostsJoinFilter(priority: 10)]
    public function addCustomJoins(string $join, \WP_Query $query): string
    {
        global $wpdb;

        if ($query->get('orderby') === 'wppack_popularity') {
            $join .= " LEFT JOIN {$wpdb->prefix}wppack_post_stats AS stats
                      ON {$wpdb->posts}.ID = stats.post_id";
        }

        return $join;
    }
}
```

#### #[PostsOrderbyFilter(priority?: int = 10)]

**WordPress フック:** `posts_orderby`
ORDER BY 句を変更します。

```php
use WpPack\Component\Query\Attribute\PostsOrderbyFilter;

class QueryOrderManager
{
    #[PostsOrderbyFilter(priority: 10)]
    public function customizeOrderBy(string $orderby, \WP_Query $query): string
    {
        if ($query->get('orderby') === 'wppack_popularity') {
            $order = $query->get('order', 'DESC');
            return "stats.view_count {$order}, stats.comment_count {$order}";
        }

        return $orderby;
    }
}
```

### クエリ結果フック

#### #[ThePostsFilter(priority?: int = 10)]

**WordPress フック:** `the_posts`
クエリ後に投稿配列を変更します。

```php
use WpPack\Component\Query\Attribute\ThePostsFilter;

class QueryResultProcessor
{
    #[ThePostsFilter(priority: 10)]
    public function processQueryResults(array $posts, \WP_Query $query): array
    {
        if ($query->get('wppack_include_computed')) {
            $posts = $this->addComputedFields($posts);
        }

        if (!empty($posts)) {
            $this->preloadMetaData($posts);
        }

        return $posts;
    }
}
```

#### #[FoundPostsFilter(priority?: int = 10)]

**WordPress フック:** `found_posts`
検出された投稿の総数を変更します。

```php
use WpPack\Component\Query\Attribute\FoundPostsFilter;

class QueryCountManager
{
    #[FoundPostsFilter(priority: 10)]
    public function adjustFoundPosts(int $found_posts, \WP_Query $query): int
    {
        $max_results = $query->get('wppack_max_results', 1000);
        if ($found_posts > $max_results) {
            $found_posts = $max_results;
        }

        return $found_posts;
    }
}
```

#### #[QueryVarsFilter(priority?: int = 10)]

**WordPress フック:** `query_vars`
カスタムクエリ変数を登録します。

```php
use WpPack\Component\Query\Attribute\QueryVarsFilter;

class QueryVariableManager
{
    #[QueryVarsFilter(priority: 10)]
    public function registerCustomQueryVars(array $query_vars): array
    {
        $custom_vars = [
            'wppack_orderby',
            'wppack_filter',
            'wppack_date_after',
            'wppack_date_before',
            'wppack_popularity',
        ];

        return array_merge($query_vars, $custom_vars);
    }
}
```

### Hook アトリビュートリファレンス

すべてのアトリビュートは `priority?: int = 10` パラメータを受け取ります。

```php
// クエリ設定（WP_Query）
#[QueryVarsFilter(priority?: int = 10)]              // クエリ変数の登録
#[ParseQueryAction(priority?: int = 10)]              // クエリ解析後
#[ParseRequestAction(priority?: int = 10)]            // リクエスト解析

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
#[PostSearchColumnsFilter(priority?: int = 10)]       // 検索カラム

// パフォーマンス・キャッシュ
#[UpdatePostMetaCacheFilter(priority?: int = 10)]     // メタキャッシュ更新
#[UpdatePostTermCacheFilter(priority?: int = 10)]     // タームキャッシュ更新
```

## このコンポーネントの使用場面

**最適な用途:**
- 複数条件を持つ複雑な投稿クエリ
- キャッシュによる高パフォーマンスが必要なアプリケーション
- カスタム投稿タイプを多用するプロジェクト
- 型安全なクエリ結果が必要なアプリケーション

**別の方法を検討:**
- 基本的な `WP_Query` で十分なシンプルなクエリ
- カスタムテーブルへのクエリ（→ Database コンポーネントを使用）

## サポートされるクエリタイプ

### 投稿クエリ
- 投稿、固定ページ、カスタム投稿タイプ、添付ファイル、リビジョン

### ユーザークエリ
- ユーザーロール、ユーザーメタ

### タームクエリ
- カテゴリー、タグ、カスタムタクソノミー、ターム階層、タームメタデータ

## 依存関係

### 必須
- **なし** — WordPress コアクエリで動作

### 推奨
- **PostType Component** — 強い型付けの投稿オブジェクト
- **Cache Component** — クエリ結果のキャッシュ
- **Hook Component** — クエリイベントハンドリング
