# Database コンポーネント

**パッケージ:** `wppack/database`
**名前空間:** `WpPack\Component\Database\`
**レイヤー:** Abstraction

WordPress の `$wpdb` を型安全にラップし、例外ベースのエラーハンドリングと `dbDelta()` によるカスタムテーブルのスキーマ管理を提供するコンポーネントです。

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
| Aurora MySQL Data API | MysqlDataApiDriver | HTTP API `parameters` フィールド | `?` → `:param1, :param2, ...` 自動変換 |
| Aurora PostgreSQL Data API | PgsqlDataApiDriver | HTTP API `parameters` フィールド | `?` → `:param1, :param2, ...` 自動変換 |
| Aurora DSQL | AuroraDsqlDriver | PgsqlDriver 継承 | `?` → `$1, $2, ...` 自動変換 |

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

`WpPackWpdb` は WordPress の `wpdb` を完全に置き換え、`prepare()` メソッド自体をオーバーライドします。WordPress 標準の `prepare()` はパラメータを SQL に文字列結合しますが、`WpPackWpdb::prepare()` はパラメータを SQL に埋め込まず、`?` プレースホルダ SQL と `/*WPP:<id>*/` SQL コメントマーカーを返しつつ、パラメータを instance の **PreparedBank** に保持します。`WP_Site_Query` などが prepare 断片を concat して最終 SQL を作るパターンに対応するため、マーカーは prepare 呼び出し単位で1個だけ付加されます。

```
WordPress コア/プラグインの呼び出し:
$wpdb->query($wpdb->prepare("SELECT ... WHERE id = %d", 1))

WpPackWpdb の内部動作:
prepare() → "SELECT ... WHERE id = ?/*WPP:abc123def456*/"
             + PreparedBank[abc123def456] = [1]
query()   → marker を SQL から除去、bank から params を取り出して即 unset
           → driver->executeQuery("SELECT ... WHERE id = ?", [1])
           → ネイティブ prepared statement で実行
```

- **マーカー id** は `substr(sha1($salt . "\x01" . $outputSql . "\x01" . serialize($params)), 0, 16)` で決定的に生成します。64bit 分の空間により現実的な運用内で衝突しません。`$salt` はインスタンス起動時に `random_bytes(8)` で生成されるため、ユーザ入力に `/*WPP:<16hex>*/` が混入しても他の PreparedBank エントリには絶対に到達できません (marker forge 対策)。同じインスタンス内で同じ (SQL, params) は常に同じ id → 同じマーカー → `WP_Site_Query::get_site_ids()` 等のキャッシュキーが安定します。
- **PreparedBank** はインスタンス配列 (`private array $preparedBank`) で、`query()` が消費したエントリは即 `unset` されます。バルクインポートのような `foreach { prepare() } query($concat)` パターンでも query 実行時に一括クリアされるのでメモリリークしません。`WpPackWpdb::resetPreparedBank()` で orphan を強制クリアも可能。
- **`$wpdb->last_query`** には `?` 版のクリーン SQL が入り、値は露出しません。`$wpdb->last_params` 新プロパティに実行時の値配列が入ります（debug 用）。SAVEQUERIES ログのマスキング効果あり。
- `%i`（識別子）は prepared statement にできないので引き続き quoteIdentifier でインライン展開。
- **文字列リテラル内プレースホルダ**: `$wpdb->prepare("LIKE '%%%s%%'", 'foo')` のように `%s`（/`%d`/`%f`/`%i`）が `'...'` リテラル内に入るケースは、**リテラル全体** を 1 つの `?` に置き換えて bind します (`LIKE ?` + bound `'%foo%'`)。MySQL 側が `'%?%'` を placeholder として認識しない制約と、PostgreSQL の `pg_escape_literal` が `E'...'` 形式を返す挙動 (splice 時に整合しない) の両方を engine-neutral に回避するための唯一の方法です。`%i` を literal 内に書くのは意味論的に誤用ですが、引数を「消費」して識別子形式に folding することで後続 placeholder の index ズレを起こさないよう実装しています。
- **不正テンプレートの即時検出**: 未閉リテラル (`'abc`) を渡した場合、`prepare()` は driver 実行時に構文エラーを待つのではなく `InvalidArgumentException` を即座に投げます。デバッグ時に呼び出し元が即時特定できる設計です。

