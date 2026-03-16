# WpPack Wpress

Handles `.wpress` archive files used by All-in-One WP Migration. Provides a `ZipArchive`-like API for reading, writing, extracting, and modifying `.wpress` archives.

## Installation

```bash
composer require wppack/wpress
```

## Usage

```php
use WpPack\Component\Wpress\WpressArchive;

// Open an existing archive
$archive = new WpressArchive('/path/to/backup.wpress');

// List entries
foreach ($archive->getEntries() as $entry) {
    echo $entry->getPath() . ' (' . $entry->getSize() . " bytes)\n";
}

// Read a specific entry
$entry = $archive->getEntry('package.json');
$meta = json_decode($entry->getContents(), true);

// Extract all files
$archive->extractTo('/path/to/destination');

// Create a new archive
$archive = new WpressArchive('/path/to/new.wpress', WpressArchive::CREATE);
$archive->addFromString('package.json', json_encode(['SiteURL' => 'https://example.com']));
$archive->addFile('/path/to/dump.sql', 'database.sql');
$archive->addDirectory('/path/to/uploads/', 'wp-content/uploads/');
$archive->close();
```

## Documentation

See [docs/components/wpress/](../../../docs/components/wpress/) for full documentation.

## License

MIT
