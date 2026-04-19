# WPPack SQLite Database

SQLite database driver for WPPack Database component.

## Installation

```bash
composer require wppack/sqlite-database
```

## Usage

```php
use WPPack\Component\Database\Bridge\Sqlite\SqliteDriver;
use WPPack\Component\Database\Connection;

$driver = new SqliteDriver('/path/to/database.db');
$connection = new Connection($driver);

$rows = $connection->fetchAllAssociative('SELECT * FROM posts');
```

### Via DSN

```php
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Connection;

$driver = Driver::fromDsn('sqlite:///path/to/database.db');
$connection = new Connection($driver);
```

### In-Memory Database

```php
$driver = Driver::fromDsn('sqlite:///:memory:');
```

## Query Translation

When used with the `db.php` drop-in, WordPress MySQL queries are automatically translated to SQLite via `SqliteQueryTranslator`. The translator uses phpmyadmin/sql-parser for AST parsing and a stateful `QueryRewriter` for token-level transformations.

Key translations: `NOW()` → `datetime('now')`, `AUTO_INCREMENT` → `AUTOINCREMENT`, `BIGINT` → `INTEGER`, `VARCHAR` → `TEXT`, `DATETIME` → `TEXT`, `LIMIT offset,count` → `LIMIT count OFFSET offset`, `INSERT IGNORE` → `INSERT OR IGNORE`, `ON DUPLICATE KEY UPDATE` → `ON CONFLICT DO UPDATE SET`.

String literals are never transformed (guaranteed by `TokenType::String` detection). See the [Database component documentation](../../../docs/components/database/README.md) for the full conversion reference.

### User-Defined Functions

15 MySQL-compatible functions are registered as SQLite UDFs via `SqliteDriver::registerFunctions()` — the translator prefers native SQLite functions first and only falls back to UDFs when no structural rewrite exists (keeping the query optimizer aware of indexes where possible):

- `REGEXP(pattern, value)` — PHP `preg_match` based
- `CONCAT(a, b, ...)` — string concatenation (NULL → empty)
- `CONCAT_WS(separator, a, b, ...)` — concatenation with separator, NULL-filtered
- `CHAR_LENGTH(value)` — `mb_strlen`-based multibyte length
- `FIELD(search, val1, val2, ...)` — 1-based position in value list (0 on miss)
- `MD5(value)` — PHP `md5()`
- `LOG(x)` / `LOG(base, x)` — natural / base logarithm, MySQL-compatible semantics
- `UNHEX(hex)` — `hex2bin` hex string decoder
- `FROM_BASE64(str)` / `TO_BASE64(str)` — Base64 codec
- `INET_ATON(ip)` / `INET_NTOA(num)` — IPv4 ↔ integer
- `GET_LOCK(name, timeout)` / `RELEASE_LOCK(name)` / `IS_FREE_LOCK(name)` — reentrant named locks backed by a `_wppack_locks` table, not a dummy `return 1`

## License

MIT
