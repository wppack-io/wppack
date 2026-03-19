# WpPack Shortcode

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=shortcode)](https://codecov.io/github/wppack-io/wppack)

A component for defining WordPress shortcodes in an object-oriented manner. Provides shortcode definitions via `AbstractShortcode` + `#[AsShortcode]` attributes, along with Named Hook Attributes.

## Installation

```bash
composer require wppack/shortcode
```

## Usage

### Shortcode Definition

Override `configureAttributes()` to declaratively define default values and validation. The `render()` method receives the resolved attributes. Since `shortcode_atts()` is also called internally, compatibility with the `shortcode_atts_{shortcode}` filter is maintained.

```php
use WpPack\Component\OptionsResolver\OptionsResolver;
use WpPack\Component\Shortcode\AbstractShortcode;
use WpPack\Component\Shortcode\Attribute\AsShortcode;

#[AsShortcode(name: 'button', description: 'Styled button shortcode')]
class ButtonShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'url' => '#',
            'style' => 'primary',
        ]);
        $resolver->setAllowedValues('style', ['primary', 'secondary', 'danger']);
    }

    public function render(array $atts, string $content): string
    {
        return sprintf(
            '<a href="%s" class="btn btn-%s">%s</a>',
            esc_url($atts['url']),
            esc_attr($atts['style']),
            esc_html($content),
        );
    }
}
```

### Shortcode with Dependency Injection

```php
#[AsShortcode(name: 'recent_posts', description: 'Display recent posts')]
class RecentPostsShortcode extends AbstractShortcode
{
    public function __construct(
        private PostRepository $postRepository,
    ) {}

    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'count' => '5',
            'category' => '',
        ]);
    }

    public function render(array $atts, string $content): string
    {
        $posts = $this->postRepository->getRecent((int) $atts['count']);

        $html = '<ul>';
        foreach ($posts as $post) {
            $html .= sprintf('<li>%s</li>', esc_html($post->post_title));
        }
        $html .= '</ul>';

        return $html;
    }
}
```

### ShortcodeRegistry

```php
use WpPack\Component\Shortcode\ShortcodeRegistry;

$registry = new ShortcodeRegistry();
$registry->register(new ButtonShortcode());
$registry->register(new RecentPostsShortcode($postRepository));
$registry->unregister('button');
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\Shortcode\Filter\ShortcodeAttsFilter;
use WpPack\Component\Hook\Attribute\Shortcode\Filter\DoShortcodeTagFilter;
use WpPack\Component\Hook\Attribute\Shortcode\Filter\PreDoShortcodeTagFilter;
use WpPack\Component\Hook\Attribute\Shortcode\Filter\NoTexturizeShortcodesFilter;
use WpPack\Component\Hook\Attribute\Shortcode\Filter\StripShortcodesTagNamesFilter;

final class ShortcodeHooks
{
    #[ShortcodeAttsFilter(shortcode: 'gallery')]
    public function filterGalleryAtts(array $out, array $pairs, array $atts): array
    {
        // Filter gallery shortcode attributes
        return $out;
    }

    #[DoShortcodeTagFilter]
    public function filterShortcodeOutput(string $output, string $tag, array $attr): string
    {
        return $output;
    }

    #[PreDoShortcodeTagFilter]
    public function preFilterShortcode(false|string $output, string $tag, array $attr): false|string
    {
        return $output;
    }

    #[NoTexturizeShortcodesFilter]
    public function addNoTexturizeShortcodes(array $shortcodes): array
    {
        $shortcodes[] = 'code';
        return $shortcodes;
    }

    #[StripShortcodesTagNamesFilter]
    public function filterStripShortcodes(array $tags): array
    {
        return $tags;
    }
}
```

**Filter Attributes:**
- `#[ShortcodeAttsFilter]` — `shortcode_atts_{shortcode}` (dynamic hook name, `shortcode: string` parameter)
- `#[DoShortcodeTagFilter]` — `do_shortcode_tag`
- `#[PreDoShortcodeTagFilter]` — `pre_do_shortcode_tag`
- `#[NoTexturizeShortcodesFilter]` — `no_texturize_shortcodes`
- `#[StripShortcodesTagNamesFilter]` — `strip_shortcodes_tag_names`

## Documentation

See [docs/components/shortcode/](../../../docs/components/shortcode/) for details.

## License

MIT
