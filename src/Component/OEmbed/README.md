# WpPack OEmbed

WordPress の oEmbed をモダンな PHP で管理するコンポーネントです。`OEmbedProviderInterface` によるプロバイダー自動登録と、Named Hook アトリビュートを提供します。

## インストール

```bash
composer require wppack/oembed
```

## 使い方

### OEmbedProviderInterface（DI 自動収集）

```php
use WpPack\Component\OEmbed\OEmbedProviderDefinition;
use WpPack\Component\OEmbed\OEmbedProviderInterface;

class MyOEmbedProviders implements OEmbedProviderInterface
{
    public function getProviders(): array
    {
        return [
            new OEmbedProviderDefinition('https://example.com/*', 'https://example.com/oembed'),
            new OEmbedProviderDefinition('#https?://custom\.site/.*#i', 'https://custom.site/oembed', regex: true),
        ];
    }
}
```

### OEmbedProviderRegistry（直接使用）

```php
use WpPack\Component\OEmbed\OEmbedProviderRegistry;

$registry = new OEmbedProviderRegistry();
$registry->addDefinition('https://example.com/*', 'https://example.com/oembed');
$registry->addDefinition('#https?://custom\.site/.*#i', 'https://custom.site/oembed', regex: true);
$registry->removeProvider('https://example.com/*');
```

### Named Hook Attributes

```php
use WpPack\Component\OEmbed\Attribute\Filter\OembedProvidersFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedResultFilter;

final class VideoEmbedCustomizer
{
    #[OembedProvidersFilter]
    public function addProvider(array $providers): array
    {
        $providers['#https?://example\.com/videos/([0-9]+)#i'] = [
            'https://example.com/oembed',
            true,
        ];

        return $providers;
    }

    #[OembedResultFilter]
    public function wrapYouTube(string|false $html, string $url, array $args, int $postId): string|false
    {
        if (!$html || !str_contains($url, 'youtube.com')) {
            return $html;
        }

        return '<div class="video-wrapper">' . $html . '</div>';
    }
}
```

**Filter Attributes:**
- `#[OembedProvidersFilter]` — `oembed_providers`
- `#[OembedFetchUrlFilter]` — `oembed_fetch_url`
- `#[PreOembedResultFilter]` — `pre_oembed_result`
- `#[OembedTtlFilter]` — `oembed_ttl`
- `#[OembedResultFilter]` — `oembed_result`
- `#[EmbedOembedHtmlFilter]` — `embed_oembed_html`
- `#[OembedDataparseFilter]` — `oembed_dataparse`
- `#[OembedDiscoveryLinksFilter]` — `oembed_discovery_links`
- `#[OembedResponseDataFilter]` — `oembed_response_data`
- `#[EmbedDefaultsFilter]` — `embed_defaults`
- `#[EmbedHandlersFilter]` — `embed_handlers`
- `#[OembedWhitelistFilter]` — `oembed_whitelist`

## ドキュメント

詳細は [docs/components/oembed/](../../../docs/components/oembed/) を参照してください。

## License

MIT
