# wppack/s3-storage-plugin

WordPress plugin for S3-based media storage. Replaces WordPress media uploads with direct browser-to-S3 uploads via pre-signed URLs, with asynchronous attachment registration through SQS.

## Installation

```bash
composer require wppack/s3-storage-plugin
```

## Requirements

- PHP 8.1+
- WordPress 6.x
- AWS account with S3 and SQS

## Usage

### Upload Flow

The plugin automatically handles media uploads:

1. Browser requests a pre-signed URL via REST API
2. File is uploaded directly to S3 (no server load)
3. S3 event triggers SQS message
4. Lambda handler registers the WordPress attachment

### Pre-signed URL API

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

### WP-CLI

```bash
wp wppack s3 migrate              # Migrate local files to S3
wp wppack s3 migrate --dry-run    # Preview migration
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
