# WPPack Mime

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=mime)](https://codecov.io/github/wppack-io/wppack)

MIME type detection and extension mapping with WordPress integration.

## Installation

```bash
composer require wppack/mime
```

## Usage

```php
use WPPack\Component\Mime\MimeTypes;

$mimeTypes = MimeTypes::getDefault();

// Guess MIME type from file content
$mimeType = $mimeTypes->guessMimeType('/path/to/file.jpg');
// => 'image/jpeg'

// Get extensions for a MIME type
$extensions = $mimeTypes->getExtensions('image/jpeg');
// => ['jpg', 'jpeg', 'jpe']

// Get MIME types for an extension
$types = $mimeTypes->getMimeTypes('jpg');
// => ['image/jpeg']

// Get file type category
$type = $mimeTypes->getExtensionType('jpg');
// => 'image'

// Validate file content against filename
$info = $mimeTypes->validateFile('/path/to/file', 'photo.jpg');
if ($info->isValid()) {
    echo $info->mimeType;   // 'image/jpeg'
    echo $info->extension;  // 'jpg'
}

// Sanitize MIME type string
$clean = $mimeTypes->sanitize('image/jpeg; charset=utf-8');
// => 'image/jpeg'
```

## Documentation

See [docs/components/mime/](../../../docs/components/mime/) for full documentation.

## License

MIT
