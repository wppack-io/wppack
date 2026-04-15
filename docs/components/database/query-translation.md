# Query Translation Architecture

WordPress は MySQL SQL を生成するため、非 MySQL エンジン（SQLite、PostgreSQL）ではクエリ変換が必要になる。本ドキュメントでは、変換手法の比較と WpPack の設計判断を記録する。

## 変換手法の比較

MySQL SQL を別エンジンの SQL に変換するアプローチは主に3つある。

### 1. UDF（ユーザー定義関数）方式

**概要:** MySQL の関数名をそのまま SQL に残し、ターゲットエンジン側に同名の関数を PHP で登録する。

```
MySQL:   SELECT NOW(), MONTH(created) FROM posts
SQLite:  SELECT NOW(), MONTH(created) FROM posts  ← SQL は変更なし
         ↑ NOW() と MONTH() は PHP 実装の UDF として登録済み
```

```php
// SQLite に NOW() を登録
$pdo->sqliteCreateFunction('NOW', fn() => gmdate('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('MONTH', fn($d) => (int) gmdate('n', strtotime($d)));
```

**利点:**
- SQL の書き換え不要。実装がシンプル
- MySQL の動作を PHP で正確にエミュレーション可能（ゼロ日付処理等）
- 変換ミスが起きない（SQL が変わらないため）

**欠点:**
- パフォーマンス: 行ごとに PHP ↔ SQLite ブリッジが発生。大量行で顕著に遅い
- SQLite 限定: PostgreSQL は `CREATE FUNCTION` に PL/pgSQL が必要で、PHP UDF は使えない
- インデックス無効: UDF は SQLite のクエリオプティマイザに認識されず、インデックスが効かない
- 構造変換不可: `DATE_ADD(d, INTERVAL 1 DAY)` のような構文変換は UDF では対応できない

**採用例:** WordPress SQLite Database Integration プラグイン（46 UDF 登録）

### 2. トークンストリーム書き換え方式

**概要:** SQL をトークンに分解し、トークン列を線形に走査しながら書き換える。構文の構造解析は行わない。

```
MySQL:   SELECT NOW(), DATE_ADD(d, INTERVAL 1 DAY) FROM posts
         ↓ Lexer → [SELECT] [NOW] [(] [)] [,] [DATE_ADD] [(] [d] [,] [INTERVAL] [1] [DAY] [)] ...
         ↓ トークン走査: NOW → datetime('now'), DATE_ADD(...) → datetime(d, '+1 day')
SQLite:  SELECT datetime('now'), datetime(d, '+1 day') FROM posts
```

```php
// QueryRewriter パターン（consume/skip/add）
while ($rw->hasMore()) {
    $token = $rw->peek();
    if ($token->keyword === 'NOW' && nextIsEmptyParens()) {
        $rw->skip(); $rw->skip(); $rw->skip(); // NOW ( )
        $rw->add("datetime('now')");
    } else {
        $rw->consume();
    }
}
```

**利点:**
- 高速: シングルパスでの変換。AST 構築オーバーヘッドなし
- 柔軟: 任意のトークンパターンに対応可能
- 元のフォーマット保持: 空白やコメントがそのまま出力に残る

**欠点:**
- 構造理解なし: 「今 WHERE 句にいるのか SELECT 句にいるのか」を自前で追跡する必要がある
- コンテキスト判定が困難: `LEFT JOIN` の `LEFT` と `LEFT(s, n)` 関数の区別にはルックアヘッドが必要
- DDL 処理に弱い: CREATE TABLE の型変換は構造的理解がないと正確にできない
- 文字列リテラル保護にトークン型チェックが必須（なければ `'NOW()'` が変換される）

**採用例:** WordPress SQLite Database Integration プラグイン（DML 変換の主方式）

### 3. AST（抽象構文木）方式

**概要:** SQL をパーサーで構文解析し、AST（抽象構文木）を構築。AST のノードを走査して変換を適用する。

