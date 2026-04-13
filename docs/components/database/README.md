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
        ['active'],
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

### Real Prepared Statements

パラメータ付きクエリは、すべてのデータベースエンジンでネイティブの prepared statement として実行されます。SQL 文字列にパラメータを文字列結合せず、DB エンジンにパラメータを分離して渡すため、SQL インジェクションを構造的に防止します。

#### Connection 経由（推奨）

`Connection` を `setConnection()` で注入すると、全クエリが Connection 経由で実行されます。`?` プレースホルダと WordPress 標準の `%s/%d/%f` プレースホルダの両方に対応し、内部で自動変換します。

```php
use WpPack\Component\Database\Connection;
use WpPack\Component\Database\Driver\Driver;

// Connection を注入
$driver = Driver::fromDsn('mysql://user:pass@localhost:3306/mydb');
$db->setConnection(new Connection($driver));

// ? プレースホルダ（DBAL スタイル）
$rows = $db->fetchAllAssociative(
    'SELECT * FROM orders WHERE status = ? AND total > ?',
    ['shipped', 100.0],
);

// %s/%d/%f プレースホルダ（WordPress スタイル）も自動変換されて ? で実行
$rows = $db->fetchAllAssociative(
    "SELECT * FROM {$db->prefix()}orders WHERE status = %s AND total > %f",
    ['shipped', 100.0],
);
```

#### 各エンジンの prepared statement 実装

| エンジン | ドライバ | 内部実装 | パラメータ形式 |
|---------|--------|---------|-------------|
| MySQL | MysqlDriver | `mysqli::prepare()` + `bind_param()` | `?` ネイティブ |
| MariaDB | MysqlDriver | `mysqli::prepare()` + `bind_param()` | `?` ネイティブ |
| SQLite | SqliteDriver | `PDO::prepare()` + `execute()` | `?` ネイティブ |
| PostgreSQL | PgsqlDriver | `pg_query_params()` / `pg_prepare()` + `pg_execute()` | `?` → `$1, $2, ...` 自動変換 |
| RDS Data API | RdsDataApiDriver | HTTP API `parameters` フィールド | `?` → `:param1, :param2, ...` 自動変換 |
| Aurora DSQL | AuroraDsqlDriver | PgsqlDriver に委譲 | `?` → `$1, $2, ...` 自動変換 |

すべてのドライバで、パラメータは SQL 文字列とは別にデータベースエンジンに渡されます。文字列結合やエスケープによる疑似的な prepared statement ではありません。

#### `%i` 識別子プレースホルダ

WordPress 6.2+ で追加された `%i`（識別子）プレースホルダにも対応しています。`%i` は prepared statement のパラメータにできないため、`PlatformInterface::quoteIdentifier()` で即座にインライン展開されます。

```php
// %i はプラットフォームに応じたクォートで展開される
$wpdb->prepare('SELECT * FROM %i WHERE id = %d', 'my_table', 1);
// MySQL:  SELECT * FROM `my_table` WHERE id = ?
// SQLite: SELECT * FROM "my_table" WHERE id = ?
```

#### db.php ドロップイン経由の prepared statement（WpPackWpdb）

`WpPackWpdb` は WordPress の `wpdb` を完全に置き換え、`prepare()` メソッド自体をオーバーライドします。WordPress 標準の `prepare()` はパラメータを SQL に文字列結合しますが、`WpPackWpdb::prepare()` はパラメータを SQL に埋め込まず、`?` プレースホルダ SQL を返しつつパラメータを内部に保持します。続く `query()` がそのパラメータを Driver に分離して渡し、ネイティブ prepared statement で実行します。

```
WordPress コア/プラグインの呼び出し:
$wpdb->query($wpdb->prepare("SELECT ... WHERE id = %d", 1))

WpPackWpdb の内部動作:
prepare() → "SELECT ... WHERE id = ?" + params=[1] を保持
query()   → driver->executeQuery("SELECT ... WHERE id = ?", [1])
           → ネイティブ prepared statement で実行
```

`insert()` / `update()` / `delete()` / `replace()` は `prepare()` + `query()` を経由せず、直接 Driver にパラメータ分離で投げます。

MySQL 接続は一切行われません。すべてのクエリは WpPack Driver 経由で処理されます。

#### Connection なし（従来動作）

`setConnection()` を呼ばない場合の従来動作:

| 環境 | 動作 |
|------|------|
| MySQL（`mysqli`） | `mysqli_prepare()` による native prepared statement |
| SQLite / PostgreSQL | `$wpdb->prepare()` フォールバック（sprintf ベース） |

