# WPPack Filesystem

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=filesystem)](https://codecov.io/github/wppack-io/wppack)

DI-injectable wrapper around `WP_Filesystem_Base`.

## Installation

```bash
composer require wppack/filesystem
```

## Usage

### Filesystem

```php
use WPPack\Component\Filesystem\Filesystem;

// Inject WP_Filesystem_Base (required)
$filesystem = new Filesystem($wp_filesystem);

// Read / write
$content = $filesystem->read('/path/to/file.txt');
$filesystem->write('/path/to/file.txt', 'content');
$filesystem->append('/path/to/file.txt', 'more content');

// Check existence
$filesystem->exists('/path/to/file.txt');
$filesystem->isFile('/path/to/file.txt');
$filesystem->isDirectory('/path/to/dir');

// Delete
$filesystem->delete('/path/to/file.txt');
$filesystem->deleteDirectory('/path/to/dir');

// Copy / move
$filesystem->copy('/path/from.txt', '/path/to.txt');
$filesystem->move('/path/from.txt', '/path/to.txt');

// Directory creation
$filesystem->mkdir('/path/to/new-dir', recursive: true);

// File info
$size = $filesystem->size('/path/to/file.txt');
$mtime = $filesystem->lastModified('/path/to/file.txt');
$mime = $filesystem->mimeType('/path/to/file.txt');

// Directory listing
$files = $filesystem->files('/path/to/directory');
$dirs = $filesystem->directories('/path/to/directory');
$all = $filesystem->listContents('/path/to/directory', recursive: true);
```

### UploadPath

```php
use WPPack\Component\Filesystem\WordPress\UploadPath;

$uploadPath = new UploadPath();

$basePath = $uploadPath->getBasePath();       // /var/www/html/wp-content/uploads
$baseUrl = $uploadPath->getBaseUrl();         // https://example.com/wp-content/uploads
$currentPath = $uploadPath->getCurrentPath(); // .../uploads/2024/01
$currentUrl = $uploadPath->getCurrentUrl();   // .../uploads/2024/01
$customPath = $uploadPath->subdir('exports'); // .../uploads/exports (auto-created)
```

### Named Hook Attributes

```php
use WPPack\Component\Hook\Attribute\Filesystem\Filter\UploadDirFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\FilesystemMethodFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Action\WpFilesystemInitAction;

final class FilesystemHooks
{
    #[UploadDirFilter(priority: 10)]
    public function customizeUploadDirectory(array $uploads): array
    {
        $uploads['baseurl'] = 'https://cdn.example.com/uploads';

        return $uploads;
    }

    #[FilesystemMethodFilter(priority: 10)]
    public function forceDirectMethod(string $method): string
    {
        return 'direct';
    }
}
```

## Documentation

See [docs/components/filesystem/](../../../docs/components/filesystem/) for full documentation.

## License

MIT
