# Site Component

The Site component provides an OOP abstraction for WordPress multisite management, including blog switching, context queries, and site repository operations.

## Installation

```bash
composer require wppack/site
```

## Usage

### BlogContext

Read-only multisite state queries:

```php
use WpPack\Component\Site\BlogContext;

$context = new BlogContext();

$blogId = $context->getCurrentBlogId();
$isMultisite = $context->isMultisite();
$isSwitched = $context->isSwitched();
```

### BlogSwitcher

Safe blog switching with automatic restore via callable:

```php
use WpPack\Component\Site\BlogSwitcher;

$switcher = new BlogSwitcher();

// Always switches, even if already on the target blog
$result = $switcher->runInBlog(2, function () {
    return get_option('blogname');
});

// Skips switching if already on the target blog
$result = $switcher->runInBlogIfNeeded(2, function () {
    return get_option('blogname');
});
```

Direct switch/restore for cases that don't fit a single callback:

```php
$switcher->switchToBlog(2);
try {
    $name = get_option('blogname');
    $url = get_option('siteurl');
} finally {
    $switcher->restoreCurrentBlog();
}
```

### SiteRepository

Multisite site queries and resolution:

```php
use WpPack\Component\Site\SiteRepository;

$repository = new SiteRepository();

$sites = $repository->findAll();
$site = $repository->findById(2);
$blogId = $repository->findByUrl('example.com', '/blog/');
$blogId = $repository->findBySlug('myblog');
$domains = $repository->getAllDomains();
```

## Documentation

For detailed documentation in Japanese, see [docs/components/site/](../../../docs/components/site/).
