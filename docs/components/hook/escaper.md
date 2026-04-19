## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Escaper/Subscriber/`

### `#[EscHtmlFilter]`

**WordPress Hook:** `esc_html`

```php
use WPPack\Component\Hook\Attribute\Escaper\Filter\EscHtmlFilter;

final class HtmlEscaper
{
    #[EscHtmlFilter(priority: 10)]
    public function escapeHtml(string $safeText, string $text): string
    {
        return $safeText;
    }
}
```

### `#[EscAttrFilter]`

**WordPress Hook:** `esc_attr`

```php
use WPPack\Component\Hook\Attribute\Escaper\Filter\EscAttrFilter;

final class AttrEscaper
{
    #[EscAttrFilter(priority: 10)]
    public function escapeAttr(string $safeText, string $text): string
    {
        return $safeText;
    }
}
```

### `#[EscUrlFilter]`

**WordPress Hook:** `esc_url`

```php
use WPPack\Component\Hook\Attribute\Escaper\Filter\EscUrlFilter;

final class UrlEscaper
{
    #[EscUrlFilter(priority: 10)]
    public function escapeUrl(string $url, string $originalUrl, string $context): string
    {
        return $url;
    }
}
```

### `#[EscJsFilter]`

**WordPress Hook:** `esc_js`

```php
use WPPack\Component\Hook\Attribute\Escaper\Filter\EscJsFilter;

final class JsEscaper
{
    #[EscJsFilter(priority: 10)]
    public function escapeJs(string $safeText, string $text): string
    {
        return $safeText;
    }
}
```

## クイックリファレンス

| Attribute | WordPress Hook | 説明 |
|-----------|---------------|------|
| `#[EscHtmlFilter(priority: 10)]` | `esc_html` | HTML エスケープをフィルター |
| `#[EscAttrFilter(priority: 10)]` | `esc_attr` | 属性値エスケープをフィルター |
| `#[EscUrlFilter(priority: 10)]` | `esc_url` | URL エスケープをフィルター |
| `#[EscJsFilter(priority: 10)]` | `esc_js` | JS エスケープをフィルター |
```