エンジン種別は `DatabaseEngine` enum と `$engine` プロパティで判定できます:

```php
use WpPack\Component\Database\DatabaseEngine;

if ($db->engine === DatabaseEngine::MySQL) {
    // MySQL 固有の処理
}
```

**WordPress フック互換性:** Connection なしの native prepared statement 実行時は `apply_filters('query', ...)` の発火と `SAVEQUERIES` への記録を維持します。

### クエリ実行（executeQuery / executeStatement）

SELECT 用と INSERT/UPDATE/DELETE 用を明確に分離しています（Doctrine DBAL パターン）。

| メソッド | 用途 | 戻り値 |
|---------|------|--------|
| `executeQuery(string $query, array $params = []): int\|bool` | SELECT 実行 | `$wpdb->query()` の結果 |
| `executeStatement(string $query, array $params = []): int` | INSERT/UPDATE/DELETE 実行 | 影響行数 |

### フェッチメソッド（Doctrine DBAL 風命名）

すべて連想配列で結果を返します。`$params` 配列があれば自動で prepared statement を実行します。

| メソッド | 説明 |
|---------|------|
| `fetchAllAssociative(string $query, array $params = []): list<array<string, mixed>>` | 複数行を連想配列の配列で取得 |
| `fetchAssociative(string $query, array $params = []): array<string, mixed>\|false` | 1行を連想配列で取得（見つからない場合 `false`） |
| `fetchOne(string $query, array $params = []): mixed` | 単一値を取得 |
| `fetchFirstColumn(string $query, array $params = []): list<mixed>` | 1列を配列で取得 |

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
        [3, 1],
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
| `prepare(string $query, mixed ...$args): string` | プリペアドステートメント（`$wpdb->prepare()` ラッパー） |
| `quoteIdentifier(string $identifier): string` | バッククォートで識別子をエスケープ |
| `prefix(): string` | テーブルプレフィックスを取得 |
| `charsetCollate(): string` | 文字セット・照合順序を取得 |
| `lastInsertId(): int` | 最後の挿入 ID を取得 |
| `lastError(): string` | 最後のエラーメッセージを取得 |
| `wpdb(): \wpdb` | エスケープハッチ（`$wpdb` インスタンスを直接取得） |

### コアテーブル名プロパティ

WordPress コアテーブルのフルネーム（プレフィックス付き）をプロパティで提供します。`$wpdb->posts` 等と同等です。

#### ブログ固有テーブル（マルチサイト対応）

以下のプロパティは `__get()` マジックメソッドで実装されており、アクセスのたびに `$wpdb` から最新値を読み取ります。マルチサイト環境で `switch_to_blog()` を呼んだ後も、正しいブログのテーブル名を返します。

| プロパティ | `$wpdb` プロパティ | 値の例 |
|-----------|-------------------|--------|
| `$db->posts` | `$wpdb->posts` | `wp_posts` |
| `$db->postmeta` | `$wpdb->postmeta` | `wp_postmeta` |
| `$db->comments` | `$wpdb->comments` | `wp_comments` |
| `$db->commentmeta` | `$wpdb->commentmeta` | `wp_commentmeta` |
| `$db->options` | `$wpdb->options` | `wp_options` |
| `$db->terms` | `$wpdb->terms` | `wp_terms` |
| `$db->termmeta` | `$wpdb->termmeta` | `wp_termmeta` |
| `$db->termTaxonomy` | `$wpdb->term_taxonomy` | `wp_term_taxonomy` |
| `$db->termRelationships` | `$wpdb->term_relationships` | `wp_term_relationships` |

#### グローバルテーブル

以下のプロパティは `readonly` で、`switch_to_blog()` の影響を受けません（WordPress の仕様と同じ）。

| プロパティ | `$wpdb` プロパティ | 値の例 |
|-----------|-------------------|--------|
| `$db->users` | `$wpdb->users` | `wp_users` |
| `$db->usermeta` | `$wpdb->usermeta` | `wp_usermeta` |

#### マルチサイトでの使用例

```php
// サイト 2 の投稿を取得
switch_to_blog(2);

// $db->posts は自動的に wp_2_posts を返す
$posts = $db->fetchAllAssociative(
    "SELECT * FROM {$db->posts} WHERE post_status = %s",
    ['publish'],
);

restore_current_blog();
// $db->posts は wp_posts に戻る
```

### DatabaseEngine enum

DB エンジン種別を表す enum です。`$db->engine` プロパティで現在のエンジンを取得できます。

