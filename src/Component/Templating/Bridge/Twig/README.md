# Twig Templating

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=twig_templating)](https://codecov.io/github/wppack-io/wppack)

**Package:** `wppack/twig-templating`
**Namespace:** `WpPack\Component\Templating\Bridge\Twig\`

A Twig bridge for WpPack Templating. Implements `TemplateRendererInterface` using Twig, enabling `.html.twig` templates alongside PHP templates via `ChainRenderer`.

## Installation

```bash
composer require wppack/twig-templating
```

## Usage

```php
use WpPack\Component\Templating\Bridge\Twig\TwigEnvironmentFactory;
use WpPack\Component\Templating\Bridge\Twig\TwigRenderer;
use WpPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;

$factory = new TwigEnvironmentFactory(
    paths: [get_template_directory() . '/templates'],
    extensions: [new WordPressExtension()],
);

$renderer = new TwigRenderer($factory->create());
echo $renderer->render('pages/about', ['title' => 'About Us']);
```

## Dependencies

- `wppack/templating` ^1.0
- `twig/twig` ^3.0

## Documentation

See [docs/components/templating/twig-templating.md](../../../../docs/components/templating/twig-templating.md) for details.

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
