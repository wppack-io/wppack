# プラグイン比較: SQLite Database Integration / PG4WP

WpPack Database コンポーネントの MySQL→SQLite / MySQL→PostgreSQL クエリ変換機能を、WordPress エコシステムの既存プラグインと比較する。

## 概要

| | SQLite Database Integration | PG4WP | WpPack |
|---|---|---|---|
| リポジトリ | wordpress/sqlite-database-integration | PostgreSQL-For-Wordpress/postgresql-for-wordpress | wppack-io/wppack |
| エンジン | SQLite | PostgreSQL | SQLite + PostgreSQL + Aurora DSQL |
| アーキテクチャ | 独自 Lexer + トークン書き換え + UDF 46個 | 正規表現ベース文字列置換 | AST (phpmyadmin/sql-parser) + QueryRewriter + UDF 14個 |
| テスト | WordPress e2e 依存 | 504 スタブベーステスト | 608 ユニットテスト / 1,024 アサーション |

## 対応範囲一覧

全4実装の対応状況を一覧で示す。

- ✅ = 対応済
- N/A = そのエンジンでは対応不要（ネイティブ対応 or 該当なし）
- — = 未対応

### 関数

| MySQL 関数 | SQLite プラグイン | PG4WP | WpPack SQLite | WpPack PgSQL |
|-----------|:---:|:---:|:---:|:---:|
| NOW() | ✅ UDF | N/A ネイティブ | ✅ AST | N/A ネイティブ |
| CURDATE() / CURTIME() | ✅ UDF | — | ✅ AST | ✅ AST |
| UNIX_TIMESTAMP() | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| FROM_UNIXTIME() | ✅ UDF | — | ✅ AST | ✅ AST |
| UTC_TIMESTAMP/DATE/TIME | ✅ UDF | — | ✅ AST | ✅ AST |
| DATE_ADD / DATE_SUB | ✅ トークン | ✅ | ✅ AST | ✅ AST |
| DATE_FORMAT | ✅ トークン | — | ✅ AST (全31仕様) | ✅ AST (全31仕様) |
| MONTH/YEAR/DAY | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| HOUR/MINUTE/SECOND | ✅ UDF | — | ✅ AST | ✅ AST |
| DAYOFWEEK/WEEKDAY | ✅ UDF | — | ✅ AST | ✅ AST |
| WEEK(d, mode) | ✅ UDF | — | ✅ AST | ✅ AST |
| DATEDIFF | ✅ UDF | — | ✅ AST | ✅ AST |
| RAND() | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| CONCAT / CONCAT_WS | ✅ トークン | N/A ネイティブ | ✅ AST | N/A ネイティブ |
| LEFT / RIGHT | ✅ トークン | N/A ネイティブ | ✅ AST | ✅ AST/ネイティブ |
| SUBSTRING / CHAR_LENGTH | ✅ トークン | N/A ネイティブ | ✅ AST | N/A ネイティブ |
| LOCATE | ✅ UDF | — | ✅ AST | ✅ AST |
| MID / LCASE / UCASE | ✅ UDF (LCASE/UCASE) | — | ✅ AST | ✅ AST |
| IF() | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| IFNULL | ✅ ネイティブ | N/A COALESCE | ✅ ネイティブ | ✅ AST→COALESCE |
| ISNULL | ✅ UDF | — | ✅ AST | ✅ AST |
| GREATEST / LEAST | ✅ UDF | N/A ネイティブ | ✅ AST | N/A ネイティブ |
| FIELD() | ✅ UDF | ✅ | ✅ AST→CASE | ✅ AST→CASE |
| CONVERT() | — | ✅ | ✅ AST | ✅ AST |
| CAST(AS SIGNED/CHAR/BINARY) | — | ✅ | ✅ AST | ✅ AST |
| GROUP_CONCAT | ✅ ネイティブ | ✅ | ✅ AST→group_concat | ✅ AST→STRING_AGG |
| VERSION / DATABASE | ✅ UDF | N/A ネイティブ | ✅ AST | ✅ AST |
| FOUND_ROWS() | ✅ | ✅ | ✅ | ✅ |
| LAST_INSERT_ID | — | ✅ | ✅ AST | ✅ AST |
| REGEXP | ✅ UDF | ✅ | ✅ UDF | ✅ AST→~* |
| MD5 / LOG | ✅ UDF | N/A ネイティブ | ✅ UDF | N/A ネイティブ |
| UNHEX / BASE64 / INET | ✅ UDF | N/A | ✅ UDF | ✅ AST (decode/encode) |
| GET_LOCK / RELEASE_LOCK | ✅ UDF | N/A | ✅ UDF | ✅ ダミー |