`insert()` / `update()` / `delete()` / `replace()` は `prepare()` + `query()` を経由せず、直接 Driver にパラメータ分離で投げます。

#### insert/update/delete/replace のテーブル名契約

`WpPackWpdb::insert()` / `update()` / `delete()` / `replace()` は **標準 `wpdb` と同じ契約** で、完全修飾テーブル名（プレフィックス付き）を受け取ります。WpPackWpdb 側ではプレフィックスを自動付与しません。

```php
// 正しい: $wpdb->posts は "wp_posts" 等の完全修飾名
$wpdb->insert($wpdb->posts, ['post_title' => 'Hello', ...]);

// 正しい: プレフィックスを明示
$wpdb->insert($wpdb->prefix . 'analytics', ['name' => 'test']);

// NG: "analytics" のままだと "analytics" テーブルを探してエラー
$wpdb->insert('analytics', ['name' => 'test']);
```

`DatabaseManager` レイヤはこの上で **ベアテーブル名を受けて自動でプレフィックスを前置する** API を提供します:

```php
$db = new DatabaseManager();
$db->insert('analytics', ['name' => 'test']);  // 内部で $wpdb->insert($wpdb->prefix . 'analytics', ...) を呼ぶ
```

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

SELECT 用と INSERT/UPDATE/DELETE 用を明確に分離しています。

| メソッド | 用途 | 戻り値 |
|---------|------|--------|
| `executeQuery(string $query, array $params = []): int\|bool` | SELECT 実行 | `$wpdb->query()` の結果 |
| `executeStatement(string $query, array $params = []): int` | INSERT/UPDATE/DELETE 実行 | 影響行数 |

### フェッチメソッド

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

### エンジン識別子

`$db->engine` プロパティで現在のエンジンを文字列で取得できます。各 Platform が独自のエンジン名を定義します。

| エンジン | 値 | Platform | ドライバ |
|---------|---|----------|---------|
| MySQL | `'mysql'` | MysqlPlatform | MysqlDriver, MysqlDataApiDriver |
| MariaDB | `'mariadb'` | MariadbPlatform | MysqlDriver（自動検出） |
| SQLite | `'sqlite'` | SqlitePlatform | SqliteDriver |
| PostgreSQL | `'pgsql'` | PostgresqlPlatform | PgsqlDriver, PgsqlDataApiDriver |
| Aurora DSQL | `'dsql'` | DsqlPlatform | AuroraDsqlDriver |

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

### 例外階層

`ExceptionInterface` を実装する以下の例外型を提供します。用途に応じて retry / surface を判定できます。

| 例外型 | 発生元 | retry 推奨度 |
|--------|--------|--------------|
| `QueryException` | `DatabaseManager` のクエリ失敗 | データエラー依存 |
| `TranslationException` | Translator が MySQL→他エンジン変換できず | 不可 (固定形式) |
| `ParserFailureException` (TranslationException) | phpmyadmin/sql-parser が構文解析失敗 | 不可 |
| `UnsupportedFeatureException` (TranslationException) | 意味的に変換不能 (FULLTEXT / 空間関数等) | 不可 — 別 feature の利用を促す |
| `DriverException` | Driver 層でのクエリ / 接続失敗 (`driverErrno` を保持) | ケースによる |
| `DriverThrottledException` (DriverException) | AWS 429 / ThrottlingException | backoff で retry 可 |
| `DriverTimeoutException` (DriverException) | 504 / socket timeout | 冪等クエリなら retry 可 |
| `CredentialsExpiredException` (DriverException) | IAM token 期限切れ / secret ローテーション | 認証 refresh 後 retry |
| `ConnectionException` | 接続確立失敗 | 通常リトライ不可 (設定ミス) |