```
MySQL:   SELECT NOW(), DATE_ADD(d, INTERVAL 1 DAY) FROM posts WHERE id > 5 LIMIT 10, 20

         ↓ Parser → AST
         SelectStatement:
           expr: [Expression(function="NOW"), Expression(function="DATE_ADD")]
           from: [Expression(table="posts")]
           where: [Condition(expr="id > 5")]
           limit: Limit(offset=10, rowCount=20)

         ↓ AST ルーティング: SelectStatement → translateSelect()
         ↓ 各コンポーネントの変換:
            expr[0]: NOW() → datetime('now')          ← QueryRewriter で変換
            expr[1]: DATE_ADD → datetime(d, '+1 day') ← 構造変換
            limit: offset=10, rowCount=20 → LIMIT 20 OFFSET 10  ← AST から直接

SQLite:  SELECT datetime('now'), datetime(d, '+1 day') FROM posts WHERE id > 5 LIMIT 20 OFFSET 10
```

**利点:**
- 構造理解: 文タイプ（SELECT/INSERT/CREATE）、LIMIT 構造、ON DUPLICATE KEY 等を正確に把握
- DDL に強い: `CreateDefinition[]` から型名・オプション・制約を個別に操作可能
- マルチエンジン対応: 同じ AST を異なるターゲット SQL に変換するパターンが明確
- 安全性: 文字列リテラルは `TokenType::String` で構造的に区別される

**欠点:**
- パーサーオーバーヘッド: 全クエリで AST 構築が発生
- 式レベルの限界: phpmyadmin/sql-parser の `Expression.expr` は文字列のまま。関数引数は AST に分解されない
- 複雑性: AST + トークン操作のハイブリッドが必要

**採用例:** WpPack Database コンポーネント

## WpPack の設計判断

### ハイブリッドアプローチ: AST + QueryRewriter

WpPack は AST とトークン操作を組み合わせたハイブリッドアプローチを採用している。

```
SQL → Parser → AST（構造理解）+ TokensList（トークン列）
                ↓                        ↓
      文タイプルーティング        QueryRewriter（consume/skip/add）
                ↓                        ↓
      文タイプ別ハンドラ ──────── AST コンテキスト参照
                ↓
         ターゲット SQL
```

**レイヤー分担:**

| レイヤー | 役割 | 使用技術 |
|---------|------|---------|
| 文ルーティング | SELECT/INSERT/CREATE 等の判定 | AST (`$stmt instanceof SelectStatement`) |
| 構造変換 | LIMIT, INSERT IGNORE, ON DUPLICATE KEY | AST 情報（`$stmt->limit`, `$stmt->options`） |
| DDL 構築 | CREATE TABLE の型変換・制約処理 | AST `CreateDefinition[]` 直接走査 |
| 式変換 | 関数リネーム、構造変換（DATE_ADD 等） | QueryRewriter トークン操作 |
| リテラル保護 | 文字列内の関数名を変換しない | `TokenType::String` 自動スキップ |
| 識別子変換 | バッククォート → ダブルクォート | QueryRewriter 自動変換（consume 時） |

### UDF を最小限に留める理由

WpPack は UDF を14個に限定し、大半の関数は AST 変換でネイティブ関数に変換している。

| 判断基準 | UDF で実装 | AST で変換 |
|---------|-----------|-----------|
| SQLite に同等機能がない | REGEXP (`preg_match`) | — |
| 可変引数で変換が複雑 | CONCAT (任意個数) | — |
| SQLite ネイティブ関数に1:1マップ可能 | — | NOW → `datetime('now')` |
| 構文構造の変換が必要 | — | DATE_ADD → `datetime(d, '+n unit')` |
| PostgreSQL でも使いたい | — | 全関数（UDF は SQLite 限定） |

**パフォーマンス比較（概算）:**

```
UDF 方式:    SQL 解析(0) + UDF 呼び出し(行数 × 関数数 × PHP↔C ブリッジ)
AST 方式:    SQL 解析(1回) + トークン走査(1回) + 変換済み SQL 実行(ネイティブ速度)
```

SELECT で 10,000 行を返すクエリに `NOW()` がある場合:
- UDF: NOW() が 10,000 回 PHP に呼び出される
- AST: `datetime('now')` に1回変換されて SQLite がネイティブ実行

### 既存プラグインとの比較

SQLite Database Integration プラグインおよび PG4WP (PostgreSQL for WordPress) との詳細な機能比較は [plugin-comparison.md](./plugin-comparison.md) を参照。

## 変換リファレンス

### 関数変換

