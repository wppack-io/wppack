# WPPack Database Export

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
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\SchemaReader\MySQLSchemaReader;
use WPPack\Component\DatabaseExport\DatabaseExporter;
use WPPack\Component\DatabaseExport\ExportConfiguration;
use WPPack\Component\DatabaseExport\RowTransformer\WpOptionsTransformer;
use WPPack\Component\DatabaseExport\RowTransformer\WpUserMetaTransformer;
use WPPack\Component\DatabaseExport\TableFilter\PrefixTableFilter;
use WPPack\Component\DatabaseExport\Writer\WpressSqlWriter;

$db = new DatabaseManager();
$config = new ExportConfiguration(
    dbPrefix: $db->prefix(),
    tablePrefix: 'WPPACK_PREFIX_',
);

$exporter = new DatabaseExporter(
    db: $db,
    schemaReader: new MySQLSchemaReader(),
    writer: new WpressSqlWriter(),
    tableFilter: new PrefixTableFilter($config),
    rowTransformers: [
        new WpOptionsTransformer($config),
        new WpUserMetaTransformer($config),
    ],
);

$sql = $exporter->exportToString($config);
```

### AI1WM Compatible Export

```php
$config = new ExportConfiguration(
    dbPrefix: $db->prefix(),
    tablePrefix: 'SERVMASK_PREFIX_',
);
```

## License

MIT
