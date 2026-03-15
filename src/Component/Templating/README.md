# WpPack Templating

A PHP template engine for WordPress with layout inheritance, sections, automatic escaping, and partial includes. Follows the Plates / Symfony PhpEngine pattern with an engine-agnostic interface for future Twig support.

## Installation

```bash
composer require wppack/templating
```

## Usage

### Basic Rendering

```php
use WpPack\Component\Templating\PhpRenderer;

$renderer = new PhpRenderer([
    get_template_directory() . '/templates',
]);

$html = $renderer->render('partials/card', [
    'title' => 'Hello World',
    'body' => 'Card content.',
]);
```

### Template File (`partials/card.php`)

```php
<div class="card">
    <h3><?= $this->e($title) ?></h3>
    <p><?= $this->e($body) ?></p>
</div>
```

### Layout Inheritance

Layout template (`layouts/base.php`):

```php
<html>
<body>
    <?= $this->section('content') ?>
</body>
</html>
```

Child template (`pages/about.php`):

```php
<?php $this->layout('layouts/base'); ?>
<article>
    <h1><?= $this->e($title) ?></h1>
</article>
```

### Sections

```php
<?php $this->layout('layouts/two-column'); ?>

<?php $this->start('sidebar'); ?>
<nav>Sidebar content</nav>
<?php $this->stop(); ?>

<article><?= $this->e($title) ?></article>
```

### Partial Includes

```php
<?= $this->include('partials/card', ['title' => 'Card', 'body' => 'Content']) ?>
```

### Escaping

```php
<?= $this->e($title) ?>                <!-- HTML escape (default) -->
<?= $this->e($value, 'attr') ?>        <!-- Attribute escape -->
<?= $this->e($url, 'url') ?>           <!-- URL escape -->
<?= $this->e($name, 'js') ?>           <!-- JavaScript escape -->
<?= $this->raw($trustedHtml) ?>        <!-- No escaping -->
```

### Template Locator

```php
use WpPack\Component\Templating\TemplateLocator;

$locator = new TemplateLocator(['/templates', '/fallback']);
$file = $locator->locate('partials/card');            // partials/card.php
$file = $locator->locate('partials/card', 'featured'); // partials/card-featured.php → card.php
```

### Chain Renderer

```php
use WpPack\Component\Templating\ChainRenderer;
use WpPack\Component\Templating\PhpRenderer;

$chain = new ChainRenderer([
    new PhpRenderer(['/templates']),
]);

$html = $chain->render('pages/about', ['title' => 'About']);
```

### TemplatePart (WordPress)

```php
use WpPack\Component\Templating\TemplatePart;

TemplatePart::render('template-parts/content', 'post', ['show_thumbnail' => true]);
$html = TemplatePart::capture('template-parts/card', 'product');
```

### DI Integration

```php
use WpPack\Component\Templating\DependencyInjection\TemplatingServiceProvider;

$builder->addServiceProvider(new TemplatingServiceProvider(
    paths: [get_template_directory() . '/templates'],
));
```

### Testing

```php
$renderer = new PhpRenderer([__DIR__ . '/Fixtures/templates']);

$html = $renderer->render('simple', ['title' => 'Test']);
self::assertStringContainsString('Test', $html);
```

## API Reference

| Class | Description |
|-------|-------------|
| `TemplateRendererInterface` | Engine-agnostic contract (`render`, `exists`, `supports`) |
| `PhpRenderer` | PHP template engine with layouts, sections, escaping |
| `ChainRenderer` | Delegates to first supporting renderer |
| `TemplateLocator` | Template file resolution (WP themes + custom paths) |
| `TemplateContext` | Template `$this` context (`e`, `raw`, `layout`, `section`, `start`, `stop`, `include`) |
| `TemplatePart` | `get_template_part()` wrapper with `capture()` |
| `TemplatingServiceProvider` | DI registration |

## Documentation

See [docs/components/templating/](../../../docs/components/templating/) for details.

## License

MIT
