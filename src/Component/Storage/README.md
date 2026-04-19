# Storage

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=storage)](https://codecov.io/github/wppack-io/wppack)

Storage abstraction for WordPress.

## Installation

```bash
composer require wppack/storage
```

## Usage

### Using DSN

```php
use WPPack\Component\Storage\Adapter\Storage;

// S3 (requires wppack/s3-storage)
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

$adapter->write('path/to/file.txt', 'Hello, World!');
$contents = $adapter->read('path/to/file.txt');
```

### StorageAdapterInterface

```php
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;

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

    // Check file existence
    $exists = $adapter->fileExists('path/to/file.txt');

    // Directory operations
    $adapter->createDirectory('path/to/directory');
    $adapter->directoryExists('path/to/directory');
    $adapter->deleteDirectory('path/to/directory');

    // Get metadata
    $metadata = $adapter->metadata('path/to/file.txt');
    // $metadata->size, $metadata->lastModified, $metadata->mimeType

    // Get public URL
    $url = $adapter->publicUrl('path/to/file.txt');

    // Get temporary (pre-signed) URL
    $url = $adapter->temporaryUrl('path/to/file.txt', new \DateTimeImmutable('+1 hour'));

    // Get temporary upload URL (pre-signed PUT)
    $uploadUrl = $adapter->temporaryUploadUrl('uploads/photo.jpg', new \DateTimeImmutable('+1 hour'), [
        'Content-Type' => 'image/jpeg',
        'Content-Length' => 1024000,
    ]);

    // Copy / Move
    $adapter->copy('source.txt', 'destination.txt');
    $adapter->move('old-path.txt', 'new-path.txt');

    // Delete
    $adapter->delete('path/to/file.txt');
    $adapter->deleteMultiple(['file1.txt', 'file2.txt']);

    // List contents
    foreach ($adapter->listContents('uploads/2024/') as $object) {
        echo $object->path;
    }
}
```

### Testing

Use `InMemoryStorageAdapter` for unit tests:

```php
use WPPack\Component\Storage\Test\InMemoryStorageAdapter;

$adapter = new InMemoryStorageAdapter();
$adapter->write('test.txt', 'hello');
assert($adapter->read('test.txt') === 'hello');
```

## Available Adapters

| Adapter | Package | DSN Scheme |
|---------|---------|------------|
| Amazon S3 | `wppack/s3-storage` | `s3://` |
| Azure Blob Storage | `wppack/azure-storage` | `azure://` |
| Google Cloud Storage | `wppack/gcs-storage` | `gcs://` |
| Local filesystem | `wppack/storage` (core) | `local://` |

## Stream Wrapper

Register a protocol to use standard PHP file functions with any storage adapter:

```php
use WPPack\Component\Storage\StreamWrapper\StorageStreamWrapper;

// Register a protocol
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');
StorageStreamWrapper::register('s3', $adapter);

// Use standard PHP file functions
file_put_contents('s3://path/to/file.txt', 'Hello, World!');
$contents = file_get_contents('s3://path/to/file.txt');
$exists = file_exists('s3://path/to/file.txt');
```

See [Stream Wrapper documentation](../../docs/components/storage/stream-wrapper.md) for details on supported functions, fopen modes, buffering strategy, and StatCache.

## Documentation

- [Storage documentation](../../docs/components/storage/)