| MySQL | カテゴリ | SQLite | PostgreSQL |
|-------|---------|--------|------------|
| `NOW()` | 日時 | `datetime('now')` | そのまま |
| `CURDATE()` | 日時 | `date('now')` | `CURRENT_DATE` |
| `CURTIME()` | 日時 | `time('now')` | `CURRENT_TIME` |
| `UNIX_TIMESTAMP()` | 日時 | `strftime('%s','now')` | `EXTRACT(EPOCH FROM NOW())::INTEGER` |
| `UTC_TIMESTAMP()` | 日時 | `datetime('now')` | `NOW() AT TIME ZONE 'UTC'` |
| `UTC_DATE()` | 日時 | `date('now')` | `(NOW() AT TIME ZONE 'UTC')::date` |
| `UTC_TIME()` | 日時 | `time('now')` | `(NOW() AT TIME ZONE 'UTC')::time` |
| `FROM_UNIXTIME(t)` | 日時 | `datetime(t, 'unixepoch')` | `TO_TIMESTAMP(t)` |
| `DATE_ADD(d, INTERVAL n unit)` | 日時 | `datetime(d, '+n unit')` | `d + INTERVAL 'n unit'` |
| `DATE_SUB(d, INTERVAL n unit)` | 日時 | `datetime(d, '-n unit')` | `d - INTERVAL 'n unit'` |
| `DATE_FORMAT(d, fmt)` | 日時 | `strftime(fmt, d)` | `TO_CHAR(d, fmt)` |
| `DATEDIFF(d1, d2)` | 日時 | `julianday(d1) - julianday(d2)` | `DATE_PART('day', d1 - d2)` |
| `MONTH(d)` | 抽出 | `strftime('%m', d)` | `EXTRACT(MONTH FROM d)` |
| `YEAR(d)` | 抽出 | `strftime('%Y', d)` | `EXTRACT(YEAR FROM d)` |
| `DAY(d)` | 抽出 | `strftime('%d', d)` | `EXTRACT(DAY FROM d)` |
| `HOUR(d)` | 抽出 | `strftime('%H', d)` | `EXTRACT(HOUR FROM d)` |
| `MINUTE(d)` | 抽出 | `strftime('%M', d)` | `EXTRACT(MINUTE FROM d)` |
| `SECOND(d)` | 抽出 | `strftime('%S', d)` | `EXTRACT(SECOND FROM d)` |
| `DAYOFWEEK(d)` | 抽出 | `strftime('%w', d) + 1` | `EXTRACT(DOW FROM d) + 1` |
| `DAYOFYEAR(d)` | 抽出 | `strftime('%j', d)` | `EXTRACT(DOY FROM d)` |
| `WEEKDAY(d)` | 抽出 | `(strftime('%w', d) + 6) % 7` | `EXTRACT(ISODOW FROM d) - 1` |
| `RAND()` | 数学 | `random()` | `random()` |
| `IFNULL(a, b)` | 比較 | そのまま | `COALESCE(a, b)` |
| `IF(cond, t, f)` | 制御 | `CASE WHEN cond THEN t ELSE f END` | 同左 |
| `CAST(x AS SIGNED)` | 型 | `CAST(x AS INTEGER)` | 同左 |
| `CONCAT(a, b, ...)` | 文字列 | `a \|\| b \|\| ...` | そのまま |
| `CONCAT_WS(sep, a, b)` | 文字列 | `a \|\| sep \|\| b` | そのまま |
| `LEFT(s, n)` | 文字列 | `SUBSTR(s, 1, n)` | `SUBSTRING(s FROM 1 FOR n)` |
| `RIGHT(s, n)` | 文字列 | `SUBSTR(s, -n)` | そのまま |
| `SUBSTRING(s, p, n)` | 文字列 | `SUBSTR(s, p, n)` | そのまま |
| `CHAR_LENGTH(s)` | 文字列 | `LENGTH(s)` | `LENGTH(s)` |
| `MID(s, p, n)` | 文字列 | `SUBSTR(s, p, n)` | `SUBSTRING(s, p, n)` |
| `LOCATE(sub, str)` | 文字列 | `INSTR(str, sub)` | `POSITION(sub IN str)` |
| `LCASE(s)` / `UCASE(s)` | 文字列 | `lower(s)` / `upper(s)` | 同左 |
| `GREATEST(a, b)` | 比較 | `MAX(a, b)` | そのまま |
| `LEAST(a, b)` | 比較 | `MIN(a, b)` | そのまま |
| `GROUP_CONCAT(col SEP s)` | 集約 | `group_concat(col, s)` | `STRING_AGG(col, s)` |
| `LAST_INSERT_ID()` | システム | `last_insert_rowid()` | `lastval()` |
| `VERSION()` | システム | `'10.0.0-wppack'` | `version()` |
| `DATABASE()` | システム | `'main'` | `CURRENT_DATABASE()` |
| `FOUND_ROWS()` | システム | WpPackWpdb でインターセプト | 同左 |
| `FIELD(val, 'a', 'b')` | 制御 | `CASE WHEN val='a' THEN 1 ... END` | 同左 |
| `CONVERT(val, type)` | 型 | `CAST(val AS type)` | 同左 |
| `CAST(x AS CHAR)` | 型 | `CAST(x AS TEXT)` | 同左 |
| `CAST(x AS BINARY)` | 型 | `CAST(x AS BLOB)` | `CAST(x AS BYTEA)` |
| `ISNULL(x)` | 比較 | `(x IS NULL)` | 同左 |
| `WEEK(d [, mode])` | 抽出 | `strftime('%W', d)` | `EXTRACT(WEEK FROM d)` |
| `LOG(x)` / `LOG(b, x)` | 数学 | UDF | ネイティブ |
| `MD5(s)` | 暗号 | UDF | ネイティブ |
| `UNHEX(hex)` | 変換 | UDF (`hex2bin`) | `decode(hex, 'hex')` |
| `TO_BASE64(s)` / `FROM_BASE64(s)` | 変換 | UDF | `encode/decode(s, 'base64')` |
| `INET_ATON(ip)` / `INET_NTOA(n)` | ネットワーク | UDF | `inet` 型演算 |
| `GET_LOCK` / `RELEASE_LOCK` | ロック | UDF（`_wppack_locks` テーブル） | `pg_try_advisory_lock(hashtext(name)::bigint)` |
| `IS_FREE_LOCK` | ロック | UDF（`_wppack_locks` テーブル） | advisory lock 取得試行+即解放 |
| `LOCALTIME` / `LOCALTIMESTAMP` | 日時 | `datetime('now')` | `NOW()` |
| `REGEXP` | 演算子 | そのまま（UDF） | `~*`（REGEXP BINARY → `~`） |

