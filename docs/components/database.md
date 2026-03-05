# Database コンポーネント

**パッケージ:** `wppack/database`
**名前空間:** `WpPack\Component\Database\`
**レイヤー:** Abstraction

WordPress の `$wpdb` を型安全にラップし、`dbDelta()` によるカスタムテーブルのスキーマ管理を提供するコンポーネントです。

## Database と Query の違い

| | Database コンポーネント | Query コンポーネント |
|---|---|---|
| 対象 | カスタムテーブル / 生 SQL | WordPress ネイティブデータ |
| 内部実装 | `$wpdb` ラッパー | `WP_Query` / `WP_User_Query` / `WP_Term_Query` ラッパー |
| 主な用途 | カスタムテーブルの CRUD、スキーマ管理 | 投稿・ユーザー・タームの検索・取得 |

## インストール

```bash
composer require wppack/database
```

## 基本コンセプト

### Before（従来の WordPress）

```php
global $wpdb;

// グローバル変数への依存、型安全性なし
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}custom_table WHERE status = %s AND priority >= %d",
    'active',
    5
));

$wpdb->insert("{$wpdb->prefix}custom_table", [
    'name' => 'test',
    'status' => 'active',
]);
```

### After（WpPack）

```php
use WpPack\Component\Database\DatabaseManager;

// DI 経由で注入、型安全な $wpdb ラッパー
$results = $this->db->getResults(
    "SELECT * FROM {$this->db->prefix()}custom_table WHERE status = %s AND priority >= %d",
    'active',
    5
);

$this->db->insert('custom_table', [
    'name' => 'test',
    'status' => 'active',
]);
```

## DatabaseManager

`$wpdb` を型安全にラップするサービスクラスです。グローバル変数 `$wpdb` への直接アクセスを排除し、DI コンテナ経由で注入できるようにします。

### 主要メソッド

| メソッド | `$wpdb` メソッド | 説明 |
|---------|-----------------|------|
| `getResults(string, mixed...): array` | `get_results()` | 複数行を取得 |
| `getRow(string, mixed...): ?object` | `get_row()` | 1行を取得 |
| `getVar(string, mixed...): ?string` | `get_var()` | 単一値を取得 |
| `getCol(string, mixed...): array` | `get_col()` | 1列を取得 |
| `insert(string, array): int\|false` | `insert()` | 行を挿入（テーブル名にプレフィックス自動付与） |
| `update(string, array, array): int\|false` | `update()` | 行を更新 |
| `delete(string, array): int\|false` | `delete()` | 行を削除 |
| `prepare(string, mixed...): string` | `prepare()` | プリペアドステートメント |
| `query(string): int\|bool` | `query()` | 生 SQL を実行 |
| `prefix(): string` | `$wpdb->prefix` | テーブルプレフィックスを取得 |
| `charsetCollate(): string` | `$wpdb->get_charset_collate()` | 文字セット・照合順序を取得 |
| `lastInsertId(): int` | `$wpdb->insert_id` | 最後の挿入 ID を取得 |
| `lastError(): string` | `$wpdb->last_error` | 最後のエラーメッセージを取得 |

### コアテーブル名プロパティ

WordPress コアテーブルのフルネーム（プレフィックス付き）を readonly プロパティで提供します。`$wpdb->posts` 等と同等です。

| プロパティ | `$wpdb` プロパティ | 値の例 |
|-----------|-------------------|--------|
| `$db->posts` | `$wpdb->posts` | `wp_posts` |
| `$db->postmeta` | `$wpdb->postmeta` | `wp_postmeta` |
| `$db->comments` | `$wpdb->comments` | `wp_comments` |
| `$db->commentmeta` | `$wpdb->commentmeta` | `wp_commentmeta` |
| `$db->options` | `$wpdb->options` | `wp_options` |
| `$db->users` | `$wpdb->users` | `wp_users` |
| `$db->usermeta` | `$wpdb->usermeta` | `wp_usermeta` |
| `$db->terms` | `$wpdb->terms` | `wp_terms` |
| `$db->termmeta` | `$wpdb->termmeta` | `wp_termmeta` |
| `$db->termTaxonomy` | `$wpdb->term_taxonomy` | `wp_term_taxonomy` |
| `$db->termRelationships` | `$wpdb->term_relationships` | `wp_term_relationships` |

### 使用例

```php
use WpPack\Component\Database\DatabaseManager;

class AnalyticsRepository
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function findActive(): array
    {
        return $this->db->getResults(
            "SELECT * FROM {$this->db->prefix()}analytics WHERE status = %s ORDER BY created_at DESC",
            'active',
        );
    }

    public function findById(int $id): ?object
    {
        return $this->db->getRow(
            "SELECT * FROM {$this->db->prefix()}analytics WHERE id = %d",
            $id,
        );
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$this->db->prefix()}analytics WHERE status = %s",
            $status,
        );
    }

    public function create(string $url, string $status): int|false
    {
        return $this->db->insert('analytics', [
            'url' => $url,
            'status' => $status,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function updateStatus(int $id, string $status): int|false
    {
        return $this->db->update(
            'analytics',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    public function remove(int $id): int|false
    {
        return $this->db->delete('analytics', ['id' => $id]);
    }
}
```

## #[Table] によるスキーマ定義

`#[Table]` アトリビュートでカスタムテーブルのスキーマを宣言的に定義します。テーブルの作成・更新には WordPress の `dbDelta()` 関数が使用されます。

```php
use WpPack\Component\Database\Attribute\Table;
use WpPack\Component\Database\DatabaseManager;

#[Table('analytics')]
class AnalyticsTable
{
    public function schema(DatabaseManager $db): string
    {
        $tableName = $db->prefix() . 'analytics';
        $charsetCollate = $db->charsetCollate();

        return "CREATE TABLE {$tableName} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            view_count bigint(20) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charsetCollate};";
    }
}
```

フレームワークが `schema(DatabaseManager)` を呼び出すため、`global $wpdb` は不要です。プラグインの有効化時に `dbDelta()` を通じてテーブルが自動的に作成・更新されます。

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Database/Subscriber/`

### クエリフック

#### `#[QueryFilter]`

**WordPress Hook:** `query`

SQL クエリの実行前にクエリを変更します。

```php
use WpPack\Component\Database\Attribute\Filter\QueryFilter;

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
use WpPack\Component\Database\Attribute\Filter\DbprepareFilter;

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
use WpPack\Component\Database\Attribute\Action\WpUpgradeAction;

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
use WpPack\Component\Database\Attribute\Filter\DbDeltaQueriesFilter;

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

## 依存関係

### 必須
なし — WordPress の `$wpdb` をそのまま利用

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