```php
try {
    $db->insert('orders', ['total' => 100]);
} catch (\WpPack\Component\Database\Exception\DriverThrottledException $e) {
    // RDS Data API rate limit — exponential backoff して retry
    sleep(1); $db->insert('orders', ['total' => 100]);
} catch (\WpPack\Component\Database\Exception\CredentialsExpiredException $e) {
    // IAM token refresh 後に retry
    $tokenRefresher->refresh();
    $db->insert('orders', ['total' => 100]);
} catch (\WpPack\Component\Database\Exception\DriverException $e) {
    // その他の driver 失敗
    $this->logger->error($e->getMessage(), ['errno' => $e->driverErrno]);
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

Database コンポーネントは Cache コンポーネントと同じ抽象化パターン（Driver / Platform / Factory / Bridge）を提供します。MySQL 以外のデータベースエンジン（SQLite、PostgreSQL、RDS Data API、Aurora DSQL）に対応しています。

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
    ├── MysqlDataApiDriver（Bridge/MysqlDataApi）← MysqlDriver 継承
    ├── PgsqlDataApiDriver（Bridge/PgsqlDataApi）← PgsqlDriver 継承
    └── AuroraDsqlDriver（Bridge/AuroraDsql）← PgsqlDriver 継承
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
| `mysql+dataapi://` | MysqlDataApiDriver | `mysql+dataapi://cluster-arn/dbname?secret_arn=...` |
| `pgsql+dataapi://` | PgsqlDataApiDriver | `pgsql+dataapi://cluster-arn/dbname?secret_arn=...` |
| `dsql://` | AuroraDsqlDriver | `dsql://admin@id.dsql.us-east-1.on.aws/dbname?occMaxRetries=3` |
| `wpdb://` | ― | `wpdb://default`（WordPress デフォルト `$wpdb` を使用、ドライバ置き換えなし） |

### Platform

各エンジンの SQL 方言差異（識別子クォート、トランザクション構文、AUTO_INCREMENT キーワード等）を抽象化します。

| Platform | エンジン | 識別子クォート | BEGIN | AUTO_INCREMENT |
|---------|--------|------------|-------|----------------|
| `MysqlPlatform` | MySQL | `` ` `` | `START TRANSACTION` | `AUTO_INCREMENT` |
| `MariadbPlatform` | MariaDB | `` ` `` | `START TRANSACTION` | `AUTO_INCREMENT` |
| `SqlitePlatform` | SQLite | `"` | `BEGIN` | `AUTOINCREMENT` |
| `PostgresqlPlatform` | PostgreSQL | `"`（小文字化） | `BEGIN` | `SERIAL` |

### QueryTranslator

WordPress は MySQL SQL を生成するため、非 MySQL エンジンではクエリ変換が必要です。各ドライバが `getQueryTranslator()` で適切なトランスレーターを返します。

トランスレーターは `phpmyadmin/sql-parser` による AST パースを使用し、MySQL SQL を完全に解析してターゲットエンジンの SQL に変換します。変換手法の設計判断と全変換リストの詳細は [Query Translation Architecture](./query-translation.md) を参照してください。

| ドライバ | Translator | 動作 |
|---------|-----------|------|
| MysqlDriver | NullQueryTranslator | パススルー |
| SqliteDriver | SqliteQueryTranslator | MySQL → SQLite 変換（AST ベース） |
| PgsqlDriver | PostgresqlQueryTranslator | MySQL → PostgreSQL 変換（AST ベース） |
| MysqlDataApiDriver | NullQueryTranslator | パススルー（Aurora MySQL 互換） |
| PgsqlDataApiDriver | PostgresqlQueryTranslator | MySQL → PostgreSQL 変換（AST ベース） |
| AuroraDsqlDriver | AuroraDsqlQueryTranslator | PostgreSQL 変換 + TRUNCATE→DELETE |

#### 対応する変換

**対応する変換:**

