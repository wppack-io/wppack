# プラグイン比較: SQLite Database Integration / PG4WP

WpPack Database コンポーネントの MySQL→SQLite / MySQL→PostgreSQL クエリ変換機能を、WordPress エコシステムの既存プラグインと比較する。

## 概要

| | SQLite Database Integration | PG4WP | WpPack |
|---|---|---|---|
| リポジトリ | wordpress/sqlite-database-integration | PostgreSQL-For-Wordpress/postgresql-for-wordpress | wppack-io/wppack |
| エンジン | SQLite | PostgreSQL | SQLite + PostgreSQL + Aurora DSQL |
| アーキテクチャ | 独自 Lexer + トークン書き換え + UDF 46個 | 正規表現ベース文字列置換 | AST (phpmyadmin/sql-parser) + QueryRewriter + UDF 15個 |
| テスト | WordPress e2e 依存 | 504 スタブベーステスト | 574 ユニットテスト / 972 アサーション |

## 対応範囲一覧

全4実装の対応状況を一覧で示す。✅ = 対応、⚠️ = 部分的、— = 未対応。

### 関数

| MySQL 関数 | SQLite プラグイン | PG4WP | WpPack SQLite | WpPack PgSQL |
|-----------|:---:|:---:|:---:|:---:|
| NOW() | ✅ UDF | — | ✅ AST | ✅ ネイティブ |
| CURDATE() / CURTIME() | ✅ UDF | — | ✅ AST | ✅ AST |
| UNIX_TIMESTAMP() | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| FROM_UNIXTIME() | ✅ UDF | — | ✅ AST | ✅ AST |
| UTC_TIMESTAMP/DATE/TIME | ✅ UDF | — | ✅ AST | ✅ AST |
| DATE_ADD / DATE_SUB | ✅ トークン | ✅ | ✅ AST | ✅ AST |
| DATE_FORMAT | ✅ トークン | — | ✅ AST (30仕様) | ✅ AST (30仕様) |
| MONTH/YEAR/DAY | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| HOUR/MINUTE/SECOND | ✅ UDF | — | ✅ AST | ✅ AST |
| DAYOFWEEK/WEEKDAY | ✅ UDF | — | ✅ AST | ✅ AST |
| WEEK(d, mode) | ✅ UDF | — | ✅ AST | ✅ AST |
| DATEDIFF | ✅ UDF | — | ✅ AST | ✅ AST |
| RAND() | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| CONCAT / CONCAT_WS | ✅ トークン | — | ✅ AST | ✅ ネイティブ |
| LEFT / RIGHT | ✅ トークン | — | ✅ AST | ✅ AST/ネイティブ |
| SUBSTRING / CHAR_LENGTH | ✅ トークン | — | ✅ AST | ✅ AST |
| LOCATE | ✅ UDF | — | ✅ AST | ✅ AST |
| MID / LCASE / UCASE | — | — | ✅ AST | ✅ AST |
| IF() | ✅ UDF | ✅ | ✅ AST | ✅ AST |
| IFNULL | ✅ ネイティブ | — | ✅ ネイティブ | ✅ AST→COALESCE |
| ISNULL | ✅ UDF | — | ✅ AST | ✅ AST |
| GREATEST / LEAST | ✅ UDF | — | ✅ AST | ✅ ネイティブ |
| FIELD() | ✅ UDF | ✅ | ✅ UDF | ✅ AST→CASE |
| CONVERT() | — | ✅ | ✅ AST | ✅ AST |
| CAST(AS SIGNED/CHAR/BINARY) | — | ✅ | ✅ AST | ✅ AST |
| GROUP_CONCAT | — | ✅ | ✅ ネイティブ | ✅ AST→STRING_AGG |
| VERSION / DATABASE | ✅ UDF | — | ✅ AST | ✅ AST |
| FOUND_ROWS() | ✅ | ✅ | ✅ | ✅ |
| LAST_INSERT_ID | — | ✅ | ✅ AST | ✅ AST |
| REGEXP | ✅ UDF | ✅ | ✅ UDF | ✅ AST→~* |
| MD5 / LOG | ✅ UDF | — | ✅ UDF | ✅ ネイティブ |
| UNHEX / BASE64 / INET | ✅ UDF | — | ✅ UDF | — |
| GET_LOCK / RELEASE_LOCK | ✅ UDF | — | ✅ UDF | — |

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
| FOR UPDATE | — | — | ✅ 除去 | ✅ ネイティブ |
| SQL_CALC_FOUND_ROWS | ✅ | ✅ | ✅ | ✅ |
| FROM DUAL | ✅ | — | ✅ | ✅ |
| INDEX HINTS | ✅ | — | ✅ | ✅ |
| LIKE → ILIKE (PgSQL) | — | ✅ | — | ✅ |
| LIKE BINARY → GLOB | ✅ | — | ✅ | ✅ LIKE |
| LIKE ESCAPE | ✅ | — | ✅ | ✅ |
| HAVING without GROUP BY | ✅ | ✅ | ✅ | ✅ |
| CONVERT → CAST | — | ✅ | ✅ | ✅ |
| COLLATE 除去 | — | — | ✅ | ✅ |
| @@変数 → ダミー | — | — | ✅ | ✅ |
| 空 IN () → IN (NULL) | — | — | ✅ | ✅ |
| DISTINCT + ORDER BY 列注入 | — | ✅ | — | ✅ |
| meta_value + 0 → CAST | — | ✅ | — | ✅ |
| ゼロ日付処理 | ✅ | — | ✅ text | ✅ '-infinity' |
| LOW_PRIORITY / DELAYED | ✅ | — | ✅ | ✅ |
| START TRANSACTION | ✅ | — | ✅ | ✅ |
| SAVEPOINT | — | — | ✅ | ✅ |

