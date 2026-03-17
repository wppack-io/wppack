# WpPack Media

Type-safe media library and attachment management for WordPress.

## Installation

```bash
composer require wppack/media
```

## Usage

### Upload

```php
use WpPack\Component\Media\MediaManager;
use WpPack\Component\Media\Upload\UploadedFile;

$mediaManager = new MediaManager();

$file = UploadedFile::fromGlobals('my_file_input');
$attachment = $mediaManager->upload($file, parentPostId: $postId);
```

### Attachment

```php
$attachment = $mediaManager->find($attachmentId);

$url = $attachment->getUrl();
$url = $attachment->getUrl('thumbnail');
$html = $attachment->toHtml('large', ['class' => 'featured-image']);
```

### Image Sizes

```php
use WpPack\Component\Media\ImageSize;

$mediaManager->addImageSize(new ImageSize(
    name: 'card-thumbnail',
    width: 400,
    height: 300,
    crop: true,
));
```

### S3 Integration

For S3 media storage, install `wppack/s3-storage-plugin`. The Media API remains the same.

## Storage Integration

The Media component includes built-in support for replacing WordPress uploads with any object storage backend via `StorageAdapterInterface`. Subscriber classes hook into WordPress upload/attachment lifecycle to transparently redirect file operations through the Storage component's stream wrapper.

Key classes:

- `Storage\Subscriber\UploadDirSubscriber` - Rewrites `upload_dir` paths to stream wrapper paths
- `Storage\Subscriber\AttachmentSubscriber` - Handles attachment URLs, file paths, deletion, and metadata
- `Storage\Subscriber\ImageEditorSubscriber` - Registers `StorageImageEditor` for remote image processing
- `Storage\Subscriber\SideloadSubscriber` - Ensures sideload compatibility with stream wrappers
- `Storage\ImageEditor\StorageImageEditor` - Downloads to local temp, processes with Imagick, writes back
- `Storage\Command\MigrateCommand` - WP-CLI command to migrate local uploads to object storage
- `Storage\StorageConfiguration` - Storage connection configuration value object
- `Storage\UrlResolver` - Resolves storage keys to CDN or public URLs

See [Storage Integration documentation](../../docs/components/media/storage.md) for details.

## Documentation

See [docs/components/media/](../../docs/components/media/) for full documentation.

## License

MIT