- **日付 / 時刻関数:** `NOW`, `CURDATE`, `CURTIME`, `UNIX_TIMESTAMP`, `UTC_TIMESTAMP`, `UTC_DATE`, `UTC_TIME`, `DATE_ADD`, `DATE_SUB`, `DATE_FORMAT`, `STR_TO_DATE`, `FROM_UNIXTIME`, `DATEDIFF`, `MONTH`, `YEAR`, `DAY`, `DAYOFMONTH`, `DAYOFWEEK`, `DAYOFYEAR`, `WEEK`, `WEEKDAY`, `HOUR`, `MINUTE`, `SECOND`, `QUARTER`, `DAYNAME`, `MONTHNAME`, `LAST_DAY`, `MAKEDATE`, `PERIOD_ADD`, `PERIOD_DIFF`, `TIME_TO_SEC`, `SEC_TO_TIME`
- **文字列関数:** `CONCAT`, `CONCAT_WS`, `LEFT`, `RIGHT`, `SUBSTRING` / `SUBSTR` / `MID`, `CHAR_LENGTH` / `CHARACTER_LENGTH`, `LCASE` / `UCASE`, `LOCATE`, `FIELD`, `SUBSTRING_INDEX`, `FIND_IN_SET`, `SPACE`, `LPAD`, `RPAD`, `CONVERT` (両形式 — `(x, type)` cast + `(x USING charset)` 文字セット変換)
- **制御フロー / 数値:** `IF`, `IFNULL`, `ISNULL`, `RAND`, `LOG`, `GREATEST`, `LEAST`
- **バイナリ / エンコード:** `UNHEX`, `TO_BASE64`, `FROM_BASE64`, `INET_ATON`, `INET_NTOA`
- **ロック:** `GET_LOCK`, `RELEASE_LOCK`, `IS_FREE_LOCK`
- **JSON:** `JSON_EXTRACT` (PostgreSQL の `col::jsonb #> '{path}'` 形式へ変換、SQLite は json1 をそのまま経由)
- **集計:** `GROUP_CONCAT` (DISTINCT / ORDER BY / SEPARATOR の空白保全込み)
- **文:** `INSERT IGNORE`, `REPLACE INTO`, `ON DUPLICATE KEY UPDATE` + `VALUES(col)` 参照, `LIMIT`, `FOR UPDATE`, `TRUNCATE`, `DELETE ... JOIN` (LEFT/INNER/USING + ネスト AND/OR 保全)
- **DDL:** `CREATE TABLE` 型変換, `AUTO_INCREMENT`, ENGINE/CHARSET 除去, `KEY` → `CREATE INDEX` 分離, `ALTER TABLE` (CHANGE/MODIFY/ADD COLUMN/RENAME)
- **SHOW:** TABLES, COLUMNS, CREATE TABLE, INDEX, VARIABLES, DATABASES, DESCRIBE
- **互換性:** `LIKE ESCAPE` 自動付与, 識別子ケース正規化 (PostgreSQL), ゼロ日付変換

**明示的に翻訳を拒絶する機能** (`UnsupportedFeatureException` を throw):

- `FULLTEXT MATCH (col) AGAINST (…)` — SQLite は FTS5 仮想テーブル、PostgreSQL は `tsvector` 列が必要で schema-level の事前準備を要する
- SQLite の `ST_*` / `GeomFromText` / `GeomFromWKB` / `AsText` / `AsBinary` 等の空間関数 — SpatiaLite 拡張が必要
- SQLite の `SUBSTRING_INDEX(..., -N)` (負の index) — 再帰 CTE が必要で実装コストに見合わない

silent pass-through はしません。production で "結果 0 件" や "無音の syntax error" 系の障害を避けるため、変換不可能な入力は `TranslationException` (subtype: `UnsupportedFeatureException` / `ParserFailureException`) で明示的に失敗させます。

全変換リストと設計判断の詳細は [Query Translation Architecture](./query-translation.md) を参照。既存プラグインとの比較は [プラグイン比較](./plugin-comparison.md) を参照。

#### エンジン固有の注意点

**SQLite:**
- `CHANGE COLUMN`（リネーム）→ `ALTER TABLE RENAME COLUMN`（SQLite 3.25.0+）。同名の型変更は no-op（動的型付けのため不要）
- `DROP COLUMN` → パススルー（SQLite 3.35.0+ でネイティブ対応）
- `REGEXP`, `CONCAT`, `CONCAT_WS`, `CHAR_LENGTH`, `FIELD` は PHP ユーザー定義関数として自動登録
- CREATE TABLE は AST `CreateDefinition[]` から直接構築。`PRIMARY KEY` + `AUTOINCREMENT` は自動マージ
- インライン `KEY name (col)` は `CREATE INDEX` 文に自動分離（SQLite は CREATE TABLE 内の KEY をサポートしない）
- 全 `LIKE` 句に `ESCAPE '\'` を自動付与（MySQL のデフォルトエスケープ動作と互換）

