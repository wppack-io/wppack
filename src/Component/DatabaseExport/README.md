# WpPack Database Export

Database export component for WordPress. Supports multiple output formats and source database engines.

## Features

- Export to wpress-compatible SQL (All-in-One WP Migration import compatible)
- Export to JSON
- Export to CSV
- MySQL, MariaDB, SQLite, PostgreSQL source support
- Multisite-aware table filtering
- Configurable table prefix placeholder
- Stream-based writing for memory efficiency
- Pluggable row transformers (wp_options reset, etc.)

## Installation

```bash
composer require wppack/database-export
```

## Usage

```php
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\SchemaReader\MysqlSchemaReader;
use WpPack\Component\DatabaseExport\DatabaseExporter;
use WpPack\Component\DatabaseExport\ExportConfiguration;
use WpPack\Component\DatabaseExport\TableFilter\PrefixTableFilter;
use WpPack\Component\DatabaseExport\Writer\WpressSqlWriter;

$db = new DatabaseManager();
$config = new ExportConfiguration(tablePrefix: 'WPPACK_PREFIX_');

$writer = new WpressSqlWriter();
$writer->setDatabasePrefix($db->prefix());

$exporter = new DatabaseExporter(
    db: $db,
    schemaReader: new MysqlSchemaReader(),
    writer: $writer,
    tableFilter: new PrefixTableFilter($db->prefix(), $config),
);

$sql = $exporter->exportToString($config);
```

## License

MIT