### DML 文

| 機能 | SQLite プラグイン | PG4WP | WpPack SQLite | WpPack PgSQL |
|------|:---:|:---:|:---:|:---:|
| INSERT IGNORE | ✅ | ✅ | ✅ | ✅ |
| REPLACE INTO | ✅ | ✅ | ✅ | ✅ |
| INSERT ... SET | — | — | ✅ | ✅ |
| ON DUPLICATE KEY UPDATE | ✅ | ✅ | ✅ | ✅ |
| LIMIT offset, count | ✅ | ✅ | ✅ | ✅ |
| UPDATE/DELETE LIMIT | ✅ | ⚠️ 除去 | ✅ rowid | ✅ ctid |
| DELETE JOIN | ✅ | — | ✅ rowid | ✅ USING |
| FOR UPDATE | N/A | N/A | ✅ 除去 | N/A ネイティブ |
| SQL_CALC_FOUND_ROWS | ✅ | ✅ | ✅ | ✅ |
| FROM DUAL | ✅ | — | ✅ | ✅ |
| INDEX HINTS | ✅ | — | ✅ | ✅ |
| LIKE → ILIKE (PgSQL) | N/A | ✅ | N/A | ✅ |
| LIKE BINARY → GLOB | ✅ | — | ✅ | ✅ LIKE |
| LIKE ESCAPE 自動付与 | ✅ | — | ✅ `ESCAPE '\'` | ✅ `ESCAPE '\'` |
| HAVING without GROUP BY | ✅ | ✅ | ✅ | ✅ |
| CONVERT → CAST | — | ✅ | ✅ | ✅ |
| COLLATE 除去 | — | — | ✅ | ✅ |
| @@変数 → デフォルト値 | — | — | ✅ | ✅ |
| 空 IN () → IN (NULL) | — | — | ✅ | ✅ |
| DISTINCT + ORDER BY 列注入 | N/A | ✅ | N/A | ✅ |
| meta_value + 0 → CAST | N/A | ✅ | N/A | ✅ |
| ゼロ日付処理 | ✅ | — | ✅ text | ✅ → '0001-01-01' |
| LOW_PRIORITY / DELAYED | ✅ | — | ✅ | ✅ |
| START TRANSACTION | ✅ | — | ✅ | ✅ |
| SAVEPOINT | — | — | ✅ | ✅ |

### DDL

| 機能 | SQLite プラグイン | PG4WP | WpPack SQLite | WpPack PgSQL |
|------|:---:|:---:|:---:|:---:|
| CREATE TABLE 型変換 | ✅ | ✅ | ✅ AST | ✅ AST |
| PRIMARY KEY マージ | ✅ | N/A | ✅ AST | N/A |
| AUTO_INCREMENT → SERIAL | N/A | ✅ | ✅ AUTOINCREMENT | ✅ SERIAL/BIGSERIAL |
| ON UPDATE CURRENT_TIMESTAMP | ✅ トリガー | — | ✅ トリガー | ✅ トリガー |
| ALTER ADD/DROP/CHANGE | ✅ | ✅ | ✅ | ✅ |
| KEY/INDEX → CREATE INDEX 分離 | ✅ | ✅ | ✅ AST | ✅ AST |
| ENGINE/CHARSET/COLLATE 除去 | ✅ | ✅ | ✅ AST | ✅ AST |
| IF NOT EXISTS | ✅ | ✅ | ✅ | ✅ |
| DDL ゼロ日付 DEFAULT 変換 | — | — | — (TEXT) | ✅ → '0001-01-01' |
| 識別子ケース正規化 | — | ✅ | — (不要) | ✅ 小文字化 |
| データ型キャッシュ | ✅ | N/A | ✅ | N/A |
| information_schema 対応 | ✅ | N/A | ✅ → sqlite_master | N/A ネイティブ |
| ISO 8601 日付正規化 | ✅ | N/A | ✅ | N/A PgSQL 互換 |

