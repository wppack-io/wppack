# WPPack PostgreSQL Database

PostgreSQL database driver for WPPack Database component.

## Installation

```bash
composer require wppack/pgsql-database
```

## Usage

```php
use WPPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WPPack\Component\Database\Connection;

$driver = new PgsqlDriver(
    host: 'localhost',
    username: 'user',
    password: 'pass',
    database: 'mydb',
);
$connection = new Connection($driver);

$rows = $connection->fetchAllAssociative('SELECT * FROM posts');
```

### Via DSN

```php
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Connection;

$driver = Driver::fromDsn('pgsql://user:pass@localhost:5432/mydb');
$connection = new Connection($driver);
```

### Schema / search_path

Configure the server-side `search_path` to isolate schemas per tenant or per blog. Accepts an ordered list (or a single-schema alias).

```php
// Ordered list
$driver = Driver::fromDsn('pgsql://user:pass@host:5432/mydb?search_path=tenant_42,public');

// Single-schema alias
$driver = Driver::fromDsn('pgsql://user:pass@host:5432/mydb?schema=tenant_42');

// Constructor argument
$driver = new PgsqlDriver(
    host: 'localhost',
    username: 'user',
    password: 'pass',
    database: 'mydb',
    searchPath: ['tenant_42', 'public'],
);
```

- Each entry is quoted as an identifier, so names with uppercase letters, reserved words, or non-ASCII characters work.
- `null` (the default) uses the server default — usually `"$user", public`.
- Applied again on transparent reconnect after a gone-away, so `wait_timeout` doesn't silently flip the schema.
- NUL / newline / CR in a schema name raises `ConnectionException` (libpq truncates C-strings silently — we refuse early).
- `PostgresqlQueryTranslator` rewrites `SHOW TABLES` / `SHOW COLUMNS` / `DESCRIBE` / `SHOW CREATE TABLE` / `SHOW INDEX` / `SHOW TABLE STATUS` / introspection to filter on `current_schema()` instead of a hardcoded `'public'`, so the effective search_path drives visibility.

## Query Translation

When used with the `db.php` drop-in, WordPress MySQL queries are automatically translated to PostgreSQL via `PostgresqlQueryTranslator`. The translator uses phpmyadmin/sql-parser for AST parsing and a stateful `QueryRewriter` for token-level transformations.

Key translations: `IFNULL()` → `COALESCE()`, `REGEXP` → `~*`, `AUTO_INCREMENT` → `SERIAL`/`BIGSERIAL`, `DATETIME` → `TIMESTAMP`, `BLOB` → `BYTEA`, `JSON` → `JSONB`, `DATE_FORMAT()` → `TO_CHAR()`, `MONTH()`/`YEAR()` → `EXTRACT()`.

String literals are never transformed. See the [Database component documentation](../../../docs/components/database/README.md) for the full conversion reference.

## License

MIT