### 文レベル変換

| MySQL | SQLite | PostgreSQL |
|-------|--------|------------|
| `INSERT IGNORE INTO` | `INSERT OR IGNORE INTO` | `INSERT ... ON CONFLICT DO NOTHING` |
| `REPLACE INTO` | `INSERT OR REPLACE INTO` | `INSERT INTO`（トークン書き換え） |
| `ON DUPLICATE KEY UPDATE` | `ON CONFLICT DO UPDATE SET` | 同左 |
| `VALUES(col)` (ODKU 内) | `excluded.col` | 同左 |
| `LIMIT offset, count` | `LIMIT count OFFSET offset` | 同左 |
| `FOR UPDATE` | 除去 | そのまま |
| `START TRANSACTION` | `BEGIN` | `BEGIN` |
| `TRUNCATE TABLE t` | `DELETE FROM t` + sqlite_sequence リセット | `TRUNCATE ... RESTART IDENTITY` |
| `SQL_CALC_FOUND_ROWS` | 除去（WpPackWpdb でカウント保存） | 同左 |
| `UPDATE/DELETE ... LIMIT N` | `rowid IN (SELECT rowid ... LIMIT N)` | `ctid IN (SELECT ctid ... LIMIT N)` |
| `LOW_PRIORITY` / `DELAYED` | 除去 | 除去 |
| `ALTER TABLE ADD INDEX` | `CREATE INDEX` | `CREATE INDEX` |
| `ALTER TABLE DROP INDEX` | `DROP INDEX IF EXISTS` | `DROP INDEX IF EXISTS` |
| `AS 'alias'` (PgSQL) | そのまま | `AS "alias"` |
| `COUNT(*) ... ORDER BY` (PgSQL) | そのまま | ORDER BY 除去（パフォーマンス） |
| `INSERT ... SET col=val` | `INSERT ... VALUES(...)` | 同左 |
| `DELETE JOIN` | rowid サブクエリ | `USING` 構文 |
| `CONVERT(val, type)` | `CAST(val AS type)` | 同左 |
| `COLLATE clause` | 除去 | 除去 |
| `@@SESSION.sql_mode` 等 | 変数名に応じたデフォルト値 | 同左 |
| `IN ()` (空) | `IN (NULL)` | 同左 |
| `LIKE` | `LIKE ... ESCAPE '\'` | `ILIKE ... ESCAPE '\'` |
| `LOG(x)` | UDF（`log()`） | `LN(x)`（MySQL LOG = 自然対数） |
| `LOG(b, x)` | UDF（`log(b, x)`） | `LOG(b, x)` そのまま |
| `ON DUPLICATE KEY UPDATE` | `ON CONFLICT DO UPDATE SET` | `ON CONFLICT (推定カラム) DO UPDATE SET` |
| `DISTINCT + ORDER BY` | — | ORDER BY 列を SELECT に自動注入 |
| `meta_value + 0` | そのまま | `CAST(meta_value AS BIGINT)` |
| `'0000-00-00 00:00:00'` | そのまま（TEXT） | `'0001-01-01 00:00:00'` |
| `'datetime' ISO 8601` | `'datetime' 正規化` | そのまま |

