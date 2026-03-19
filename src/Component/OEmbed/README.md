# WpPack OEmbed

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=oembed)](https://codecov.io/github/wppack-io/wppack)

A component for managing WordPress oEmbed with modern PHP. Provides automatic provider registration via `OEmbedProviderInterface` and Named Hook attributes.

## Installation

```bash
composer require wppack/oembed
```

## Usage

### OEmbedProviderInterface (DI Auto-Collection)

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

### OEmbedProviderRegistry (Direct Usage)

```php
use WpPack\Component\OEmbed\OEmbedProviderRegistry;

$registry = new OEmbedProviderRegistry();
$registry->addDefinition('https://example.com/*', 'https://example.com/oembed');
$registry->addDefinition('#https?://custom\.site/.*#i', 'https://custom.site/oembed', regex: true);
$registry->removeProvider('https://example.com/*');
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedProvidersFilter;
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedResultFilter;

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

## Documentation

See [docs/components/oembed/](../../../docs/components/oembed/) for details.

## License

MIT