### SHOW 文

| 機能 | SQLite プラグイン | PG4WP | WpPack SQLite | WpPack PgSQL |
|------|:---:|:---:|:---:|:---:|
| SHOW TABLES [LIKE] | ✅ | ✅ | ✅ | ✅ |
| SHOW FULL TABLES | ✅ | — | ✅ | ✅ |
| SHOW COLUMNS FROM | ✅ | ✅ | ✅ | ✅ |
| SHOW CREATE TABLE | ✅ | — | ✅ | ✅ |
| SHOW INDEX FROM | ✅ | ✅ | ✅ | ✅ |
| SHOW TABLE STATUS [LIKE] | ✅ | ✅ | ✅ | ✅ |
| SHOW VARIABLES | ✅ | ✅ | ✅ | ✅ |
| SHOW DATABASES | — | — | ✅ | ✅ |
| SHOW COLLATION | — | — | ✅ | ✅ |
| SHOW GRANTS | ✅ | — | ✅ ダミー | ✅ ダミー |
| DESCRIBE | — | ✅ | ✅ | ✅ |
| CHECK/ANALYZE/REPAIR TABLE | ✅ | — | ✅ ダミー | ✅ ダミー |

## SQLite Database Integration との比較

### コード規模

| | SQLite プラグイン | WpPack SQLite | WpPack PgSQL |
|---|---:|---:|---:|
| トランスレーター | 4,543行 | 1,940行 | 1,867行 |
| QueryRewriter | 343行 | 279行 | 279行 |
| UDF / Driver | 899行（46関数） | 342行（15関数） | — |
| **合計** | **5,785行** | **~2,560行** | **~2,150行** |

WpPack は phpmyadmin/sql-parser の AST を活用することで、プラグインの約半分のコード量で同等以上の機能をカバーする。プラグインは独自 Lexer（2,997行）を含むため、パーサー込みの総コスト差はさらに大きい。

### アーキテクチャ

| | SQLite プラグイン | WpPack |
|---|---|---|
| パーサー | 独自 WP_MySQL_Lexer（2,997行） | phpmyadmin/sql-parser v6.0（標準ライブラリ） |
| 変換方式 | トークンストリーム書き換え + UDF 46個 | AST ルーティング + QueryRewriter + UDF 14個 |
| DDL 処理 | 独自パーサーで AST → SQL | phpmyadmin AST `CreateDefinition[]` から直接構築 |
| 関数変換 | 大半を UDF で実行（行単位で PHP 呼び出し） | AST レベルでネイティブ関数に変換（UDF 最小限） |
| 文字列リテラル保護 | トークン型による判定 | `TokenType::String` で構造的に保証 |
| Prepared Statement | 文字列結合（vsprintf ベース） | ネイティブ `?` パラメータ（Driver 分離） |
| エンジン対応 | SQLite のみ | SQLite + PostgreSQL + Aurora DSQL |

### 関数変換: UDF vs AST

プラグインは46個の UDF を SQLite に登録する方式。WpPack は AST レベルで SQLite ネイティブ関数に変換し、UDF は14個に抑える。

| 方式 | 利点 | 欠点 |
|------|------|------|
| UDF（プラグイン） | MySQL 動作の正確な再現 | 行ごとに PHP 呼び出し → パフォーマンス劣化。インデックス無効化 |
| AST 変換（WpPack） | SQLite ネイティブ実行 → 高速。インデックス有効 | 100%の MySQL 互換性は保証できない |

