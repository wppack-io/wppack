# WpPack Wpress

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=wpress)](https://codecov.io/github/wppack-io/wppack)

Handles `.wpress` archive files used by All-in-One WP Migration. Provides a `ZipArchive`-like API for reading, writing, extracting, and modifying `.wpress` archives.

> [!NOTE]
> **Copyright & Attribution** — The `.wpress` format and All-in-One WP Migration are developed by and copyright of ServMask Inc. (Copyright (C) 2014-2025 ServMask Inc.). The original plugin is distributed under the GPLv3 license. This component is an independent implementation of the `.wpress` format and is not official software of or affiliated with ServMask Inc.

## Why .wpress?

`.wpress` is an open source archive format created by ServMask Inc. for All-in-One WP Migration — the most widely used WordPress migration plugin with 5M+ active installs. ServMask describes it as "our open source archive format" on [servmask.com](https://www.servmask.com/), and the plugin is distributed under GPLv3.

By implementing `.wpress` natively, WpPack enables reading, creating, and modifying WordPress backups without depending on the AI1WM plugin itself — making it possible to integrate `.wpress` operations into custom toolchains, CI/CD pipelines, and migration workflows.

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