**PostgreSQL:**
- `BIGINT AUTO_INCREMENT` → `BIGSERIAL`、`INT AUTO_INCREMENT` → `SERIAL`
- `DATE_FORMAT()` のフォーマット文字列は MySQL → PostgreSQL マップで変換（`%Y→YYYY`, `%m→MM` 等）
- DDL の識別子（カラム名・インデックス名）は自動的に小文字化。PostgreSQL はクォートなし識別子を小文字に正規化するため、WordPress の `WHERE ID = ?` のようなクエリとの互換性を保つ
- DDL の `DEFAULT '0000-00-00 00:00:00'` は `'0001-01-01 00:00:00'` に変換（PostgreSQL TIMESTAMP で無効なため）
- インライン `KEY name (col)` は `CREATE INDEX` 文に自動分離
- `LIKE` → `ILIKE`（大文字小文字区別なし）+ `ESCAPE '\'` 自動付与
- `TRUNCATE TABLE` はネイティブ対応
- CREATE TABLE は AST から直接構築。`VARCHAR(N)`, `DECIMAL(N,M)` のパラメータは保持

**Aurora DSQL:**
- PostgreSQL 互換エンジン。PostgresqlQueryTranslator を内部で使用し、DSQL 固有の制限（TRUNCATE 未対応 → DELETE FROM 変換）を追加
- **IAM トークン認証:** `async-aws/core` の SigV4 presign で自動生成。`admin` ユーザーは `DbConnectAdmin`、それ以外は `DbConnect` アクション
- **トークン有効期間:** デフォルト 900 秒（15分）。期限 60 秒前に自動再接続（WP-CLI 等の長時間プロセス対応）
- **SSL verify-full:** 必須。`PgsqlDriver` の `sslmode` パラメータで強制
- **OCC リトライ:** 楽観的同時実行制御。SQLSTATE `40001` / `OC000` / `OC001` で指数バックオフ + ジッター（初期 100ms, 2 倍, 上限 5s）。`occMaxRetries` で設定（デフォルト 3）
- **DSN パラメータ:** `dsql://admin@cluster.dsql.us-east-1.on.aws/postgres?occMaxRetries=5&tokenDurationSecs=600&region=us-east-1`

### Bridge パッケージ

エンジン固有の実装は Bridge パッケージとして分離されています。必要なものだけインストールします。

| パッケージ | 説明 | 依存 |
|----------|------|------|
| `wppack/sqlite-database` | SQLite ドライバ | ext-pdo_sqlite |
| `wppack/pgsql-database` | PostgreSQL ドライバ | ext-pgsql |
| `wppack/mysql-data-api-database` | Aurora MySQL Data API ドライバ | async-aws/rds-data-service |
| `wppack/pgsql-data-api-database` | Aurora PostgreSQL Data API ドライバ | wppack/pgsql-database, async-aws/rds-data-service |
| `wppack/aurora-dsql-database` | Aurora DSQL ドライバ | wppack/pgsql-database, async-aws/core |

### db.php ドロップイン

`wp-content/db.php` にシンボリックリンクまたはコピーすることで、WordPress のデータベースレイヤーを `WpPackWpdb` に完全に置き換えます。すべてのエンジン（MySQL/MariaDB 含む）で真の prepared statement が有効になります。

**`DATABASE_DSN` を定義するだけで、WordPress は対応する任意の DB エンジンで動作します。** `DB_HOST` / `DB_USER` / `DB_PASSWORD` / `DB_NAME` 等の `DB_*` 定数は不要です。

```php
// wp-config.php — DATABASE_DSN のみで WordPress が動作
define('DATABASE_DSN', 'mysql://user:pass@host:3306/mydb?charset=utf8mb4&collate=utf8mb4_general_ci');
```

#### 対応エンジンと DSN 例