| Case | 値 | 説明 |
|------|---|------|
| `DatabaseEngine::MySQL` | `'mysql'` | MySQL / MariaDB（`$wpdb->dbh` が `mysqli`） |
| `DatabaseEngine::SQLite` | `'sqlite'` | SQLite（SQLite Database Integration プラグイン） |
| `DatabaseEngine::PostgreSQL` | `'pgsql'` | PostgreSQL（PG4WP） |

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
            ['active'],
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}analytics WHERE id = %d",
            [$id],
        );
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->db->prefix()}analytics WHERE status = %s",
            [$status],
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

→ [Hook コンポーネントのドキュメント](../hook/database.md) を参照してください。

## Driver / Platform / Connection 抽象化

Database コンポーネントは Cache コンポーネントと同じ抽象化パターン（Driver / Platform / Factory / Bridge）を提供します。MySQL 以外のデータベースエンジン（SQLite、PostgreSQL、RDS Data API、Aurora DSQL）に対応し、Doctrine DBAL の設計思想を取り入れています。

### アーキテクチャ

```
DatabaseManager（WordPress $wpdb ラッパー、後方互換）
    │
Connection（DBAL スタイル API）
    │
DriverInterface（SPI）
    ├── MysqlDriver（コア）
    ├── SqliteDriver（Bridge/Sqlite）
    ├── PgsqlDriver（Bridge/Pgsql）
    ├── RdsDataApiDriver（Bridge/RdsDataApi）
    └── AuroraDsqlDriver（Bridge/AuroraDsql）
```

### Connection

`Connection` は `DriverInterface` を介して、エンジンに依存しない統一 API を提供します。`?` プレースホルダによるネイティブ prepared statement でクエリを実行します。

#### Connection を直接使用

```php
use WpPack\Component\Database\Connection;
use WpPack\Component\Database\Driver\Driver;

$driver = Driver::fromDsn('mysql://user:pass@localhost:3306/mydb');
$connection = new Connection($driver);

// ? プレースホルダ → 各エンジンのネイティブ prepared statement で実行
$rows = $connection->fetchAllAssociative('SELECT * FROM posts WHERE status = ?', ['publish']);
$connection->transactional(function (Connection $conn) {
    $conn->executeStatement('INSERT INTO logs (message) VALUES (?)', ['Hello']);
});
```

#### DatabaseManager と統合

`setConnection()` で Connection を注入すると、DatabaseManager の全クエリが Connection 経由で実行されます。WordPress 標準の `%s/%d/%f` プレースホルダも自動的に `?` に変換されます。

```php
use WpPack\Component\Database\Connection;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\Driver\Driver;

$db = new DatabaseManager();
$driver = Driver::fromDsn('mysql://user:pass@localhost:3306/mydb');
$db->setConnection(new Connection($driver));

// WordPress スタイル — 内部で %s → ? に変換してネイティブ prepared statement で実行
$db->fetchAllAssociative(
    "SELECT * FROM {$db->prefix()}posts WHERE status = %s",
    ['publish'],
);

// DBAL スタイル — そのまま ? で実行
$db->fetchAllAssociative(
    'SELECT * FROM posts WHERE status = ?',
    ['publish'],
);
```

`setConnection()` なしの場合は従来通り `$wpdb` 経由で実行されます（後方互換）。

### DSN フォーマット

`Driver::fromDsn()` で DSN 文字列からドライバを自動生成します。

| スキーム | ドライバ | 例 |
|---------|--------|-----|
| `mysql://` | MysqlDriver | `mysql://user:pass@host:3306/dbname` |
| `mariadb://` | MysqlDriver | `mariadb://user:pass@host:3306/dbname` |
| `sqlite://` | SqliteDriver | `sqlite:///path/to/db.sqlite` |
| `pgsql://` | PgsqlDriver | `pgsql://user:pass@host:5432/dbname` |
| `rds-data://` | RdsDataApiDriver | `rds-data://cluster-arn/dbname?secret_arn=...` |
| `dsql://` | AuroraDsqlDriver | `dsql://admin:token@id.dsql.us-east-1.on.aws/dbname` |
| `wpdb://` | MysqlDriver | `wpdb://default`（既存 $wpdb をラップ） |

### Platform

各エンジンの SQL 方言差異（識別子クォート、トランザクション構文、AUTO_INCREMENT キーワード等）を抽象化します。

| Platform | エンジン | 識別子クォート | BEGIN | AUTO_INCREMENT |
|---------|--------|------------|-------|----------------|
| `MysqlPlatform` | MySQL | `` ` `` | `START TRANSACTION` | `AUTO_INCREMENT` |
| `MariadbPlatform` | MariaDB | `` ` `` | `START TRANSACTION` | `AUTO_INCREMENT` |
| `SqlitePlatform` | SQLite | `"` | `BEGIN` | `AUTOINCREMENT` |
| `PostgresqlPlatform` | PostgreSQL | `"` | `BEGIN` | `SERIAL` |