### DDL 変換

| MySQL | SQLite | PostgreSQL |
|-------|--------|------------|
| `BIGINT(N) UNSIGNED` | `INTEGER` | `BIGINT` |
| `INT(N)` | `INTEGER` | `INTEGER` |
| `TINYINT(N)` | `INTEGER` | `SMALLINT` |
| `VARCHAR(N)` | `TEXT` | `VARCHAR(N)` |
| `DATETIME` | `TEXT` | `TIMESTAMP` |
| `LONGBLOB` | `BLOB` | `BYTEA` |
| `JSON` | `TEXT` | `JSONB` |
| `ENUM(...)` | `TEXT` | `TEXT` |
| `FLOAT` / `DOUBLE` | `REAL` | `REAL` / `DOUBLE PRECISION` |
| `DECIMAL(N,M)` | `REAL` | `DECIMAL(N,M)` |
| `AUTO_INCREMENT` | `AUTOINCREMENT` | `SERIAL` / `BIGSERIAL` / `SMALLSERIAL` |
| `ON UPDATE CURRENT_TIMESTAMP` | SQLite トリガー生成 | PgSQL トリガー関数 + トリガー生成 |
| `ENGINE=...` / `CHARSET=...` / `COLLATE=...` | 除去 | 除去 |
| `PRIMARY KEY (col)` + `AUTOINCREMENT` | 同一行にマージ | N/A |
| `ALTER TABLE ADD COLUMN` | パススルー | パススルー |
| `ALTER TABLE DROP COLUMN` | パススルー（SQLite 3.35.0+） | パススルー |
| `ALTER TABLE MODIFY COLUMN` | no-op（動的型付け） | `ALTER COLUMN TYPE` |
| `ALTER TABLE CHANGE COLUMN`（リネーム）| `RENAME COLUMN`（3.25.0+）| `ALTER COLUMN TYPE` + `RENAME COLUMN` |
| `ALTER TABLE CHANGE COLUMN`（同名）| no-op（動的型付け） | `ALTER COLUMN TYPE` |
| `KEY name (col)` (インライン) | `CREATE INDEX IF NOT EXISTS` に分離 | `CREATE INDEX IF NOT EXISTS` に分離 |
| `ALTER TABLE ADD [UNIQUE] INDEX` | `CREATE [UNIQUE] INDEX` | `CREATE [UNIQUE] INDEX` |
| `IF NOT EXISTS` | 保持 | 保持 |
| `DEFAULT '0000-00-00 ...'` (DDL) | そのまま（TEXT） | `'0001-01-01 00:00:00'` に変換 |
| 識別子ケース | そのまま | 小文字に正規化 |
| MySQL データ型キャッシュ | `_mysql_data_types_cache` テーブル | N/A |

### エンジン固有の重要な変換仕様

#### KEY/INDEX の CREATE INDEX 分離

MySQL の `CREATE TABLE` ではインライン `KEY` を使ってインデックスを定義できるが、SQLite と PostgreSQL はこの構文をサポートしない。