| エンジン | DSN 例 | 必要パッケージ |
|---------|--------|---------------|
| MySQL / MariaDB | `mysql://user:pass@host:3306/mydb` | （コア） |
| SQLite | `sqlite:///path/to/database.db` | wppack/sqlite-database |
| SQLite（インメモリ） | `sqlite:///:memory:` | wppack/sqlite-database |
| PostgreSQL | `pgsql://user:pass@host:5432/mydb` | wppack/pgsql-database |
| Aurora DSQL | `dsql://cluster-endpoint:5432/mydb` | wppack/aurora-dsql-database |
| Aurora MySQL Data API | `mysql+dataapi://cluster-arn/mydb?secretArn=...&region=...` | wppack/mysql-data-api-database |
| Aurora PostgreSQL Data API | `pgsql+dataapi://cluster-arn/mydb?secretArn=...&region=...` | wppack/pgsql-data-api-database |

#### DB_* 定数からの自動構築（フォールバック）

`DATABASE_DSN` が未定義の場合、WordPress 標準の `DB_HOST` / `DB_USER` / `DB_PASSWORD` / `DB_NAME` 定数から MySQL DSN を自動構築します。つまり、既存の `wp-config.php` はそのまま使えます。

#### charset / collate 設定

charset と collate は以下の優先順位で解決されます:

1. **DSN パラメータ**（最優先）: `?charset=utf8mb4&collate=utf8mb4_general_ci`
2. **DB_CHARSET / DB_COLLATE 定数**（フォールバック）
3. **デフォルト値**: charset=`utf8mb4`, collate=`''`（空文字）

collate が空文字の場合、WordPress の `get_charset_collate()` は COLLATE 句を出力しません。

```php
// DSN で charset/collate を指定（推奨）
define('DATABASE_DSN', 'mysql://user:pass@host:3306/mydb?charset=utf8mb4&collate=utf8mb4_general_ci');

// または DB_* 定数で指定（フォールバック）
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_general_ci');
```

> **日本語環境の注意:** `utf8mb4_unicode_ci` / `utf8mb4_unicode_520_ci` は濁音・半濁音を同一視します（「ぱぱ」=「はは」）。日本語環境では `utf8mb4_general_ci` を推奨します。

文字コードは各ドライバの接続 API で設定されます（SQL の `SET NAMES` には依存しません）。

| エンジン | 方式 | 設定値 |
|---------|------|--------|
| MySQL | `mysqli::set_charset()` | DSN `?charset=` → `DB_CHARSET` → `utf8mb4` |
| PostgreSQL | `pg_connect()` の `client_encoding` | `UTF8`（固定） |
| SQLite | 内部 UTF-8 | 設定不要 |
| RDS Data API | Aurora インスタンス設定 | サーバー側で制御 |

WordPress の `$wpdb->set_charset()` は no-op です。ドライバが接続時に文字コードを設定済みのため、SQL レベルでの再設定は不要です。

#### 読み書き分離（Reader/Writer Split）

```php
define('DATABASE_DSN', 'mysql://user:pass@primary:3306/mydb');
define('DATABASE_READER_DSN', 'mysql://user:pass@replica:3306/mydb');
```

Reader DSN が定義されている場合、SELECT/SHOW/DESCRIBE/EXPLAIN は reader ドライバで、INSERT/UPDATE/DELETE/CREATE 等は writer ドライバで実行されます。

#### オプトアウト

`wpdb://default` を指定すると db.php ドロップインは何もせず、WordPress 標準の `$wpdb`（mysqli 直接接続）がそのまま使用されます。WpPack のドライバ抽象化やクエリ変換は適用されません。

## Production 運用

### 観測性 (Observability)

#### PSR-3 Logger

`WpPackWpdb` に `LoggerInterface` を注入すると、クエリ実行の成功/失敗/遅延をログに記録します。

```php
use WpPack\Component\Database\WpPackWpdb;

$wpdb = new WpPackWpdb(
    writer: $driver,
    translator: $driver->getQueryTranslator(),
    dbname: 'mydb',
    logger: $psrLogger,
);
```

| レベル | 発火条件 | デフォルト context |
|--------|----------|--------------------|
| `debug` | 全クエリ成功時 | `sql`, `params` (型概要), `time_ms`, `driver` |
| `warning` | スロークエリ閾値超え | `debug` の内容 + `slow_threshold_ms` |
| `warning` | Translator が未知ステートメント / ネスト BEGIN 等 | `sql`, `errors` / `previous_depth` |
| `error` | クエリ失敗 / 翻訳失敗 | `sql`, `params` (型概要), `error` |

