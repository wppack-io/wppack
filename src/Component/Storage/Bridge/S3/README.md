# S3 Storage

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=s3_storage)](https://codecov.io/github/wppack-io/wppack)

Amazon S3 adapter for [WpPack Storage](../../README.md).

## Installation

```bash
composer require wppack/s3-storage
```

## Usage

### Via DSN

```php
use WpPack\Component\Storage\Adapter\Storage;

// Using default AWS credentials (IAM role, environment variables, etc.)
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

// With explicit credentials
$adapter = Storage::fromDsn('s3://ACCESS_KEY:SECRET_KEY@my-bucket.s3.ap-northeast-1.amazonaws.com');

// With public URL (CDN)
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads?public_url=https://cdn.example.com');

// With custom endpoint (MinIO, LocalStack, etc.)
$adapter = Storage::fromDsn('s3://my-bucket?endpoint=http://localhost:9000');
```

### Direct Instantiation

```php
use AsyncAws\S3\S3Client;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;

$s3Client = new S3Client(['region' => 'ap-northeast-1']);

$adapter = new S3StorageAdapter(
    s3Client: $s3Client,
    bucket: 'my-bucket',
    prefix: 'uploads',
    publicUrl: 'https://cdn.example.com',
);
```

### Temporary URLs (Pre-signed)

```php
$url = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
```

### Temporary Upload URLs (Pre-signed PUT)

```php
$url = $adapter->temporaryUploadUrl('uploads/photo.jpg', new \DateTimeImmutable('+1 hour'), [
    'Content-Type' => 'image/jpeg',
    'Content-Length' => 1024000,
]);
```

## DSN Format

The DSN mirrors the actual S3 virtual-hosted style URL:

```
s3://{bucket}.s3.{region}.amazonaws.com/{prefix}
```

| Part | Meaning | Example |
|------|---------|---------|
| Host | `{bucket}.s3.{region}.amazonaws.com` | `my-bucket.s3.ap-northeast-1.amazonaws.com` |
| Path | Key prefix | `/uploads` |
| User:Pass | AWS credentials (optional) | `AKID:SECRET@` |
| Query | Extra options | `?public_url=https://cdn.example.com` |

### Query Options

| Option | Description | Example |
|--------|-------------|---------|
| `public_url` | Public base URL for `publicUrl()` | `https://cdn.example.com` |
| `endpoint` | Custom endpoint (MinIO, R2) | `http://localhost:9000` |
| `region` | Override region (for plain bucket host) | `ap-northeast-1` |

### Alternative Host Formats

```php
// Without region (uses AWS SDK default)
's3://my-bucket.s3.amazonaws.com/uploads'

// Plain bucket name (for custom endpoints)
's3://my-bucket?endpoint=http://localhost:9000'
```
