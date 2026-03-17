# wppack/s3-storage-plugin

WordPress plugin for S3-based media storage. A thin S3-specific layer that provides pre-signed URL uploads and S3 event-driven attachment registration.

## Architecture

S3StoragePlugin is a thin layer on top of provider-agnostic components:

- **Stream wrapper** (`s3://` protocol) is provided by `wppack/storage` (`StorageStreamWrapper`)
- **WordPress upload integration** (upload_dir, attachment URLs, image editor) is provided by `wppack/media` (Subscriber classes)
- **S3 adapter** is provided by `wppack/s3-storage` (`S3StorageAdapter`)
- **S3StoragePlugin** provides only S3-specific features: Pre-signed URLs, S3 event handling, and S3 configuration

## Installation

```bash
composer require wppack/s3-storage-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- AWS account with S3 and SQS

## Features

### Pre-signed URL Upload

Browser-to-S3 direct upload without server load:

```javascript
const response = await fetch('/wp-json/wppack/v1/s3/presigned-url', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify({
        filename: file.name,
        content_type: file.type,
        content_length: file.size,
    }),
});

const { url, key } = await response.json();

await fetch(url, {
    method: 'PUT',
    headers: { 'Content-Type': file.type },
    body: file,
});
```

### S3 Event Handling

S3 object creation events are processed via SQS/Lambda:

1. S3 Event Notification triggers SQS message
2. `S3ObjectCreatedHandler` registers WordPress attachment
3. Resized images (thumbnails, scaled, rotated) are automatically skipped
4. `GenerateThumbnailsHandler` generates thumbnails asynchronously

### Multisite Support

Multisite environments are automatically detected. The handler parses `/sites/{blog_id}/` from S3 keys and uses `switch_to_blog()` to register attachments in the correct blog context.

### WP-CLI

```bash
wp media:migrate-storage              # Migrate local files to S3
wp media:migrate-storage --dry-run    # Preview migration
wp media:migrate-storage --batch-size=100
```

## Configuration

Set environment variables:

```bash
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1
S3_BUCKET=my-wordpress-media
S3_PREFIX=uploads
CDN_URL=https://cdn.example.com   # Optional
```

## Documentation

See [full documentation](../../docs/plugins/s3-storage-plugin.md) for details.

## License

MIT