**PII 保護**: `params` は `['#0' => 'string(21)', '#1' => 'int']` 形式の**型+長さの要約** を送り、生の値 (password hash / session token / email) は流出しません。SOC2/GDPR 要件下でも APM / Elastic / New Relic に直接流せます。

`WPPACK_DB_LOG_VALUES=1` 環境変数で生の値 + interpolated SQL を `raw_params` / `interpolated_sql` として追加出力できます (ローカル debug 用。production では設定しないでください)。

#### PSR-14 Event Dispatcher

APM 連携 (OpenTelemetry span, X-Ray subsegment, New Relic segment) 用に PSR-14 イベントを dispatch します。

```php
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\Database\Event\DatabaseQueryCompletedEvent;
use WpPack\Component\Database\Event\DatabaseQueryFailedEvent;

$wpdb = new WpPackWpdb(
    writer: $driver,
    translator: $driver->getQueryTranslator(),
    dbname: 'mydb',
    eventDispatcher: $dispatcher,
);

// リスナー例 (OpenTelemetry span 作成)
$listener = function (DatabaseQueryCompletedEvent $e) use ($tracer): void {
    $span = $tracer->spanBuilder('db.query')
        ->setAttribute('db.statement', $e->sql)
        ->setAttribute('db.system', $e->driverName)
        ->startSpan();
    $span->setAttribute('db.rows_returned', $e->rowCount);
    $span->end(); // elapsedMs は span duration で代替
};
```

Payload も `paramsSummary` 経由で redact 済みです。

#### スロークエリ検出

環境変数 `WPPACK_DB_SLOW_QUERY_MS` を正の数値に設定すると、その閾値以上 (単位: ms) のクエリが logger の **warning** レベルで記録されます。APM のデフォルトで拾える水準なので、production log pipeline に直接流せます。

```bash
WPPACK_DB_SLOW_QUERY_MS=500   # 500ms 超のクエリを warning に昇格
```

未設定 / 0 / 非数値の場合はデフォルト動作 (全クエリ `debug`)。

#### SAVEQUERIES の上限

`define('SAVEQUERIES', true)` 下で `$wpdb->queries[]` に記録される件数の上限は環境変数 `WPPACK_DB_QUERIES_MAX` で調整 (デフォルト 10000)。WP-CLI 等の長寿命プロセスで OOM 化する典型的な地雷を防ぎます。

### 接続耐障害性

#### MySQL `server has gone away` 検出

`MysqlDriver` は mysqli errno `2006` (server has gone away) / `2013` (lost connection during query) を検出し、接続 handle を drop して `$this->connection = null` に戻します。次のクエリで `ensureConnected()` が自動再接続するため、WP-CLI worker が `wait_timeout` を超えても自己回復します。write の部分適用リスクを避けるため自動 retry はしません (`last_error` で返す) — caller 側で判定してください。

#### PostgreSQL gone-away 検出

`PgsqlDriver` は同等の仕組みで `server closed the connection`, `terminating connection due to administrator command`, `SSL SYSCALL error` といった fatal disconnect 系エラー、および `pg_connection_status !== CONNECTION_OK` を検出して handle を drop します。Aurora DSQL/RDS のフェイルオーバーや `pg_terminate_backend()` 後も透過的に再接続します。

#### Persistent Connection

WP-CLI / キューワーカー等で TCP ハンドシェイクコストが支配的な場合、両 driver の `persistent: true` コンストラクタ引数で persistent connection に opt-in できます。

```php
new MysqlDriver(host: 'localhost', ..., persistent: true);  // 'p:host' に自動変換
new PgsqlDriver(host: 'localhost', ..., persistent: true);  // pg_pconnect を使用
```

デフォルトは `false` (wpdb 標準の per-request 接続)。`setCompatibleSqlMode()` は checkout 毎に走るので pool から取り出した handle の session state は reset されます。

### Reader/Writer 分離

`DATABASE_READER_DSN` で reader replica を指定すると SELECT/SHOW/DESCRIBE/EXPLAIN/PRAGMA が reader driver に、書き込み系が writer に routing されます。

