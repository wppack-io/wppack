# WpPack PostgreSQL Database

PostgreSQL database driver for WpPack Database component.

## Installation

```bash
composer require wppack/pgsql-database
```

## Usage

```php
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\Connection;

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
use WpPack\Component\Database\Driver\Driver;
use WpPack\Component\Database\Connection;

$driver = Driver::fromDsn('pgsql://user:pass@localhost:5432/mydb');
$connection = new Connection($driver);
```

## License

MIT
