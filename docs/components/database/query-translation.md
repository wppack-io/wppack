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

WpPack は UDF を5個（REGEXP, CONCAT, CONCAT_WS, CHAR_LENGTH, FIELD）に限定している。

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
| `GROUP_CONCAT(col SEP s)` | 集約 | — | `STRING_AGG(col, s)` |
| `LAST_INSERT_ID()` | システム | `last_insert_rowid()` | `lastval()` |
| `VERSION()` | システム | `'10.0.0-wppack'` | `version()` |
| `DATABASE()` | システム | `'main'` | `CURRENT_DATABASE()` |
| `FOUND_ROWS()` | システム | `-1` | `-1` |
| `REGEXP` | 演算子 | そのまま（UDF） | `~*` |

### 文レベル変換

| MySQL | SQLite | PostgreSQL |
|-------|--------|------------|
| `INSERT IGNORE INTO` | `INSERT OR IGNORE INTO` | `INSERT ... ON CONFLICT DO NOTHING` |
| `REPLACE INTO` | `INSERT OR REPLACE INTO` | — |
| `ON DUPLICATE KEY UPDATE` | `ON CONFLICT DO UPDATE SET` | 同左 |
| `VALUES(col)` (ODKU 内) | `excluded.col` | 同左 |
| `LIMIT offset, count` | `LIMIT count OFFSET offset` | 同左 |
| `FOR UPDATE` | 除去 | そのまま |
| `START TRANSACTION` | `BEGIN` | `BEGIN` |
| `TRUNCATE TABLE t` | `DELETE FROM t` | そのまま |
| `SQL_CALC_FOUND_ROWS` | 除去 | 除去 |

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
| `AUTO_INCREMENT` | `AUTOINCREMENT` | `SERIAL` / `BIGSERIAL` |
| `ENGINE=...` / `CHARSET=...` | 除去 | 除去 |

### SHOW 文変換

| MySQL | SQLite | PostgreSQL |
|-------|--------|------------|
| `SHOW TABLES` | `sqlite_master` | `information_schema.tables` |
| `SHOW COLUMNS FROM t` | `PRAGMA table_info(t)` | `information_schema.columns` |
| `SHOW CREATE TABLE t` | `pragma_table_info` + キャッシュ | — |
| `SHOW INDEX FROM t` | `PRAGMA index_list(t)` | — |
| `SHOW VARIABLES` | 空結果 | `pg_settings` |
| `SHOW DATABASES` | `'main'` | `pg_database` |
| `DESCRIBE t` | `PRAGMA table_info(t)` | `information_schema.columns` |

### 無視する文

`SET NAMES`, `SET SESSION/GLOBAL`, `LOCK/UNLOCK TABLES`, `OPTIMIZE/ANALYZE/CHECK/REPAIR TABLE`, `CREATE/DROP DATABASE`

### DATE_FORMAT 変換マップ

| MySQL | SQLite (strftime) | PostgreSQL (TO_CHAR) |
|-------|-------------------|---------------------|
| `%Y` | `%Y` | `YYYY` |
| `%m` | `%m` | `MM` |
| `%d` | `%d` | `DD` |
| `%H` | `%H` | `HH24` |
| `%i` | `%M` | `MI` |
| `%s` | `%S` | `SS` |
| `%j` | `%j` | `DDD` |
| `%W` | `%w` | `Day` |

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
| `GET_LOCK(name, timeout)` | 2 | ダミー（常に 1 を返却） |
| `RELEASE_LOCK(name)` | 1 | ダミー（常に 1 を返却） |

## 未対応機能

全ての主要機能を実装済み。未対応項目なし。

## 関連ドキュメント

- [プラグイン比較: SQLite Database Integration / PG4WP](./plugin-comparison.md) — 既存プラグインとの機能比較・アーキテクチャ比較