```sql
-- MySQL（元の WordPress DDL）
CREATE TABLE wp_posts (
  ID bigint(20) unsigned NOT NULL auto_increment,
  post_title text NOT NULL,
  post_status varchar(20) NOT NULL,
  PRIMARY KEY (ID),
  KEY post_status (post_status),          -- ← MySQL 固有構文
  KEY type_status (post_type, post_status)
);

-- SQLite / PostgreSQL（変換後）
CREATE TABLE "wp_posts" (..., PRIMARY KEY ("ID"));
CREATE INDEX IF NOT EXISTS "post_status" ON "wp_posts" ("post_status");
CREATE INDEX IF NOT EXISTS "type_status" ON "wp_posts" ("post_type", "post_status");
```

`PRIMARY KEY` と `UNIQUE KEY` はインラインに残し、通常の `KEY`/`INDEX` のみ `CREATE INDEX` 文に分離する。MySQL のプレフィックス長 `(col(191))` は両エンジンで無視される（SQLite と PostgreSQL は部分インデックスの構文が異なるため）。

#### PostgreSQL の識別子ケース正規化

PostgreSQL はクォートなし識別子を**小文字に正規化**する。一方、ダブルクォート付き識別子は大文字小文字を**そのまま保持**する。

```sql
-- クォートなし: PostgreSQL は id に正規化
SELECT * FROM wp_posts WHERE ID = 1;  → 内部で id として扱う

-- ダブルクォート: そのまま保持
SELECT * FROM wp_posts WHERE "ID" = 1;  → 大文字の ID を検索
```

WordPress は `ID`（大文字）をクォートなしで使用する。DDL で `"ID"` として作成すると、クエリの `ID`（→ `id`）と一致しない。

**解決策:** PostgreSQL の DDL では全識別子を小文字化する。

```sql
-- ✗ 問題あり
CREATE TABLE "wp_posts" ("ID" BIGSERIAL NOT NULL, ...);
-- WordPress: SELECT * FROM wp_posts WHERE ID = 1  → id ≠ "ID"

-- ✓ 正しい変換
CREATE TABLE "wp_posts" ("id" BIGSERIAL NOT NULL, ...);
-- WordPress: SELECT * FROM wp_posts WHERE ID = 1  → id = "id" ✓
```

`PostgresqlPlatform::quoteIdentifier()` は自動的に `strtolower()` を適用し、DDL・DML の両方で一貫したケースを保証する。

#### LIKE ESCAPE の自動付与

MySQL はデフォルトで `\` を LIKE のエスケープ文字として扱うが、SQLite と PostgreSQL はデフォルトではエスケープ文字がない。WordPress の `$wpdb->esc_like()` は `\` でワイルドカードをエスケープするため、変換後の LIKE に `ESCAPE '\'` を自動付与する。

```sql
-- MySQL（元のクエリ）
SELECT * FROM wp_posts WHERE post_title LIKE '%100\%%'
-- \% = リテラル %, MySQL はデフォルトで \ をエスケープと認識

-- SQLite（変換後）
SELECT * FROM "wp_posts" WHERE "post_title" LIKE '%100\%%' ESCAPE '\'
-- ESCAPE '\' がないと \ はただの文字として扱われ、意図しない結果になる

-- PostgreSQL（変換後）
SELECT * FROM wp_posts WHERE post_title ILIKE '%100\%%' ESCAPE '\'
```

パラメータプレースホルダ `?` を使う場合も同様に `ESCAPE '\'` が付与される。既存の `ESCAPE` 句がある場合は二重付与しない。

#### PostgreSQL DDL のゼロ日付変換

MySQL のゼロ日付 `'0000-00-00 00:00:00'` は PostgreSQL の `TIMESTAMP` 型では無効。DDL の `DEFAULT` 句でゼロ日付が使用されている場合、`'0001-01-01 00:00:00'` に変換する。

```sql
-- MySQL DDL
post_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00'

-- PostgreSQL DDL（変換後）
"post_date" TIMESTAMP NOT NULL DEFAULT '0001-01-01 00:00:00'
```

DML のゼロ日付変換（文レベル変換の `'0000-00-00 00:00:00'` → `'0001-01-01 00:00:00'`）と一貫性を保つ。

#### PostgreSQL LOG() の意味差異

MySQL と PostgreSQL で `LOG()` の意味が異なる:

| 関数 | MySQL | PostgreSQL |
|------|-------|-----------|
| `LOG(x)` | 自然対数 (ln) | 常用対数 (log₁₀) |
| `LOG(b, x)` | 底 b の対数 | 底 b の対数 |

1引数の `LOG(x)` は `LN(x)` に変換し、2引数の `LOG(b, x)` はそのまま（同じ意味）。SQLite は UDF で MySQL 互換の LOG を提供。

