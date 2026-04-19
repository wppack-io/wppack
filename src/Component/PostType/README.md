# WPPack PostType

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=post_type)](https://codecov.io/github/wppack-io/wppack)

Custom post type and meta registration for WordPress.

## Installation

```bash
composer require wppack/post-type
```

## Quick Start

```php
use WPPack\Component\PostType\PostRepository;

$repository = new PostRepository();

// Find a post
$post = $repository->find($postId);

// Create a post
$newId = $repository->insert(['post_title' => 'New Post', 'post_status' => 'draft']);

// Update a post
$repository->update(['ID' => $newId, 'post_title' => 'Updated Title']);

// Meta operations
$repository->updateMeta($postId, 'custom_key', 'value');
$value = $repository->getMeta($postId, 'custom_key', single: true);
```

## Documentation

See [docs/components/post-type/](../../../docs/components/post-type/) for full documentation.

## License

MIT
