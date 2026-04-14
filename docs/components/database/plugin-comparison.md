# プラグイン比較: SQLite Database Integration / PG4WP

WpPack Database コンポーネントの MySQL→SQLite / MySQL→PostgreSQL クエリ変換機能を、WordPress エコシステムの既存プラグインと比較する。

## 比較対象

| | SQLite Database Integration | PG4WP (PostgreSQL for WordPress) | WpPack |
|---|---|---|---|
| リポジトリ | wordpress/sqlite-database-integration | PostgreSQL-For-Wordpress/postgresql-for-wordpress | wppack-io/wppack |
| エンジン | SQLite | PostgreSQL | SQLite + PostgreSQL + Aurora DSQL |
| アーキテクチャ | 独自 Lexer + トークン書き換え + UDF 46個 | 正規表現ベース文字列置換 | AST (phpmyadmin/sql-parser) + QueryRewriter + UDF 15個 |
| コード量 | ~5,800行 | ~3,500行 | ~3,200行（両エンジン合計） |
| テスト | WordPress e2e 依存 | なし | 554 ユニットテスト / 940 アサーション |

## SQLite Database Integration との比較

### コード規模

| | SQLite プラグイン | WpPack SQLite |
|---|---:|---:|
| トランスレーター | 4,543行 | 1,500行 |
| QueryRewriter | 343行 | 279行 |
| UDF | 899行（46関数） | 200行（15関数） |
| **合計** | **5,785行** | **~1,980行** |

WpPack は phpmyadmin/sql-parser の AST を活用することで約1/3のコード量で同等のカバレッジを実現。

### 関数変換: UDF vs AST

プラグインは46個の UDF（PHP ユーザー定義関数）を SQLite に登録する方式。WpPack は AST レベルで SQLite ネイティブ関数に変換し、UDF は最小限（15個）に抑える。

| 方式 | 利点 | 欠点 |
|------|------|------|
| UDF（プラグイン） | MySQL 動作の正確な再現 | 行ごとに PHP 呼び出し → パフォーマンス劣化。インデックス無効化 |
| AST 変換（WpPack） | SQLite ネイティブ実行 → 高速。インデックス有効 | 100%の MySQL 互換性は保証できない |

例: 10,000行の SELECT で NOW() を使用する場合
- UDF: NOW() が10,000回 PHP に呼び出される
- AST: `datetime('now')` に1回変換されて SQLite がネイティブ実行

### 機能カバレッジ

#### WpPack が対応し、プラグインも対応している機能

- 日時関数: NOW, CURDATE, CURTIME, UNIX_TIMESTAMP, UTC_TIMESTAMP/DATE/TIME, FROM_UNIXTIME, DATE_ADD/SUB, DATE_FORMAT, MONTH/YEAR/DAY/HOUR/MINUTE/SECOND, DAYOFWEEK/WEEKDAY/WEEK, DATEDIFF
- 文字列関数: CONCAT, CONCAT_WS, LEFT, RIGHT, SUBSTRING, CHAR_LENGTH, LOCATE, LCASE/UCASE, MID
- 制御関数: IF, IFNULL, ISNULL, GREATEST, LEAST, FIELD
- その他: RAND, LAST_INSERT_ID, VERSION, DATABASE, FOUND_ROWS, MD5, LOG, REGEXP
- 暗号/変換: UNHEX, FROM_BASE64, TO_BASE64, INET_ATON/NTOA, GET_LOCK/RELEASE_LOCK
- DML: INSERT IGNORE, REPLACE INTO, ON DUPLICATE KEY UPDATE, LIMIT 書き換え, UPDATE/DELETE LIMIT, FOR UPDATE 除去, SQL_CALC_FOUND_ROWS, FROM DUAL, INSERT ... SET
- DDL: CREATE TABLE 型変換, PRIMARY KEY マージ, ON UPDATE CURRENT_TIMESTAMP トリガー, ALTER TABLE ADD/DROP/CHANGE COLUMN
- SHOW: TABLES, FULL TABLES, COLUMNS, CREATE TABLE, INDEX, VARIABLES, COLLATION, DATABASES, TABLE STATUS（全て LIKE パターン付き）
- その他: LIKE ESCAPE, LIKE BINARY→GLOB, HAVING without GROUP BY, CAST AS SIGNED/CHAR/BINARY, CONVERT→CAST, COLLATE 除去, LOW_PRIORITY/DELAYED スキップ, CHECK/ANALYZE/REPAIR TABLE ダミー, SHOW GRANTS/CREATE PROCEDURE ダミー, 空 IN 句, データ型キャッシュ

