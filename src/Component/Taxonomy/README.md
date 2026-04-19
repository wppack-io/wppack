# WPPack Taxonomy

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=taxonomy)](https://codecov.io/github/wppack-io/wppack)

Taxonomy registration and management for WordPress.

## Installation

```bash
composer require wppack/taxonomy
```

## Quick Start

```php
use WPPack\Component\Taxonomy\TermRepository;

$repository = new TermRepository();

// Find terms
$terms = $repository->findAll(['taxonomy' => 'category']);
$term = $repository->find($termId, 'category');
$term = $repository->findBySlug('my-term', 'category');

// Create a term
$result = $repository->insert('New Category', 'category', ['slug' => 'new-cat']);

// Object-term relationships
$repository->setObjectTerms($postId, [$termId1, $termId2], 'category');
$terms = $repository->getObjectTerms($postId, 'category');
```

## Documentation

See [docs/components/taxonomy/](../../../docs/components/taxonomy/) for full documentation.

## License

MIT
