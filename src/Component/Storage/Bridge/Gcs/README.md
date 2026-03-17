# GCS Storage

Google Cloud Storage adapter for [WpPack Storage](../../README.md).

## Installation

```bash
composer require wppack/gcs-storage
```

## Usage

### Via DSN

```php
use WpPack\Component\Storage\Adapter\Storage;

// Using default credentials (Application Default Credentials)
$adapter = Storage::fromDsn('gcs://my-bucket.storage.googleapis.com/uploads');

// With project ID
$adapter = Storage::fromDsn('gcs://my-bucket?project=my-project-id');

// With public URL (CDN)
$adapter = Storage::fromDsn('gcs://my-bucket.storage.googleapis.com/uploads?public_url=https://cdn.example.com');

// With service account key file
$adapter = Storage::fromDsn('gcs://my-bucket?key_file=/path/to/service-account.json');
```

### Direct Instantiation

```php
use Google\Cloud\Storage\StorageClient;
use WpPack\Component\Storage\Bridge\Gcs\GcsStorageAdapter;

$storageClient = new StorageClient(['projectId' => 'my-project-id']);
$bucket = $storageClient->bucket('my-bucket');

$adapter = new GcsStorageAdapter(
    bucket: $bucket,
    prefix: 'uploads',
    publicUrl: 'https://cdn.example.com',
);
```

### Signed URLs (V4)

```php
$url = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
```

## DSN Format

```
gcs://{bucket}.storage.googleapis.com/{prefix}
```

| Part | Meaning | Example |
|------|---------|---------|
| Host | `{bucket}.storage.googleapis.com` | `my-bucket.storage.googleapis.com` |
| Path | Key prefix | `/uploads` |
| Query | Extra options | `?project=my-project-id` |

### Query Options

| Option | Description | Example |
|--------|-------------|---------|
| `public_url` | Public base URL for `publicUrl()` | `https://cdn.example.com` |
| `project` | GCP project ID | `my-project-id` |
| `key_file` | Service account JSON key file path | `/path/to/key.json` |

### Alternative Host Formats

```php
// Plain bucket name
'gcs://my-bucket'

// With project ID
'gcs://my-bucket?project=my-project-id'
```