#### ON DUPLICATE KEY UPDATE の conflict target 推定

PostgreSQL の `ON CONFLICT DO UPDATE SET` には明示的な conflict target が必要（MySQL の `ON DUPLICATE KEY UPDATE` は不要）。トランスレータは INSERT カラムから conflict target を推定する:

```
INSERT カラム − UPDATE カラム = 推定 conflict target（ユニークキーカラム）
```

```sql
-- MySQL
INSERT INTO wp_options (option_name, option_value, autoload) VALUES (...)
  ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)

-- PostgreSQL（変換後）
-- INSERT: option_name, option_value, autoload
-- UPDATE: option_value, autoload
-- → conflict = option_name
INSERT INTO wp_options (option_name, option_value, autoload) VALUES (...)
  ON CONFLICT ("option_name") DO UPDATE SET option_value = excluded.option_value, autoload = excluded.autoload
```

WordPress コアの `ON DUPLICATE KEY UPDATE` パターン（options テーブルの upsert、term_relationships の更新等）はこのヒューリスティックで正しく動作する。

#### GET_LOCK / RELEASE_LOCK の実装

MySQL の名前付きロック関数を各エンジンで実装:

| | SQLite | PostgreSQL |
|---|--------|-----------|
| GET_LOCK | `_wppack_locks` テーブルに INSERT（UDF 内で PDO 直接操作） | `pg_try_advisory_lock(hashtext(name)::bigint)` |
| RELEASE_LOCK | `_wppack_locks` テーブルから DELETE | `pg_advisory_unlock(hashtext(name)::bigint)` |
| IS_FREE_LOCK | `_wppack_locks` テーブルで COUNT チェック | advisory lock 取得試行 + 即解放 |
| 再入可能 | ✅（既存ロック検出で 1 を返す） | ✅（PostgreSQL ネイティブ動作） |

**注:** WordPress 公式 SQLite Database Integration プラグインと PG4WP はいずれも GET_LOCK/RELEASE_LOCK をダミー（常に 1）で実装している。WpPack は実際のロック機構を提供する。

- **SQLite:** UDF 内から `_wppack_locks` テーブルを PDO で直接操作。`$wpdb` を経由しないため循環依存なし
- **PostgreSQL:** `hashtext()` で文字列名を bigint キーに変換し、PostgreSQL ネイティブの advisory lock を使用。セッションスコープ

### SHOW 文変換

| MySQL | SQLite | PostgreSQL |
|-------|--------|------------|
| `SHOW TABLES` | `sqlite_master` | `information_schema.tables` |
| `SHOW COLUMNS FROM t` | `PRAGMA table_info(t)` | `information_schema.columns` |
| `SHOW CREATE TABLE t` | `pragma_table_info` + キャッシュ | `information_schema.columns` |
| `SHOW INDEX FROM t` | `PRAGMA index_list(t)` | `pg_indexes` |
| `SHOW TABLE STATUS [LIKE]` | `sqlite_master`（MySQL 互換カラム） | `pg_class`（行数推定付き） |
| `SHOW VARIABLES` | 変数名別デフォルト値 | `pg_settings` |
| `SHOW DATABASES` | `'main'` | `pg_database` |
| `DESCRIBE t` | `PRAGMA table_info(t)` | `information_schema.columns` |

### 無視・ダミー返却する文

| MySQL | 動作 |
|-------|------|
| `SET NAMES` / `SET SESSION` / `SET GLOBAL` | 無視（文字コードはドライバ接続 API で設定済み） |
| `LOCK TABLES` / `UNLOCK TABLES` | 無視（空配列返却） |
| `OPTIMIZE TABLE` | 無視（空配列返却） |
| `CREATE DATABASE` / `DROP DATABASE` | 無視（空配列返却） |
| `CHECK TABLE t` | ダミー成功返却（`OK` ステータス行） |
| `ANALYZE TABLE t` | ダミー成功返却（`OK` ステータス行） |
| `REPAIR TABLE t` | ダミー成功返却（`OK` ステータス行） |
| `SHOW GRANTS` | ダミー GRANT 文返却 |
| `SHOW CREATE PROCEDURE` | 空結果返却 |
| `SELECT @@SESSION.sql_mode` 等 | 変数名に応じた MySQL デフォルト値返却 |
| `SELECT GET_LOCK(...)` | SQLite: `_wppack_locks` テーブル UDF / PgSQL: `pg_try_advisory_lock()` |
| `SELECT RELEASE_LOCK(...)` | SQLite: `_wppack_locks` テーブル UDF / PgSQL: `pg_advisory_unlock()` |

