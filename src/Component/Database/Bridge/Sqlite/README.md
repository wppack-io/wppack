# WpPack SQLite Database

SQLite database driver for WpPack Database component.

## Installation

```bash
composer require wppack/sqlite-database
```

## Usage

```php
use WpPack\Component\Database\Bridge\Sqlite\SqliteDriver;
use WpPack\Component\Database\Connection;

$driver = new SqliteDriver('/path/to/database.db');
$connection = new Connection($driver);

$rows = $connection->fetchAllAssociative('SELECT * FROM posts');
```

### Via DSN

```php
use WpPack\Component\Database\Driver\Driver;
use WpPack\Component\Database\Connection;

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

The following MySQL-compatible functions are registered as SQLite UDFs via `SqliteDriver::registerFunctions()`:

- `REGEXP(pattern, value)` — PHP `preg_match` based
- `CONCAT(a, b, ...)` — string concatenation
- `CONCAT_WS(separator, a, b, ...)` — concatenation with separator
- `CHAR_LENGTH(value)` — multibyte string length
- `FIELD(search, val1, val2, ...)` — position in value list

## License

MIT