例: 10,000行の SELECT で NOW() を使用する場合
- UDF: NOW() が10,000回 PHP に呼び出される
- AST: `datetime('now')` に1回変換されて SQLite がネイティブ実行

### 機能カバレッジ: 関数変換

| 関数 | プラグイン | WpPack | 方式の違い |
|------|:---:|:---:|---|
| NOW() | UDF | **AST** | プラグイン: PHP `gmdate()` / WpPack: `datetime('now')` ネイティブ |
| CURDATE() / CURTIME() | UDF | **AST** | 同上 |
| UNIX_TIMESTAMP() | UDF | **AST** | WpPack: `strftime('%s','now')` |
| FROM_UNIXTIME() | UDF | **AST** | WpPack: `datetime(t, 'unixepoch')` |
| UTC_TIMESTAMP/DATE/TIME | UDF | **AST** | 同上 |
| DATE_ADD / DATE_SUB | トークン | **AST** | WpPack: 引数を再帰的に変換 |
| DATE_FORMAT | トークン | **AST** | MySQL 全31仕様対応（プラグインは PHP date() フォーマット含む独自マップ） |
| MONTH / YEAR / DAY | UDF | **AST** | WpPack: `strftime()` → `CAST AS INTEGER` |
| HOUR / MINUTE / SECOND | UDF | **AST** | 同上 |
| DAYOFWEEK / WEEKDAY / WEEK | UDF | **AST** | WpPack: `strftime('%w')` 演算 |
| DATEDIFF | UDF | **AST** | WpPack: `julianday()` 差分 |
| CONCAT | トークン→\|\| | **AST**→\|\| | 同じ出力 |
| LEFT / RIGHT | トークン | **AST** | WpPack: RIGHT も対応 |
| SUBSTRING / CHAR_LENGTH | トークン | **AST** | 同等 |
| MID / LCASE / UCASE | UDF (LCASE/UCASE) | **AST** | プラグイン: UDF / WpPack: `lower()`/`upper()` ネイティブ |
| LOCATE | UDF | **AST** | WpPack: `INSTR()` ネイティブ |
| IF | UDF | **AST** | WpPack: `CASE WHEN ... END` ネイティブ |
| IFNULL | ネイティブ | ネイティブ | 同等 |
| GREATEST / LEAST | UDF | **AST** | WpPack: `MAX/MIN` ネイティブ |
| RAND | UDF | **AST** | WpPack: `random()` ネイティブ |
| VERSION | UDF | **AST** | プラグイン: '5.5' / WpPack: '10.0.0-wppack' |
| LAST_INSERT_ID | — | **AST** | WpPack のみ対応 |
| CONVERT | — | **AST** | WpPack: → `CAST(... AS ...)` |
| ISNULL | UDF | **AST** | WpPack: `(x IS NULL)` 構造変換 |
| MD5 / REGEXP / FIELD | UDF | **UDF** | 同方式 |
| LOG | UDF | **UDF** | WpPack: UDF + 2引数構造変換 |
| UNHEX / BASE64 / INET | UDF | **UDF** | 同方式 |
| GET_LOCK / RELEASE_LOCK | UDF (no-op) | **UDF** (no-op) | 同方式 |
| GROUP_CONCAT | ✅ ネイティブ | **AST** | SQLite: `group_concat(col, sep)` / PgSQL: `STRING_AGG(col, sep)` |

### 機能カバレッジ: 文レベル変換