### DATE_FORMAT 変換マップ（MySQL 全31仕様対応）

| MySQL | 説明 | SQLite (strftime) | PostgreSQL (TO_CHAR) |
|-------|------|-------------------|---------------------|
| `%Y` | 4桁年 | `%Y` | `YYYY` |
| `%y` | 2桁年 | `%y` | `YY` |
| `%m` | 月 01-12 | `%m` | `MM` |
| `%c` | 月 1-12 | `%n` | `FMMM` |
| `%d` | 日 01-31 | `%d` | `DD` |
| `%e` | 日 1-31 | `%j` | `FMDD` |
| `%H` | 時 00-23 | `%H` | `HH24` |
| `%h` / `%I` | 時 01-12 | `%h` | `HH12` |
| `%k` | 時 0-23 (パディングなし) | `%G` | `FMHH24` |
| `%l` | 時 1-12 (パディングなし) | `%g` | `FMHH12` |
| `%i` | 分 | `%M` | `MI` |
| `%s` / `%S` | 秒 | `%S` | `SS` |
| `%j` | 年内日数 | `%z` | `DDD` |
| `%W` | 曜日名 | `%l` | `Day` |
| `%w` | 曜日番号 0-6 | `%w` | `D` |
| `%p` | AM/PM | `%A` | `AM` |
| `%T` | HH:MM:SS | `%H:%M:%S` | `HH24:MI:SS` |
| `%r` | 12h HH:MM:SS AM | `%h:%M:%S %A` | `HH12:MI:SS AM` |
| `%a` | 曜日略称 | `%D` | `Dy` |
| `%b` | 月略称 | `%M` | `Mon` |
| `%M` | 月名 | `%F` | `FMMonth` |
| `%D` | 日+序数 | `%jS` | `FMDDth` |
| `%U` / `%u` | 週番号 | `%W` | `WW` / `IW` |
| `%V` / `%v` | ISO 週番号 | `%W` | `IW` |
| `%X` | ISO 年 | `%Y` | `YYYY` |
| `%x` | ISO 年 (2桁) | `%o` | `YY` |
| `%f` | マイクロ秒 | `000000`（固定値） | `US` |

### SQLite UDF 一覧

`SqliteDriver::registerFunctions()` で登録される PHP 実装の関数:

| UDF | 引数 | 用途 |
|-----|------|------|
| `REGEXP(pattern, value)` | 2 | `preg_match` による正規表現マッチ |
| `CONCAT(a, b, ...)` | 可変 | 文字列連結（NULL → 空文字） |
| `CONCAT_WS(sep, a, b, ...)` | 可変 | セパレータ付き連結（NULL フィルタ） |
| `CHAR_LENGTH(value)` | 1 | `mb_strlen` によるマルチバイト文字数 |
| `FIELD(search, val1, val2, ...)` | 可変 | 値リスト内の位置（1始まり、0=不一致） |
| `MD5(value)` | 1 | `md5()` ハッシュ |
| `LOG(x)` / `LOG(b, x)` | 可変 | 自然対数 / 底指定対数 |
| `UNHEX(hex)` | 1 | 16進文字列 → バイナリ（`hex2bin`） |
| `FROM_BASE64(str)` | 1 | Base64 デコード |
| `TO_BASE64(str)` | 1 | Base64 エンコード |
| `INET_ATON(ip)` | 1 | IP アドレス → 整数 |
| `INET_NTOA(num)` | 1 | 整数 → IP アドレス |
| `GET_LOCK(name, timeout)` | 2 | `_wppack_locks` テーブルで名前付きロック（再入可能） |
| `RELEASE_LOCK(name)` | 1 | `_wppack_locks` テーブルからロック解放 |
| `IS_FREE_LOCK(name)` | 1 | `_wppack_locks` テーブルでロック空きチェック |

## 未対応機能

全ての主要機能を実装済み。未対応項目なし。

## 関連ドキュメント

- [プラグイン比較: SQLite Database Integration / PG4WP](./plugin-comparison.md) — 既存プラグインとの機能比較・アーキテクチャ比較
