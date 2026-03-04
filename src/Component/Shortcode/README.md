# WpPack Shortcode

WordPress ショートコードをオブジェクト指向で定義するコンポーネント。`AbstractShortcode` + `#[AsShortcode]` アトリビュートによるショートコード定義、および Named Hook Attributes を提供します。

## インストール

```bash
composer require wppack/shortcode
```

## 使い方

### ショートコード定義

`configureAttributes()` をオーバーライドして、デフォルト値・バリデーションを宣言的に定義できます。`render()` には解決済みのアトリビュートが渡されます。内部で `shortcode_atts()` も呼ばれるため、`shortcode_atts_{shortcode}` フィルターとの互換性も維持されます。

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

### 依存性注入を使用したショートコード

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
use WpPack\Component\Shortcode\Attribute\Filter\ShortcodeAttsFilter;
use WpPack\Component\Shortcode\Attribute\Filter\DoShortcodeTagFilter;
use WpPack\Component\Shortcode\Attribute\Filter\PreDoShortcodeTagFilter;
use WpPack\Component\Shortcode\Attribute\Filter\NoTexturizeShortcodesFilter;
use WpPack\Component\Shortcode\Attribute\Filter\StripShortcodesTagNamesFilter;

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
- `#[ShortcodeAttsFilter]` — `shortcode_atts_{shortcode}`（動的フック名、`shortcode: string` パラメータ）
- `#[DoShortcodeTagFilter]` — `do_shortcode_tag`
- `#[PreDoShortcodeTagFilter]` — `pre_do_shortcode_tag`
- `#[NoTexturizeShortcodesFilter]` — `no_texturize_shortcodes`
- `#[StripShortcodesTagNamesFilter]` — `strip_shortcodes_tag_names`

## ドキュメント

詳細は [docs/components/shortcode/](../../../docs/components/shortcode/) を参照してください。

## ライセンス

MIT
