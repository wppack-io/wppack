# Database コンポーネント

**パッケージ:** `wppack/database`
**名前空間:** `WpPack\Component\Database\`
**レイヤー:** Abstraction

WordPress の `$wpdb` を型安全にラップし、例外ベースのエラーハンドリングと `dbDelta()` によるカスタムテーブルのスキーマ管理を提供するコンポーネントです。snicco/better-wpdb（例外ベースエラーハンドリング）と Doctrine DBAL（メソッド命名、トランザクション API）を参考にしています。

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

// グローバル変数への依存、エラーがサイレント
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}custom_table WHERE status = %s",
    'active',
));

// $results が null でもエラーに気付けない
$wpdb->insert("{$wpdb->prefix}custom_table", [
    'name' => 'test',
    'status' => 'active',
]);

// false が返ってもチェックを忘れがち
```

### After（WpPack）

```php
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\Exception\QueryException;

// DI 経由で注入、例外ベースのエラーハンドリング
try {
    $results = $this->db->fetchAllAssociative(
        "SELECT * FROM {$this->db->prefix()}custom_table WHERE status = %s",
        'active',
    );
} catch (QueryException $e) {
    // エラーが必ず検出される
    $this->logger->error($e->getMessage());
}

// 失敗時は自動で QueryException がスローされる
$this->db->insert('custom_table', [
    'name' => 'test',
    'status' => 'active',
]);
```

## DatabaseManager

`$wpdb` を型安全にラップするサービスクラスです。グローバル変数 `$wpdb` への直接アクセスを排除し、DI コンテナ経由で注入できるようにします。すべてのクエリ実行メソッドは失敗時に `QueryException` をスローします。

### クエリ実行（executeQuery / executeStatement）

SELECT 用と INSERT/UPDATE/DELETE 用を明確に分離しています（Doctrine DBAL パターン）。

| メソッド | 用途 | 戻り値 |
|---------|------|--------|
| `executeQuery(string $query, mixed ...$args): int\|bool` | SELECT 実行 | `$wpdb->query()` の結果 |
| `executeStatement(string $query, mixed ...$args): int` | INSERT/UPDATE/DELETE 実行 | 影響行数 |

### フェッチメソッド（Doctrine DBAL 風命名）

すべて連想配列で結果を返します。variadic args があれば自動で `$wpdb->prepare()` を実行します。

| メソッド | 説明 |
|---------|------|
| `fetchAllAssociative(string $query, mixed ...$args): list<array<string, mixed>>` | 複数行を連想配列の配列で取得 |
| `fetchAssociative(string $query, mixed ...$args): array<string, mixed>\|false` | 1行を連想配列で取得（見つからない場合 `false`） |
| `fetchOne(string $query, mixed ...$args): mixed` | 単一値を取得 |
| `fetchFirstColumn(string $query, mixed ...$args): list<mixed>` | 1列を配列で取得 |

### テーブル操作ヘルパー

テーブル名に自動プレフィックス付与。失敗時は `QueryException` をスロー。

| メソッド | 説明 |
|---------|------|
| `insert(string $table, array $data, array\|string\|null $format = null): int` | 行を挿入（成功時 1） |
| `update(string $table, array $data, array $where, ...): int` | 行を更新（影響行数） |
| `delete(string $table, array $where, ...): int` | 行を削除（影響行数） |

### トランザクション

Doctrine DBAL スタイルのトランザクション API です。

| メソッド | 説明 |
|---------|------|
| `beginTransaction(): void` | `START TRANSACTION` 発行 |
| `commit(): void` | `COMMIT` 発行 |
| `rollBack(): void` | `ROLLBACK` 発行 |

```php
$db->beginTransaction();
try {
    $db->insert('orders', ['product_id' => 1, 'quantity' => 3]);
    $db->executeStatement(
        "UPDATE {$db->prefix()}inventory SET stock = stock - %d WHERE product_id = %d",
        3,
        1,
    );
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

### ユーティリティ

| メソッド | 説明 |
|---------|------|
| `prepare(string $query, mixed ...$args): string` | プリペアドステートメント |
| `quoteIdentifier(string $identifier): string` | バッククォートで識別子をエスケープ |
| `prefix(): string` | テーブルプレフィックスを取得 |
| `charsetCollate(): string` | 文字セット・照合順序を取得 |
| `lastInsertId(): int` | 最後の挿入 ID を取得 |
| `lastError(): string` | 最後のエラーメッセージを取得 |
| `wpdb(): \wpdb` | エスケープハッチ（`$wpdb` インスタンスを直接取得） |

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
use WpPack\Component\Database\Exception\QueryException;

class AnalyticsRepository
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function findActive(): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->db->prefix()}analytics WHERE status = %s ORDER BY created_at DESC",
            'active',
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}analytics WHERE id = %d",
            $id,
        );
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->db->prefix()}analytics WHERE status = %s",
            $status,
        );
    }

    public function create(string $url, string $status): void
    {
        $this->db->insert('analytics', [
            'url' => $url,
            'status' => $status,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->update(
            'analytics',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    public function remove(int $id): void
    {
        $this->db->delete('analytics', ['id' => $id]);
    }
}
```

## 例外ハンドリング

すべてのクエリ実行メソッドは、失敗時に `QueryException` をスローします。例外にはクエリ文字列と DB エラーメッセージが含まれます。

```php
use WpPack\Component\Database\Exception\QueryException;
use WpPack\Component\Database\Exception\ExceptionInterface;

try {
    $db->fetchAllAssociative('SELECT * FROM non_existent_table');
} catch (QueryException $e) {
    echo $e->query;    // 実行しようとしたクエリ
    echo $e->dbError;  // DB のエラーメッセージ
    echo $e->getMessage(); // "Database query failed: {dbError} [Query: {query}]"
}

// コンポーネント全体の例外をキャッチ
try {
    $db->insert('table', ['col' => 'value']);
} catch (ExceptionInterface $e) {
    // Database コンポーネントのすべての例外をキャッチ
}
```

## TableInterface と #[Table] によるスキーマ定義

`#[Table]` アトリビュートと `TableInterface` でカスタムテーブルのスキーマを宣言的に定義します。テーブルの作成・更新には WordPress の `dbDelta()` 関数が使用されます。

```php
use WpPack\Component\Database\Attribute\Table;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\TableInterface;

#[Table('analytics')]
class AnalyticsTable implements TableInterface
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

フレームワークが `schema(DatabaseManager)` を呼び出すため、`global $wpdb` は不要です。

## SchemaManager

`SchemaManager` は `TableInterface` を実装したテーブル定義を一括管理し、`dbDelta()` によるスキーマの作成・更新を実行します。

```php
use WpPack\Component\Database\SchemaManager;

// DI コンテナから注入（#[Table] タグ付きの TableInterface が自動収集される）
$schemaManager = new SchemaManager($db, $tables);

// 全テーブルのスキーマを更新
$results = $schemaManager->updateSchema();

// 単一テーブルを更新
$results = $schemaManager->updateTable($analyticsTable);

// デバッグ用: 全スキーマ SQL を取得
$schemas = $schemaManager->getSchemas();
```

### メソッド

| メソッド | 説明 |
|---------|------|
| `updateSchema(): array` | 全登録テーブルに `dbDelta()` を実行 |
| `updateTable(TableInterface $table): array` | 単一テーブルに `dbDelta()` を実行 |
| `getSchemas(): list<string>` | 全テーブルの SQL を取得（デバッグ用） |

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
