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

## Documentation

See [docs/components/media.md](../../docs/components/media.md) for full documentation.

## License

MIT
