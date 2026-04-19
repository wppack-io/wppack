# wppack/s3-storage-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=s3_storage_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for S3-based media storage. A thin S3-specific layer that provides browser-direct S3 uploads via pre-signed URLs, synchronous attachment registration, and S3 event-driven asynchronous attachment registration.

## Architecture

S3StoragePlugin is a thin layer on top of provider-agnostic components:

- **Stream wrapper** (`s3://` protocol) is provided by `wppack/storage` (`StorageStreamWrapper`)
- **WordPress upload integration** (upload_dir, attachment URLs, image editor) is provided by `wppack/media` (Subscriber classes)
- **S3 adapter** is provided by `wppack/s3-storage` (`S3StorageAdapter`)
- **S3StoragePlugin** provides only S3-specific features: plugin bootstrap, browser-direct upload JS, pre-signed URLs, synchronous attachment registration, S3 event handling, and S3 configuration

## Installation

```bash
composer require wppack/s3-storage-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.3 or higher
- AWS account with S3 and SQS

## Features

### Browser-Direct S3 Upload

`s3-upload.js` automatically intercepts WordPress's media uploader (`wp.Uploader`) and replaces the default upload flow with S3 direct uploads:

1. Fetches a pre-signed PUT URL from the REST API
2. Uploads the file directly to S3 via XMLHttpRequest (with progress tracking)
3. Registers the attachment synchronously via REST API
4. Updates the media modal with the new attachment

No custom JavaScript is required — the script is automatically enqueued on admin pages where the media uploader is active.

### Pre-signed URL Upload (Custom Implementation)

For custom upload flows, use the REST API directly:

```javascript
// 1. Get pre-signed URL
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

// 2. Upload directly to S3
await fetch(url, {
    method: 'PUT',
    headers: { 'Content-Type': file.type },
    body: file,
});

// 3. Register attachment synchronously
const regResponse = await fetch('/wp-json/wppack/v1/s3/register-attachment', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify({ key }),
});

const attachment = await regResponse.json();
```

### S3 Event Handling

S3 object creation events are processed via SQS/Lambda:

1. S3 Event Notification triggers SQS message
2. `S3ObjectCreatedHandler` delegates to `AttachmentRegistrar`
3. `AttachmentRegistrar` detects duplicates (idempotent) and skips resized images
4. `GenerateThumbnailsHandler` generates thumbnails asynchronously

If a browser-direct upload has already registered the attachment, the S3 event is idempotently skipped via `_wp_attached_file` meta lookup.

### Multisite Support

Multisite environments are automatically detected. `AttachmentRegistrar` parses `/sites/{blog_id}/` from S3 keys and uses `switch_to_blog()` to register attachments in the correct blog context.

### WP-CLI

```bash
wp media:migrate-storage              # Migrate local files to S3
wp media:migrate-storage --dry-run    # Preview migration
wp media:migrate-storage --batch-size=100
```

## Configuration

### Settings UI

Configure via Settings → Storage in WordPress admin. Supports multiple storage providers (S3, Azure, GCS, Local) with a primary storage selector and CDN URL per storage.

### Environment Variable / Constant

Set `STORAGE_DSN` as an environment variable or PHP constant:

```bash
# DSN format: s3://accessKey:secretKey@bucket?region=region
STORAGE_DSN=s3://AKIAIOSFODNN7EXAMPLE:secretKey@my-bucket?region=ap-northeast-1

# Uploads path prefix (optional, defaults to wp-content/uploads)
WPPACK_STORAGE_UPLOADS_PATH=wp-content/uploads
```

When using IAM roles, credentials can be omitted:

```bash
STORAGE_DSN=s3://my-bucket?region=ap-northeast-1
```

Environment variables take precedence over wp_options settings.

### S3 CORS Configuration

Browser-direct uploads require CORS configuration on the S3 bucket:

```json
[
    {
        "AllowedOrigins": ["https://example.com"],
        "AllowedMethods": ["PUT"],
        "AllowedHeaders": ["Content-Type"],
        "MaxAgeSeconds": 3600
    }
]
```

```bash
aws s3api put-bucket-cors --bucket my-wordpress-media --cors-configuration file://cors.json
```

## Documentation

See [full documentation](../../docs/plugins/s3-storage-plugin.md) for details.

## License

MIT
