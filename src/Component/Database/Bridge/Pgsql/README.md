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

## Query Translation

When used with the `db.php` drop-in, WordPress MySQL queries are automatically translated to PostgreSQL via `PostgresqlQueryTranslator`. The translator uses phpmyadmin/sql-parser for AST parsing and a stateful `QueryRewriter` for token-level transformations.

Key translations: `IFNULL()` → `COALESCE()`, `REGEXP` → `~*`, `AUTO_INCREMENT` → `SERIAL`/`BIGSERIAL`, `DATETIME` → `TIMESTAMP`, `BLOB` → `BYTEA`, `JSON` → `JSONB`, `DATE_FORMAT()` → `TO_CHAR()`, `MONTH()`/`YEAR()` → `EXTRACT()`.

String literals are never transformed. See the [Database component documentation](../../../docs/components/database/README.md) for the full conversion reference.

## License

MIT