### QueryTranslator

WordPress は MySQL SQL を生成するため、非 MySQL エンジンではクエリ変換が必要です。各ドライバが `getQueryTranslator()` で適切なトランスレーターを返します。

トランスレーターは `phpmyadmin/sql-parser` による AST パースを使用し、MySQL SQL を完全に解析してターゲットエンジンの SQL に変換します。

| ドライバ | Translator | 動作 |
|---------|-----------|------|
| MysqlDriver | NullQueryTranslator | パススルー |
| SqliteDriver | SqliteQueryTranslator | MySQL → SQLite 変換（AST ベース） |
| PgsqlDriver | PostgresqlQueryTranslator | MySQL → PostgreSQL 変換（AST ベース） |
| RdsDataApiDriver | NullQueryTranslator | パススルー（Aurora MySQL 互換） |
| AuroraDsqlDriver | PostgresqlQueryTranslator | MySQL → PostgreSQL 変換（AST ベース） |

#### 対応する変換

**DDL:** CREATE TABLE, ALTER TABLE (ADD/DROP/MODIFY), CREATE INDEX, DROP INDEX, TRUNCATE TABLE

**DML:** INSERT IGNORE, REPLACE INTO, ON DUPLICATE KEY UPDATE → ON CONFLICT

**関数:** NOW, CURDATE, RAND, UNIX_TIMESTAMP, FROM_UNIXTIME, DATE_ADD, DATE_SUB, DATE_FORMAT, CONCAT, SUBSTRING, LENGTH, CHAR_LENGTH, LEFT, IF, IFNULL, CAST AS SIGNED, LAST_INSERT_ID, VERSION, DATABASE, FOUND_ROWS

**SHOW 文:** SHOW TABLES, SHOW COLUMNS, SHOW CREATE TABLE, SHOW INDEX, SHOW VARIABLES, SHOW COLLATION, SHOW DATABASES, SHOW TABLE STATUS

**無視:** SET SESSION, SET NAMES, LOCK/UNLOCK TABLES, OPTIMIZE/ANALYZE/CHECK/REPAIR TABLE

### Bridge パッケージ

エンジン固有の実装は Bridge パッケージとして分離されています。必要なものだけインストールします。

| パッケージ | 説明 | 依存 |
|----------|------|------|
| `wppack/sqlite-database` | SQLite ドライバ | ext-pdo_sqlite |
| `wppack/pgsql-database` | PostgreSQL ドライバ | ext-pgsql |
| `wppack/rds-data-api-database` | RDS Data API ドライバ | async-aws/rds-data-service |
| `wppack/aurora-dsql-database` | Aurora DSQL ドライバ | wppack/pgsql-database |

### db.php ドロップイン

`wp-content/db.php` にシンボリックリンクまたはコピーすることで、WordPress のデータベースレイヤーを `WpPackWpdb` に完全に置き換えます。すべてのエンジン（MySQL/MariaDB 含む）で真の prepared statement が有効になります。

```php
// wp-config.php — 基本設定
define('WPPACK_DATABASE_DSN', 'mysql://user:pass@host:3306/mydb');
```

```php
// SQLite を使う場合
define('WPPACK_DATABASE_DSN', 'sqlite:///path/to/database.db');
```

```php
// 読み書き分離（Reader/Writer Split）
define('WPPACK_DATABASE_DSN', 'mysql://user:pass@primary:3306/mydb');
define('WPPACK_DATABASE_READER_DSN', 'mysql://user:pass@replica:3306/mydb');
```

Reader DSN が定義されている場合、SELECT/SHOW/DESCRIBE/EXPLAIN は reader ドライバで、INSERT/UPDATE/DELETE/CREATE 等は writer ドライバで実行されます。

`wpdb://default` を指定するか、`WPPACK_DATABASE_DSN` を未定義にすると、WordPress 標準の `$wpdb` がそのまま使用されます。

### DatabaseEngine enum

| Case | 値 | 説明 |
|------|---|------|
| `MySQL` | `'mysql'` | MySQL |
| `MariaDB` | `'mariadb'` | MariaDB |
| `SQLite` | `'sqlite'` | SQLite |
| `PostgreSQL` | `'pgsql'` | PostgreSQL |

## 依存関係

### 必須
- `wppack/dsn` — DSN パーサー

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
