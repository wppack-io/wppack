## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/OEmbed/Subscriber/`

### #[OembedProvidersFilter(priority?: int = 10)]

**WordPress フック:** `oembed_providers`

oEmbed プロバイダーの追加・変更を行います。

```php
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedProvidersFilter;

final class CustomProviderRegistrar
{
    #[OembedProvidersFilter]
    public function registerProviders(array $providers): array
    {
        // カスタム動画プラットフォームを追加
        $providers['#https?://(?:www\.)?customvideo\.com/watch/([^/]+)#i'] = [
            'https://customvideo.com/oembed',
            true,
        ];

        // 不要なプロバイダーを除去
        unset($providers['https://www.example.com/*']);

        return $providers;
    }
}
```

### #[OembedFetchUrlFilter(priority?: int = 10)]

**WordPress フック:** `oembed_fetch_url`

oEmbed リクエスト URL を変更します。

```php
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedFetchUrlFilter;

final class OEmbedRequestCustomizer
{
    #[OembedFetchUrlFilter]
    public function addApiKey(string $provider, string $url, array $args): string
    {
        if (str_contains($provider, 'customvideo.com')) {
            $provider = add_query_arg('api_key', get_option('customvideo_api_key'), $provider);
        }

        return $provider;
    }
}
```

### #[OembedResultFilter(priority?: int = 10)]

**WordPress フック:** `oembed_result`

oEmbed レスポンス HTML を加工します。

```php
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedResultFilter;

final class OEmbedResponseProcessor
{
    #[OembedResultFilter]
    public function addLazyLoading(string|false $html, string $url, array $args, int $postId): string|false
    {
        if (!$html) {
            return $html;
        }

        // iframe に遅延読み込みを追加
        return str_replace('<iframe', '<iframe loading="lazy"', $html);
    }
}
```

### #[EmbedOembedHtmlFilter(priority?: int = 10)]

**WordPress フック:** `embed_oembed_html`

キャッシュされた oEmbed HTML を出力前にフィルタリングします。

```php
use WpPack\Component\Hook\Attribute\OEmbed\Filter\EmbedOembedHtmlFilter;

final class EmbedDisplayFilter
{
    #[EmbedOembedHtmlFilter]
    public function wrapWithContainer(string $html, string $url, array $attr, int $postId): string
    {
        $provider = match (true) {
            str_contains($url, 'youtube.com') => 'youtube',
            str_contains($url, 'vimeo.com') => 'vimeo',
            default => 'generic',
        };

        return sprintf('<div class="embed--%s">%s</div>', esc_attr($provider), $html);
    }
}
```

### #[OembedDiscoveryLinksFilter(priority?: int = 10)]

**WordPress フック:** `oembed_discovery_links`

サイトの `<head>` に出力される oEmbed ディスカバリーリンクをカスタマイズします。

```php
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedDiscoveryLinksFilter;

final class DiscoveryLinksCustomizer
{
    #[OembedDiscoveryLinksFilter]
    public function disableDiscovery(string $output): string
    {
        // oEmbed ディスカバリーを無効化
        return '';
    }
}
```