**Read-your-own-writes**: INSERT/UPDATE/DELETE/DDL/BEGIN/SAVEPOINT のいずれかが実行されたリクエストでは、以降の SELECT も writer 固定になります。replication lag 下で「書いた直後に読み出すと古いデータ」を防ぐため、request 終了までの sticky 状態を保持します。

長寿命 WP-CLI worker で unit of work を区切りたい場合は `$wpdb->resetReaderStickiness()` を明示呼び出しします。

### Transaction 深度トラッキング

`BEGIN` / `START TRANSACTION` / `SAVEPOINT` でカウンタを +1、`COMMIT` / `ROLLBACK` / `RELEASE SAVEPOINT` で -1 して内部的に transaction 深度を追跡します。depth > 0 の状態でさらに `BEGIN` が実行されると MySQL は silently 外側の transaction を commit してしまう footgun があるため、logger に warning を記録します。`$wpdb->getTransactionDepth()` で現在の深度を問い合わせ可能です。

### 環境変数一覧

| 変数 | 既定値 | 用途 |
|------|--------|------|
| `DATABASE_DSN` | - | 書き込み driver の DSN。db.php drop-in が参照 |
| `DATABASE_READER_DSN` | - | reader driver の DSN。省略時は writer のみ |
| `WPPACK_DB_LOG_VALUES` | `0` | `1`/`true` で生パラメータ + interpolated SQL を logger に出す (**dev 限定**) |
| `WPPACK_DB_SLOW_QUERY_MS` | - | 正数値で設定した ms 以上のクエリを warning に昇格 |
| `WPPACK_DB_QUERIES_MAX` | `10000` | SAVEQUERIES 下での `$wpdb->queries[]` 保持上限 |
| `WPPACK_TEST_DB_PORT` | `3306` | テスト用 MySQL port (CI matrix で使用) |

### Aurora DSQL 固有の運用

- **IAM トークン:** `async-aws/core` の SigV4 presign で自動生成。token 期限の **120 秒前** に自動再接続 (以前は 60s、async-aws の credential 取得 latency と pg_connect round-trip を考慮して 120s に拡大)。
- **Transaction 中の token 更新:** `ensureTokenFresh()` は `inTransaction()` 中は no-op。transaction 中に connection を drop すると server 側で silently abort してしまうため、`transaction()` helper の retry boundary でのみ refresh します。
- **OCC retry:** SQLSTATE `40001` / `OC000` / `OC001` を検出して指数バックオフ + decorrelated jitter (`random_int(waitMs/2, waitMs)`) で retry。`waitMs` は 100ms 初期 → 2倍 → 上限 5s。`occMaxRetries` (DSN パラメータ、デフォルト 3) で合計リトライ回数を制御。

### RDS Data API 固有の制約

- **レスポンスサイズ:** Data API は 1 API コール当たり ~1 MB に固定的に制限されています。ページング API は無く、大量 SELECT は SQL の `LIMIT` / keyset pagination で事前に抑える必要があります。`DataApiDriverTrait` は 5000 行を超える応答を検出すると logger に warning を出します。
- **エラー分類:** AWS 側の例外 (Throttling / Timeout / ExpiredToken / InvalidSignature) を `DriverThrottledException` / `DriverTimeoutException` / `CredentialsExpiredException` に分類して throw します。caller が safe-to-retry / auth refresh / fatal を区別できます。
- **`lastInsertId()`:** HTTP-stateless のため session 間で `SELECT lastval()` は undefined になります。PgsqlDataApiDriver は失敗を suppress して 0 を返します。

### Translator キャッシュ

`CachedQueryTranslator` で任意の translator を LRU memoize できます。同じ SQL が多数流れる high-traffic 環境や WP-CLI worker で `phpmyadmin/sql-parser` の parse コストを削減します。

```php
use WpPack\Component\Database\Translator\CachedQueryTranslator;
use WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator;

$translator = new CachedQueryTranslator(
    new PostgresqlQueryTranslator($driver),
    capacity: 256,
);
```

throw された例外もキャッシュされるため、同じ parse failure に対して何度も parser を回しません。DDL 発行後にキャッシュを無効化したい場合は `$translator->clear()`。

## 依存関係

### 必須
- `wppack/dsn` — DSN パーサー

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
