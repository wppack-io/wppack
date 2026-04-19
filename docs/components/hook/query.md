## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Query/Subscriber/`

Query コンポーネントは、WordPress のクエリ機能に対する Named Hook アトリビュートを提供します。すべての Named Hook アトリビュートはオプションの `priority` パラメータ（デフォルト: `10`）を受け取ります。

### クエリ変更フック

#### #[PreGetPostsAction(priority?: int = 10)]

**WordPress フック:** `pre_get_posts`
クエリ実行前にクエリパラメータを変更します。

```php
use WPPack\Component\Hook\Attribute\Query\Action\PreGetPostsAction;

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
use WPPack\Component\Hook\Attribute\Query\Filter\PostsWhereFilter;

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

## クイックリファレンス

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
