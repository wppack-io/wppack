# WpPack Theme

Named hook attributes for WordPress theme-related hooks including assets, template output, customizer, and filters.

## Installation

```bash
composer require wppack/theme
```

## Usage

### Enqueue Scripts

```php
use WpPack\Component\Hook\Attribute\Theme\Action\WpEnqueueScriptsAction;

final class AssetLoader
{
    #[WpEnqueueScriptsAction]
    public function enqueueAssets(): void
    {
        wp_enqueue_style('my-theme', get_stylesheet_uri());
        wp_enqueue_script('my-theme', get_template_directory_uri() . '/assets/js/theme.js', [], null, true);
    }
}
```

### Body Class Filter

```php
use WpPack\Component\Hook\Attribute\Theme\Filter\BodyClassFilter;

final class ThemeBodyClass
{
    #[BodyClassFilter]
    public function addClasses(array $classes): array
    {
        $classes[] = 'custom-theme';
        return $classes;
    }
}
```

## Available Attributes

### Actions

- `WpEnqueueScriptsAction` — `wp_enqueue_scripts`
- `WpPrintStylesAction` — `wp_print_styles`
- `WpPrintScriptsAction` — `wp_print_scripts`
- `WpHeadAction` — `wp_head`
- `WpFooterAction` — `wp_footer`
- `WpBodyOpenAction` — `wp_body_open`
- `CustomizeRegisterAction` — `customize_register`
- `CustomizePreviewInitAction` — `customize_preview_init`

### Filters

- `BodyClassFilter` — `body_class`
- `PostClassFilter` — `post_class`
- `ScriptLoaderTagFilter` — `script_loader_tag`
- `StyleLoaderTagFilter` — `style_loader_tag`

## Documentation

See [docs/components/theme/](../../../docs/components/theme/) for full documentation.

## License

MIT