#### プラグインのみの機能

| 機能 | 説明 | WordPress 影響 |
|------|------|---------------|
| DATE_FORMAT 37仕様 | WpPack は21仕様 | 低（WordPress コアは基本的な仕様のみ使用） |
| ゼロ日付処理 | '0000-00-00' の特殊ハンドリング | 低 |
| WEEK(d, mode) mode | mode パラメータ対応 | 低 |
| Information Schema Builder | MySQL 互換スキーマ再構築 | 低（WordPress Site Health 用） |

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

### 機能カバレッジ

#### WpPack のみの機能（PG4WP にない）

| 機能 | 説明 |
|------|------|
| DATE_FORMAT → TO_CHAR | PG4WP は DATE_FORMAT 未対応 |
| FROM_UNIXTIME → TO_TIMESTAMP | PG4WP は未対応 |
| DATEDIFF | PG4WP は未対応 |
| HOUR/MINUTE/SECOND 抽出 | PG4WP は未対応 |
| DAYOFWEEK/DAYOFYEAR/WEEKDAY/WEEK | PG4WP は未対応 |
| LEFT/RIGHT 関数 | PG4WP は未対応 |
| CONCAT/CONCAT_WS | PG4WP は未対応 |
| LOCATE → POSITION | PG4WP は未対応 |
| ISNULL → IS NULL | PG4WP は未対応 |
| CURDATE/CURTIME | PG4WP は未対応 |
| UTC_TIMESTAMP/DATE/TIME | PG4WP は未対応 |
| LOCALTIME/LOCALTIMESTAMP | PG4WP は未対応 |
| VERSION/DATABASE 関数 | PG4WP は未対応 |
| TRUNCATE TABLE | PG4WP は未対応 |
| START TRANSACTION → BEGIN | PG4WP は未対応 |
| BINARY 型キャスト → BYTEA | PG4WP は未対応 |
| JSON → JSONB 型マッピング | PG4WP は未対応 |
| BLOB → BYTEA 型マッピング | PG4WP は未対応 |
| SHOW CREATE TABLE | PG4WP は未対応 |
| SHOW DATABASES/COLLATION | PG4WP は未対応 |
| UPDATE/DELETE LIMIT (ctid サブクエリ) | PG4WP は LIMIT を単純除去 |
| LIKE ESCAPE 句 | PG4WP は基本的なハンドリングのみ |
| LOW_PRIORITY/DELAYED/HIGH_PRIORITY | PG4WP は未対応 |
| CHECK/ANALYZE/REPAIR TABLE ダミー | PG4WP は未対応 |
| SHOW GRANTS/CREATE PROCEDURE ダミー | PG4WP は未対応 |
| ネイティブ Prepared Statement | PG4WP は文字列結合 |
| Reader/Writer Split | PG4WP は非対応 |

#### PG4WP のみの機能（WpPack にない）

| 機能 | 説明 | WordPress 影響 |
|------|------|---------------|
| meta_value 型キャスト | `meta_value+0` → `CAST(meta_value AS BIGINT)` | 高（WP_Meta_Query 数値比較） |
| WordPress プラグイン互換 | Akismet comment_ID 正規化、NextGen Gallery AS 処理 | 中（特定プラグイン使用時） |
| wp_options マルチ行 INSERT 分割 | 複数行 INSERT を個別実行 | 低（インストール時のみ） |
| シーケンス setval() 管理 | WPMU インストール時のシーケンス更新 | 低（マルチサイトインストール時のみ） |
| DELETE self-join 書き換え | トランジェントクリーンアップの自己結合 DELETE | 中 |

## まとめ

| 評価軸 | SQLite プラグイン | PG4WP | WpPack |
|--------|:---:|:---:|:---:|
| 関数カバレッジ | ★★★ (46 UDF) | ★★ (~20) | ★★★★ (50+ AST変換 + 15 UDF) |
| パフォーマンス | ★★ (UDF オーバーヘッド) | ★★★ | ★★★★ (ネイティブ関数優先) |
| DDL 対応 | ★★★★ | ★★★ | ★★★★ |
| SHOW 対応 | ★★★ | ★★★ | ★★★★ |
| 型安全性 | ★★ (文字列結合) | ★★ | ★★★★ (Prepared Statement) |
| マルチエンジン | ★ (SQLite のみ) | ★ (PgSQL のみ) | ★★★★★ (SQLite + PgSQL + DSQL) |
| テスト | ★★ (e2e 依存) | ★ (なし) | ★★★★★ (554 ユニットテスト) |
| WP 固有対応 | ★★★★★ | ★★★★ | ★★★ |
