# Database Component

A component that provides a type-safe wrapper around WordPress `$wpdb`, with exception-based error handling and custom table schema management via `dbDelta()`.

## Installation

```bash
composer require wppack/database
```

## Basic Usage

```php
use WpPack\Component\Database\DatabaseManager;

$db = new DatabaseManager();

// Doctrine DBAL-style fetch API (array parameters)
$rows = $db->fetchAllAssociative(
    "SELECT * FROM {$db->prefix()}analytics WHERE status = %s",
    ['active'],
);

$row = $db->fetchAssociative(
    "SELECT * FROM {$db->prefix()}analytics WHERE id = %d",
    [$id],
);

$count = $db->fetchOne(
    "SELECT COUNT(*) FROM {$db->prefix()}analytics WHERE status = %s",
    ['active'],
);

// Table operations (automatic prefix applied)
$db->insert('analytics', ['name' => 'test', 'status' => 'active']);
$db->update('analytics', ['status' => 'inactive'], ['id' => 1]);
$db->delete('analytics', ['id' => 1]);

// Transactions
$db->beginTransaction();
try {
    $db->insert('analytics', ['name' => 'tx_test']);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

## Documentation

For details, see [docs/components/database/](../../docs/components/database/).
