# Storage

Object storage abstraction for WordPress.

## Installation

```bash
composer require wppack/storage
```

## Usage

### Using DSN

```php
use WpPack\Component\Storage\Adapter\Storage;

// S3 (requires wppack/s3-storage)
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

$adapter->write('path/to/file.txt', 'Hello, World!');
$contents = $adapter->read('path/to/file.txt');
```

### StorageAdapterInterface

```php
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

function upload(StorageAdapterInterface $adapter): void
{
    // Write contents
    $adapter->write('path/to/file.txt', 'contents', ['Content-Type' => 'text/plain']);

    // Write stream (for large files)
    $stream = fopen('/path/to/large-file.zip', 'r');
    $adapter->writeStream('path/to/large-file.zip', $stream);

    // Read contents
    $contents = $adapter->read('path/to/file.txt');

    // Read stream
    $stream = $adapter->readStream('path/to/large-file.zip');

    // Check existence
    $exists = $adapter->exists('path/to/file.txt');

    // Get metadata
    $metadata = $adapter->metadata('path/to/file.txt');
    // $metadata->size, $metadata->lastModified, $metadata->mimeType

    // Get public URL
    $url = $adapter->publicUrl('path/to/file.txt');

    // Get temporary (pre-signed) URL
    $url = $adapter->temporaryUrl('path/to/file.txt', new \DateTimeImmutable('+1 hour'));

    // Copy / Move
    $adapter->copy('source.txt', 'destination.txt');
    $adapter->move('old-path.txt', 'new-path.txt');

    // Delete
    $adapter->delete('path/to/file.txt');
    $adapter->deleteMultiple(['file1.txt', 'file2.txt']);

    // List contents
    foreach ($adapter->listContents('uploads/2024/') as $object) {
        echo $object->key;
    }
}
```

### Testing

Use `InMemoryStorageAdapter` for unit tests:

```php
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

$adapter = new InMemoryStorageAdapter();
$adapter->write('test.txt', 'hello');
assert($adapter->read('test.txt') === 'hello');
```

## Available Adapters

| Adapter | Package | DSN Scheme |
|---------|---------|------------|
| Amazon S3 | `wppack/s3-storage` | `s3://` |

## Documentation

- [Storage documentation](../../docs/components/storage/)