### DDL

| 機能 | SQLite プラグイン | PG4WP | WpPack SQLite | WpPack PgSQL |
|------|:---:|:---:|:---:|:---:|
| CREATE TABLE 型変換 | ✅ | ✅ | ✅ AST | ✅ AST |
| PRIMARY KEY マージ | ✅ | — | ✅ AST | — |
| AUTO_INCREMENT → SERIAL | — | ✅ | ✅ AUTOINCREMENT | ✅ SERIAL/BIGSERIAL |
| ON UPDATE CURRENT_TIMESTAMP | ✅ トリガー | — | ✅ トリガー | — |
| ALTER ADD/DROP/CHANGE | ✅ | ✅ | ✅ | ✅ |
| ENGINE/CHARSET/COLLATE 除去 | ✅ | ✅ | ✅ AST | ✅ AST |
| IF NOT EXISTS | ✅ | ✅ | ✅ | ✅ |
| データ型キャッシュ | ✅ | — | ✅ | — |
| information_schema 対応 | ✅ | — | ✅ → sqlite_master | ✅ ネイティブ |
| ISO 8601 日付正規化 | ✅ | — | ✅ | — |

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
| トランスレーター | 4,543行 | 1,500行 | 1,200行 |
| QueryRewriter | 343行 | 279行 | 279行 |
| UDF | 899行（46関数） | 200行（15関数） | — |
| **合計** | **5,785行** | **~1,980行** | **~1,480行** |

WpPack は phpmyadmin/sql-parser の AST を活用することで、プラグインの約1/3のコード量で同等の機能をカバーする。プラグインは独自 Lexer（2,997行）を含むため、パーサー込みの総コスト差はさらに大きい。

### アーキテクチャ

| | SQLite プラグイン | WpPack |
|---|---|---|
| パーサー | 独自 WP_MySQL_Lexer（2,997行） | phpmyadmin/sql-parser v6.0（標準ライブラリ） |
| 変換方式 | トークンストリーム書き換え + UDF 46個 | AST ルーティング + QueryRewriter + UDF 15個 |
| DDL 処理 | 独自パーサーで AST → SQL | phpmyadmin AST `CreateDefinition[]` から直接構築 |
| 関数変換 | 大半を UDF で実行（行単位で PHP 呼び出し） | AST レベルでネイティブ関数に変換（UDF 最小限） |
| 文字列リテラル保護 | トークン型による判定 | `TokenType::String` で構造的に保証 |
| Prepared Statement | 文字列結合（vsprintf ベース） | ネイティブ `?` パラメータ（Driver 分離） |
| エンジン対応 | SQLite のみ | SQLite + PostgreSQL + Aurora DSQL |

### 関数変換: UDF vs AST

プラグインは46個の UDF を SQLite に登録する方式。WpPack は AST レベルで SQLite ネイティブ関数に変換し、UDF は15個に抑える。

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
| DATE_FORMAT | トークン | **AST** | プラグイン: 37仕様 / WpPack: 21仕様 |
| MONTH / YEAR / DAY | UDF | **AST** | WpPack: `strftime()` → `CAST AS INTEGER` |
| HOUR / MINUTE / SECOND | UDF | **AST** | 同上 |
| DAYOFWEEK / WEEKDAY / WEEK | UDF | **AST** | WpPack: `strftime('%w')` 演算 |
| DATEDIFF | UDF | **AST** | WpPack: `julianday()` 差分 |
| CONCAT | トークン→\|\| | **AST**→\|\| | 同じ出力 |
| LEFT / RIGHT | トークン | **AST** | WpPack: RIGHT も対応 |
| SUBSTRING / CHAR_LENGTH | トークン | **AST** | 同等 |
| MID / LCASE / UCASE | — | **AST** | WpPack のみ対応 |
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
| GROUP_CONCAT | — | **AST** (PgSQL) | WpPack PgSQL: → `STRING_AGG` |

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
| @@変数 → ダミー | — | ✅ |
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
| **ネイティブ関数優先** | UDF 15個 vs プラグイン46個。パフォーマンスに直結 |
| **真の Prepared Statement** | `?` パラメータを Driver に分離。SQL インジェクション構造的防止 |
| **Reader/Writer Split** | `DATABASE_READER_DSN` で読み書き分離 |
| **560 ユニットテスト** | プラグインは WordPress e2e テスト依存 |
| **文字列リテラル安全性** | `TokenType::String` による構造的保証 |

