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

## License

MIT