| 機能 | プラグイン | WpPack |
|------|:---:|:---:|
| INSERT IGNORE | ✅ | ✅ |
| REPLACE INTO | ✅ | ✅ |
| ON DUPLICATE KEY UPDATE | ✅ (PK/UNIQUE 自動検出) | ✅ |
| VALUES(col) → excluded.col | ✅ | ✅ |
| INSERT ... SET | — | ✅ |
| LIMIT offset, count | ✅ | ✅ |
| UPDATE/DELETE ... LIMIT N | ✅ (rowid サブクエリ) | ✅ (rowid サブクエリ) |
| DELETE JOIN | ✅ | ✅ (rowid サブクエリ) |
| FOR UPDATE | — | ✅ (除去) |
| SQL_CALC_FOUND_ROWS | ✅ (カウント保存) | ✅ (カウント保存) |
| FOUND_ROWS() | ✅ (保存値返却) | ✅ (保存値返却) |
| FROM DUAL | ✅ | ✅ |
| INDEX HINTS | ✅ | ✅ |
| HAVING without GROUP BY | ✅ | ✅ |
| LIKE BINARY → GLOB | ✅ | ✅ |
| LIKE ESCAPE | ✅ | ✅ |
| CONVERT → CAST | — | ✅ |
| COLLATE 除去 | — | ✅ |
| @@変数 → デフォルト値 | — | ✅ |
| 空 IN () → IN (NULL) | — | ✅ |
| information_schema → sqlite_master | ✅ | ✅ |
| START TRANSACTION / SAVEPOINT | ✅ / — | ✅ / ✅ |
| LOW_PRIORITY / DELAYED | ✅ | ✅ |

### 機能カバレッジ: DDL

| 機能 | プラグイン | WpPack |
|------|:---:|:---:|
| CREATE TABLE 型変換 | ✅ | ✅ (AST ベース) |
| PRIMARY KEY マージ | ✅ | ✅ (AST ベース) |
| ON UPDATE CURRENT_TIMESTAMP → トリガー | ✅ | ✅ |
| ALTER TABLE ADD/DROP/CHANGE COLUMN | ✅ | ✅ |
| ENGINE / CHARSET / COLLATE 除去 | ✅ | ✅ (AST ベース) |
| IF NOT EXISTS | ✅ | ✅ |
| MySQL データ型キャッシュ | ✅ | ✅ |

### 機能カバレッジ: SHOW 文

| 機能 | プラグイン | WpPack |
|------|:---:|:---:|
| SHOW TABLES [LIKE] | ✅ | ✅ |
| SHOW FULL TABLES | ✅ | ✅ |
| SHOW COLUMNS FROM | ✅ | ✅ |
| SHOW CREATE TABLE | ✅ | ✅ (pragma + キャッシュ) |
| SHOW INDEX FROM | ✅ | ✅ |
| SHOW TABLE STATUS [LIKE] | ✅ | ✅ |
| SHOW VARIABLES | ✅ | ✅ |
| SHOW DATABASES | — | ✅ |
| SHOW COLLATION | — | ✅ |
| SHOW GRANTS / CREATE PROCEDURE | ✅ | ✅ (ダミー) |
| DESCRIBE | — | ✅ |
| CHECK / ANALYZE / REPAIR TABLE | ✅ | ✅ (ダミー) |

### WpPack の優位点

| 機能 | 説明 |
|------|------|
| **PostgreSQL + Aurora DSQL** | プラグインは SQLite のみ。WpPack は3エンジン対応 |
| **AST ベース DDL** | `CreateDefinition[]` から型安全に構築。プラグインはトークン操作 |
| **ネイティブ関数優先** | UDF 14個 vs プラグイン46個。パフォーマンスに直結 |
| **真の Prepared Statement** | `?` パラメータを Driver に分離。SQL インジェクション構造的防止 |
| **Reader/Writer Split** | `DATABASE_READER_DSN` で読み書き分離 |
| **608 ユニットテスト** | プラグインは WordPress e2e テスト依存 |
| **文字列リテラル安全性** | `TokenType::String` による構造的保証 |

### プラグインの優位点

| 機能 | 説明 |
|------|------|
| **WordPress フック統合** | `pre_query_sqlite_db` 等のフックでクエリの前後にカスタム処理を挿入可能 |

## PG4WP との比較

### コード規模

| | PG4WP | WpPack PgSQL |
|---|---:|---:|
| トランスレーター/ドライバ | ~4,000行 | 1,867行 |
| QueryRewriter | — | 279行 |
| **合計** | **~4,000行** | **~2,150行** |

### アーキテクチャ

