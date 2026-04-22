# PostgreSQL Database Bridge

**パッケージ:** `wppack/postgresql-database`
**名前空間:** `WPPack\Component\Database\Bridge\PostgreSQL\`
**Category:** Data

WPPack Database コンポーネントの PostgreSQL ドライバ実装。`db.php` drop-in と組み合わせると、WordPress MySQL クエリを `PostgreSQLQueryTranslator` 経由で PostgreSQL 方言に翻訳して実行します。

## インストール

```bash
composer require wppack/postgresql-database
```

PHP の `ext-pgsql` 拡張が必要です。

## DSN 設定

```php
// wp-config.php

// 基本形
define('DATABASE_DSN', 'pgsql://user:pass@host:5432/mydb');

// 別名スキーム
define('DATABASE_DSN', 'postgres://user:pass@host:5432/mydb');
define('DATABASE_DSN', 'postgresql://user:pass@host:5432/mydb');

// search_path 指定（multi-tenant / blog-scoped schema 分離）
define('DATABASE_DSN', 'pgsql://user:pass@host/mydb?search_path=tenant_42,public');

// 単一 schema のエイリアス
define('DATABASE_DSN', 'pgsql://user:pass@host/mydb?schema=tenant_42');
```

### DSN 書式

```
pgsql|postgres|postgresql://[user[:password]@]host[:port]/database[?search_path=...|schema=...]
```

### 対応オプション

| オプション | 型 | デフォルト | 説明 |
|-----------|-----|-----------|------|
| `search_path` | string | server default (`"$user", public`) | カンマ区切りの schema リスト |
| `schema` | string | — | 単一 schema の短縮形 |

schema 名は SQL identifier として quote されるので、大文字・予約語・非 ASCII も安全に扱えます。gone-away 自動再接続後も search_path は再適用されます。NUL / 改行を含む schema 名は `ConnectionException` で拒否 (libpq が C-string を silently truncate する問題を避ける)。

## 接続挙動

- **Persistent connection**: `persistent: true` で `pg_pconnect` 使用 (php-fpm / WP-CLI worker 向け)。デフォルトは wpdb 互換で per-request 接続
- **TLS**: libpq 経由で `sslmode=require` などを指定可能
- **Gone-away 検出**: `PGSQL_CONNECTION_OK` ステータス監視で透過再接続

## クエリ翻訳

MySQL → PostgreSQL の主な変換:

| MySQL | PostgreSQL |
|-------|------------|
| `IFNULL()` | `COALESCE()` |
| `REGEXP` / `RLIKE` | `~*` |
| `AUTO_INCREMENT` | `SERIAL` / `BIGSERIAL` |
| `DATETIME` | `TIMESTAMP` |
| `BLOB` | `BYTEA` |
| `JSON` | `JSONB` |
| `DATE_FORMAT(d, fmt)` | `TO_CHAR(d, fmt)` |
| `MONTH(d)` / `YEAR(d)` | `EXTRACT(MONTH/YEAR FROM d)` |
| `SHOW TABLES` / `DESCRIBE` | `information_schema` フィルタ (`current_schema()` 対応) |

翻訳は AST レベルで実行され、文字列リテラル内は変換対象外。詳細は [query-translation.md](./query-translation.md)。

## 関連ドキュメント

- [Database component](./README.md)
- [Query translation deep-dive](./query-translation.md)
- [src/Component/Database/Bridge/PostgreSQL/README.md](../../../src/Component/Database/Bridge/PostgreSQL/README.md) — 実装詳細

## License

MIT