### プラグインの優位点

| 機能 | 説明 |
|------|------|
| **DATE_FORMAT 37仕様** | WpPack は30仕様（残り7仕様は WordPress で未使用） |
| **WordPress フック統合** | `pre_query_sqlite_db` 等のフック |

## PG4WP との比較

### コード規模

| | PG4WP | WpPack PgSQL |
|---|---:|---:|
| トランスレーター | ~2,500行 | 1,200行 |
| ドライバ/DB層 | ~1,000行 | 279行（QueryRewriter） |
| **合計** | **~3,500行** | **~1,480行** |

### アーキテクチャ

| | PG4WP | WpPack |
|---|---|---|
| パース方式 | 正規表現ベース文字列置換 | phpmyadmin/sql-parser AST |
| 関数変換 | ~20関数 | 50+関数 |
| 型マッピング | 基本的（~15型） | 包括的（25+型、JSONB 含む） |
| Prepared Statement | なし | ネイティブ `?` パラメータ |
| Reader/Writer Split | なし | DATABASE_READER_DSN 対応 |

### WpPack のみの機能（PG4WP にない）

30+関数と機能が WpPack のみ対応。主要なもの:

- **日時関数:** DATE_FORMAT→TO_CHAR, FROM_UNIXTIME→TO_TIMESTAMP, DATEDIFF, HOUR/MINUTE/SECOND, DAYOFWEEK/DAYOFYEAR/WEEKDAY/WEEK, CURDATE/CURTIME, UTC_TIMESTAMP/DATE/TIME, LOCALTIME/LOCALTIMESTAMP
- **文字列関数:** CONCAT/CONCAT_WS, LEFT, LOCATE→POSITION, ISNULL→IS NULL, CHAR_LENGTH→LENGTH, LCASE/UCASE
- **型:** JSON→JSONB, BLOB→BYTEA, BINARY→BYTEA, CAST AS CHAR→TEXT
- **文:** TRUNCATE TABLE, START TRANSACTION→BEGIN, LIKE→ILIKE, LIKE ESCAPE, UPDATE/DELETE LIMIT（ctid サブクエリ）, DELETE JOIN（USING 構文）, INSERT ... SET, CONVERT→CAST, COLLATE 除去, @@変数→ダミー, 空 IN→IN(NULL)
- **SHOW:** CREATE TABLE, DATABASES, COLLATION, GRANTS, CREATE PROCEDURE, CHECK/ANALYZE/REPAIR TABLE
- **インフラ:** ネイティブ Prepared Statement, Reader/Writer Split, LOW_PRIORITY/DELAYED スキップ

### PG4WP のみの機能（WpPack にない）

| 機能 | 説明 | WordPress 影響 |
|------|------|---------------|
| WordPress プラグイン互換 | Akismet comment_ID 正規化、NextGen Gallery AS 処理 | 中 |
| wp_options マルチ行 INSERT 分割 | 複数行 INSERT を個別実行 | 低 |
| シーケンス setval() 管理 | WPMU インストール時 | 低 |

## 総合評価

| 評価軸 | SQLite プラグイン | PG4WP | WpPack |
|--------|:---:|:---:|:---:|
| 関数カバレッジ | ★★★ (46 UDF) | ★★ (~20) | ★★★★ (50+ AST変換 + 15 UDF) |
| パフォーマンス | ★★ (UDF オーバーヘッド) | ★★★ | ★★★★ (ネイティブ関数優先) |
| DDL 対応 | ★★★★ | ★★★ | ★★★★ |
| SHOW 対応 | ★★★ | ★★★ | ★★★★ |
| 型安全性 | ★★ (文字列結合) | ★★ | ★★★★ (Prepared Statement) |
| マルチエンジン | ★ (SQLite のみ) | ★ (PgSQL のみ) | ★★★★★ (SQLite + PgSQL + DSQL) |
| テスト | ★★ (e2e 依存) | ★★★ (504 スタブ) | ★★★★★ (573 ユニットテスト) |
| WP 固有対応 | ★★★★★ | ★★★★ | ★★★★★ |