| | PG4WP | WpPack |
|---|---|---|
| パース方式 | 正規表現ベース文字列置換 | phpmyadmin/sql-parser AST |
| 関数変換 | ~20関数 | 50+関数 |
| 型マッピング | 基本的（~15型） | 包括的（25+型、JSONB 含む） |
| Prepared Statement | なし | ネイティブ `?` パラメータ |
| Reader/Writer Split | なし | DATABASE_READER_DSN 対応 |

### WpPack のみの機能（PG4WP にない）

50以上の機能が WpPack のみ対応。

#### 関数（PG4WP 未対応 → WpPack 対応）

| MySQL | WpPack PgSQL 変換先 | カテゴリ |
|-------|-------------------|---------|
| `CURDATE()` / `CURTIME()` | `CURRENT_DATE` / `CURRENT_TIME` | 日時 |
| `UTC_TIMESTAMP()` / `UTC_DATE()` / `UTC_TIME()` | `NOW() AT TIME ZONE 'UTC'` 等 | 日時 |
| `LOCALTIME()` / `LOCALTIMESTAMP()` | `NOW()` | 日時 |
| `DATE_FORMAT(d, fmt)` | `TO_CHAR(d, fmt)` (全31仕様) | 日時 |
| `FROM_UNIXTIME(t)` | `TO_TIMESTAMP(t)` | 日時 |
| `DATEDIFF(d1, d2)` | `DATE_PART('day', d1 - d2)` | 日時 |
| `HOUR(d)` / `MINUTE(d)` / `SECOND(d)` | `EXTRACT(HOUR/MINUTE/SECOND FROM d)` | 抽出 |
| `DAYOFWEEK(d)` / `WEEKDAY(d)` / `WEEK(d)` | `EXTRACT(DOW/ISODOW/WEEK FROM d)` | 抽出 |
| `DAYOFYEAR(d)` | `EXTRACT(DOY FROM d)` | 抽出 |
| `CONCAT(a, b)` / `CONCAT_WS(sep, a, b)` | ネイティブ（引数変換付き） | 文字列 |
| `LEFT(s, n)` | `SUBSTRING(s FROM 1 FOR n)` | 文字列 |
| `LOCATE(sub, str)` | `POSITION(sub IN str)` | 文字列 |
| `CHAR_LENGTH(s)` / `MID(s, p, n)` | `LENGTH(s)` / `SUBSTRING(s, p, n)` | 文字列 |
| `LCASE(s)` / `UCASE(s)` | `lower(s)` / `upper(s)` | 文字列 |
| `ISNULL(x)` | `(x IS NULL)` | 比較 |
| `IFNULL(a, b)` | `COALESCE(a, b)` | 比較 |
| `VERSION()` / `DATABASE()` | `version()` / `CURRENT_DATABASE()` | システム |
| `CONVERT(val, type)` | `CAST(val AS type)` | 型変換 |
| `CAST(AS CHAR)` | `CAST(AS TEXT)` | 型変換 |

#### DML 文（PG4WP 未対応 → WpPack 対応）

| MySQL 構文 | WpPack PgSQL 変換 |
|-----------|-----------------|
| `TRUNCATE TABLE t` | `TRUNCATE ... RESTART IDENTITY` |
| `START TRANSACTION` | `BEGIN` |
| `SAVEPOINT / RELEASE / ROLLBACK TO` | ネイティブ |
| `INSERT ... SET col=val` | `INSERT INTO (col) VALUES (val)` |
| `DELETE JOIN` | `DELETE ... USING ... WHERE ...` |
| `UPDATE/DELETE ... LIMIT N` | `ctid IN (SELECT ctid ... LIMIT N)` |
| `LIKE` | `ILIKE`（大文字小文字区別なし） |
| `LIKE ESCAPE` | `ESCAPE '\x1a'` 句付与 |
| `CONVERT(val, type)` | `CAST(val AS type)` |
| `COLLATE utf8mb4_*` | 除去 |
| `SELECT @@SESSION.sql_mode` | 変数名別デフォルト値返却 |
| `IN ()` (空) | `IN (NULL)` |
| `LOW_PRIORITY` / `DELAYED` | 除去 |
| `'0000-00-00 00:00:00'` | `'0001-01-01 00:00:00'` |

