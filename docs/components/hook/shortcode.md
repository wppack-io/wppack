## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Shortcode/Subscriber/`

ショートコード関連の WordPress フックに対応する Named Hook Attributes を提供します。

### フィルター

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[ShortcodeAttsFilter]` | `shortcode: string` | `shortcode_atts_{shortcode}` |
| `#[DoShortcodeTagFilter]` | - | `do_shortcode_tag` |
| `#[PreDoShortcodeTagFilter]` | - | `pre_do_shortcode_tag` |
| `#[NoTexturizeShortcodesFilter]` | - | `no_texturize_shortcodes` |
| `#[StripShortcodesTagNamesFilter]` | - | `strip_shortcodes_tag_names` |

### 使用例

```php
use WPPack\Component\Hook\Attribute\Shortcode\Filter\ShortcodeAttsFilter;
use WPPack\Component\Hook\Attribute\Shortcode\Filter\DoShortcodeTagFilter;
use WPPack\Component\Hook\Attribute\Shortcode\Filter\NoTexturizeShortcodesFilter;

final class ShortcodeHooks
{
    #[ShortcodeAttsFilter(shortcode: 'gallery')]
    public function filterGalleryAtts(array $out, array $pairs, array $atts): array
    {
        // gallery ショートコードの属性をフィルタリング
        return $out;
    }

    #[DoShortcodeTagFilter(priority: 5)]
    public function filterShortcodeOutput(string $output, string $tag, array $attr): string
    {
        // ショートコード出力をフィルタリング
        return $output;
    }

    #[NoTexturizeShortcodesFilter]
    public function addNoTexturizeShortcodes(array $shortcodes): array
    {
        $shortcodes[] = 'code';
        return $shortcodes;
    }
}
```
