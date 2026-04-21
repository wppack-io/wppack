# DatabaseExport コンポーネント

**パッケージ:** `wppack/database-export`
**名前空間:** `WPPack\Component\DatabaseExport\`
**Category:** Data

WordPress データベースを SQL / JSON / CSV 形式でエクスポートする。wpress（All-in-One WP Migration）互換の SQL 出力に対応。MySQL、MariaDB、SQLite、PostgreSQL の各ソース DB からの読み取りに対応。

## インストール

```bash
composer require wppack/database-export
```

## マルチエンジン対応

どのデータベースエンジンから読み取っても、SQL 出力は **MySQL フォーマット**（wpress 互換）で生成される。

```
MySQL DB      ──→ MySQL SQL 出力
SQLite DB     ──→ MySQL SQL 出力（型変換付き）
PostgreSQL DB ──→ MySQL SQL 出力（型変換付き）
Aurora DSQL   ──→ MySQL SQL 出力（型変換付き）
```

| 機能 | MySQL | SQLite | PostgreSQL | DSQL |
|------|:---:|:---:|:---:|:---:|
| JSON エクスポート | ✅ | ✅ | ✅ | ✅ |
| CSV エクスポート | ✅ | ✅ | ✅ | ✅ |
| SQL エクスポート | ✅ | ✅ | ✅ | ✅ |
| スキーマ読み取り | ✅ MySQLSchemaReader | ✅ SqliteSchemaReader | ✅ PostgreSQLSchemaReader | ✅ PostgreSQLSchemaReader |

## アーキテクチャ

```
SchemaReader（エンジン固有）
    ↓ テーブル一覧 + スキーマ（MySQL DDL 形式）
TableFilter（WordPress テーブルフィルタ）
    ↓ 対象テーブル
DatabaseExporter
    ↓ バッチ SELECT でデータ読み取り
RowTransformer（WordPress 固有の変換）
    ↓ 行データ
ExportWriter（フォーマット別出力）
    └── WpressSqlWriter (MySQL SQL)
    └── JsonWriter (JSON)
    └── CsvWriter (CSV)
```

## SchemaReader

各エンジンのスキーマを MySQL 互換 CREATE TABLE SQL に変換する。

| SchemaReader | エンジン | スキーマ取得方法 |
|-------------|--------|---------------|
| `MySQLSchemaReader` | MySQL / MariaDB | `SHOW CREATE TABLE`（Database コア） |
| `MariadbSchemaReader` | MariaDB | `MySQLSchemaReader` 継承（Database コア） |
| `SqliteSchemaReader` | SQLite | `PRAGMA table_info` + `_mysql_data_types_cache`（Bridge/Sqlite） |
| `PostgreSQLSchemaReader` | PostgreSQL / DSQL | `information_schema.columns` + `pg_index`（Bridge/PostgreSQL） |

### SQLite → MySQL 型変換

SQLite の `_mysql_data_types_cache` テーブル（クエリ変換時に自動作成）から元の MySQL 型を復元。キャッシュがない場合はアフィニティベースで変換:

| SQLite | MySQL |
|--------|-------|
| `INTEGER` | `bigint(20)` |
| `TEXT` | `longtext` |
| `REAL` | `double` |
| `BLOB` | `longblob` |

### PostgreSQL → MySQL 型変換

| PostgreSQL | MySQL |
|-----------|-------|
| `bigint` / `bigserial` | `bigint(20)` / `bigint(20) unsigned` |
| `integer` / `serial` | `int(11)` / `int(11) unsigned` |
| `text` | `longtext` |
| `character varying(N)` | `varchar(N)` |
| `timestamp` | `datetime` |
| `bytea` | `longblob` |
| `jsonb` / `json` | `json` |
| `boolean` | `tinyint(1)` |

## ExportWriter

### WpressSqlWriter

MySQL 互換の SQL ダンプを生成。wpress / All-in-One WP Migration と互換。

- `DROP TABLE IF EXISTS` + `CREATE TABLE` + `INSERT` 文
- `START TRANSACTION` / `COMMIT` でバッチラップ
- テーブルプレフィックスをプレースホルダ (`WPPACK_PREFIX_`) に置換
- MySQL エスケープ（`\'`, `\n`, `\r`, `\0`, `\Z`）
- バイナリデータは `0x...` hex リテラル

### JsonWriter

ストリーミング JSON:
```json
{"tables": {"wp_posts": {"columns": [...], "rows": [[...], ...]}}}
```

### CsvWriter

RFC 4180 CSV:
```
--- wp_posts ---
ID,post_title,post_status
1,"Hello World","publish"
```

## マルチサイト対応

`ExportConfiguration::$blogIds` で対象サイトを制御できます。

| blogIds | 動作 |
|---------|------|
| `[]`（空） | ネットワーク全体 |
| `[1]` | メインサイトのみ |
| `[2]` | サブサイト 2 のみ |

## セキュリティデフォルト

- トランジェント（`_transient_*`）はデフォルトで除外
- セッショントークン（`session_tokens`）はデフォルトで除外
- `active_plugins` は空配列 `a:0:{}` にリセット
- `template` / `stylesheet` は空文字にリセット

## 設定 (ExportConfiguration)

| オプション | デフォルト | 説明 |
|----------|----------|------|
| `excludeTables` | `[]` | 除外テーブル名リスト |
| `includeTables` | `[]` | 含めるテーブル名リスト（空 = 全テーブル） |
| `batchSize` | `1000` | SELECT バッチサイズ |
| `transactionSize` | `500` | SQL 出力のトランザクションサイズ |
| `excludeOptionPrefixes` | `['_transient_', ...]` | wp_options から除外するプレフィックス |
| `resetActivePlugins` | `true` | active_plugins を空にリセット |
| `resetTheme` | `false` | テーマをリセット |
| `replacePrefixInValues` | `true` | 値中のテーブルプレフィックスをプレースホルダに置換 |

## 使用例

```php
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\Bridge\Sqlite\SchemaReader\SqliteSchemaReader;
use WPPack\Component\DatabaseExport\DatabaseExporter;
use WPPack\Component\DatabaseExport\ExportConfiguration;
use WPPack\Component\DatabaseExport\Writer\WpressSqlWriter;

$db = new DatabaseManager(); // 接続先は db.php の DSN で決定
$reader = new SqliteSchemaReader();
$config = new ExportConfiguration(dbPrefix: 'wp_');
$writer = new WpressSqlWriter($outputStream);

$exporter = new DatabaseExporter($db, $reader, $config);
$exporter->export($writer);
```

## 依存関係

```
wppack/database-export
    ↓ requires
wppack/database
    + wppack/wpress (suggest — wpress アーカイブ操作)
```

## 関連ドキュメント

- [Database コンポーネント](../database/README.md) — ドライバ、プラットフォーム、クエリ変換
- [Query Translation Architecture](../database/query-translation.md) — MySQL → SQLite/PostgreSQL 変換の詳細
- [プラグイン比較](../database/plugin-comparison.md) — 既存プラグインとの機能比較