#### SHOW 文（PG4WP 未対応 → WpPack 対応）

| MySQL SHOW | WpPack PgSQL 変換先 |
|-----------|-------------------|
| `SHOW CREATE TABLE t` | `information_schema.columns` から再構築 |
| `SHOW DATABASES` | `pg_database` クエリ |
| `SHOW COLLATION` | `pg_collation` クエリ |
| `SHOW GRANTS` | ダミー GRANT 文 |
| `SHOW CREATE PROCEDURE` | 空結果 |
| `CHECK / ANALYZE / REPAIR TABLE` | ダミー OK 結果 |

#### インフラ（PG4WP にない機能）

| 機能 | 説明 |
|------|------|
| **ネイティブ Prepared Statement** | `?` パラメータを Driver に分離。PG4WP は文字列結合 |
| **Reader/Writer Split** | `DATABASE_READER_DSN` で読み書き分離 |
| **AST ベース解析** | phpmyadmin/sql-parser。PG4WP は正規表現 |
| **JSON → JSONB** | PG4WP は JSON 型未対応 |
| **BLOB → BYTEA** | PG4WP は BLOB 型未対応 |
| **DISTINCT + ORDER BY 列注入** | ORDER BY 列を SELECT に自動追加 |
| **meta_value + 0 → CAST** | `CAST(meta_value AS BIGINT)` に自動変換 |
| **SERIAL シーケンス自動同期** | 明示 ID INSERT 後に setval() で次値をリセット |
| **TRUNCATE RESTART IDENTITY** | TRUNCATE 時にシーケンスを1にリセット（MySQL 互換） |
| **AS 'alias' → AS "alias"** | PgSQL はシングルクォート識別子非対応。プラグイン互換 |
| **ALTER TABLE ADD/DROP INDEX** | `CREATE INDEX` / `DROP INDEX IF EXISTS` に変換 |
| **COUNT(*) ORDER BY 除去** | 不要な ORDER BY をパフォーマンス向上のため除去 |
| **608 ユニットテスト** | PG4WP は504スタブテスト |

### PG4WP のみの機能（WpPack にない）

| 機能 | 説明 | WordPress 影響 | 実装必要性 |
|------|------|---------------|-----------|
| Akismet comment_ID 正規化 | 小文字 `comment_id` → 大文字 `comment_ID` | 低 | 不要（Akismet プラグイン固有のバグ。WP コアは常に正しいケースを使用） |
| wp_options マルチ行 INSERT | 複数行 INSERT を個別実行 | なし | 不要（PgSQL はマルチ行 INSERT をネイティブサポート） |

※ NextGen Gallery AS クォート（`AS 'alias'` → `AS "alias"`）は WpPack で汎用的に対応済み。

## 総合評価

| 評価軸 | SQLite プラグイン | PG4WP | WpPack |
|--------|:---:|:---:|:---:|
| 関数カバレッジ | ★★★ (46 UDF) | ★★ (~20) | ★★★★ (50+ AST変換 + 14 UDF) |
| パフォーマンス | ★★ (UDF オーバーヘッド) | ★★★ | ★★★★ (ネイティブ関数優先) |
| DDL 対応 | ★★★★ | ★★★ | ★★★★ |
| SHOW 対応 | ★★★ | ★★★ | ★★★★ |
| 型安全性 | ★★ (文字列結合) | ★★ | ★★★★ (Prepared Statement) |
| マルチエンジン | ★ (SQLite のみ) | ★ (PgSQL のみ) | ★★★★★ (SQLite + PgSQL + DSQL) |
| テスト | ★★ (e2e 依存) | ★★★ (504 スタブ) | ★★★★★ (608 ユニットテスト) |
| WP 固有対応 | ★★★★★ | ★★★★ | ★★★★★ |
