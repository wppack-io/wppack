## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Database/Subscriber/`

### クエリフック

#### `#[QueryFilter]`

**WordPress Hook:** `query`

SQL クエリの実行前にクエリを変更します。

```php
use WpPack\Component\Hook\Attribute\Database\Filter\QueryFilter;

class DatabaseQueryManager
{
    #[QueryFilter(priority: 10)]
    public function addQueryComment(string $query): string
    {
        if (stripos($query, 'SELECT') === 0) {
            return "/* wppack */ " . $query;
        }

        return $query;
    }
}
```

#### `#[DbprepareFilter]`

**WordPress Hook:** `dbprepare`

`prepare()` 後、実行前のクエリをフィルタリングします。

```php
use WpPack\Component\Hook\Attribute\Database\Filter\DbprepareFilter;

class DatabasePrepareManager
{
    #[DbprepareFilter(priority: 10)]
    public function filterPreparedQueries(string $query): string
    {
        return $query;
    }
}
```

### スキーマフック

#### `#[WpUpgradeAction]`

**WordPress Hook:** `wp_upgrade`

WordPress アップグレード時にデータベーススキーマの更新を実行します。

```php
use WpPack\Component\Hook\Attribute\Database\Action\WpUpgradeAction;

class DatabaseSchemaManager
{
    #[WpUpgradeAction(priority: 10)]
    public function upgradeDatabaseSchema(string $wpDbVersion, string $wpCurrentDbVersion): void
    {
        $this->runDbDeltaUpdates();
    }
}
```

#### `#[DbDeltaQueriesFilter]`

**WordPress Hook:** `dbdelta_queries`

`dbDelta()` 実行前にクエリを変更します。

```php
use WpPack\Component\Hook\Attribute\Database\Filter\DbDeltaQueriesFilter;

class DatabaseTableManager
{
    #[DbDeltaQueriesFilter(priority: 10)]
    public function modifyDbDeltaQueries(array $queries): array
    {
        return $queries;
    }
}
```

### Hook Attribute リファレンス

| Attribute | WordPress Hook | 説明 |
|-----------|---------------|------|
| `#[QueryFilter(priority: 10)]` | `query` | SQL クエリのフィルタリング |
| `#[DbprepareFilter(priority: 10)]` | `dbprepare` | prepare 済みクエリのフィルタリング |
| `#[WpUpgradeAction(priority: 10)]` | `wp_upgrade` | データベースアップグレード |
| `#[DbDeltaQueriesFilter(priority: 10)]` | `dbdelta_queries` | dbDelta クエリ |
| `#[DbDeltaCreateQueriesFilter(priority: 10)]` | `dbdelta_create_queries` | テーブル作成クエリ |
| `#[DbDeltaInsertQueriesFilter(priority: 10)]` | `dbdelta_insert_queries` | INSERT クエリ |
