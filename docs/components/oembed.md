# OEmbed コンポーネント

**パッケージ:** `wppack/oembed`
**名前空間:** `WpPack\Component\OEmbed\`
**レイヤー:** Application

WordPress oEmbed 関連フックを Named Hook アトリビュートで型安全に利用するためのコンポーネントです。

## インストール

```bash
composer require wppack/oembed
```

## 基本コンセプト

### Before（従来の WordPress）

```php
wp_oembed_add_provider(
    'https://example.com/videos/*',
    'https://example.com/oembed',
    false
);

add_filter('oembed_result', function ($html, $url, $args) {
    if (str_contains($url, 'youtube.com')) {
        $html = '<div class="video-wrapper">' . $html . '</div>';
    }
    return $html;
}, 10, 3);
```

### After（WpPack）

```php
use WpPack\Component\OEmbed\Attribute\OembedProvidersFilter;
use WpPack\Component\OEmbed\Attribute\OembedResultFilter;

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

## Named Hook アトリビュート

### #[OembedProvidersFilter(priority?: int = 10)]

**WordPress フック:** `oembed_providers`

oEmbed プロバイダーの追加・変更を行います。

```php
use WpPack\Component\OEmbed\Attribute\OembedProvidersFilter;

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
use WpPack\Component\OEmbed\Attribute\OembedFetchUrlFilter;

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
use WpPack\Component\OEmbed\Attribute\OembedResultFilter;

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
use WpPack\Component\OEmbed\Attribute\EmbedOembedHtmlFilter;

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
use WpPack\Component\OEmbed\Attribute\OembedDiscoveryLinksFilter;

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

## Hook アトリビュートリファレンス

```php
// プロバイダー管理
#[OembedProvidersFilter(priority?: int = 10)]

// リクエスト
#[OembedFetchUrlFilter(priority?: int = 10)]
#[PreOembedResultFilter(priority?: int = 10)]
#[OembedTtlFilter(priority?: int = 10)]

// レスポンス処理
#[OembedResultFilter(priority?: int = 10)]
#[EmbedOembedHtmlFilter(priority?: int = 10)]
#[OembedDataparseFilter(priority?: int = 10)]

// ディスカバリー
#[OembedDiscoveryLinksFilter(priority?: int = 10)]
#[OembedResponseDataFilter(priority?: int = 10)]

// 表示
#[EmbedDefaultsFilter(priority?: int = 10)]
#[EmbedHandlersFilter(priority?: int = 10)]
```

## WordPress 統合

- **WordPress oEmbed API**（`wp_oembed_add_provider` 等）との互換性を維持
- WordPress コアに登録済みの**デフォルトプロバイダー**（YouTube、Vimeo 等）をそのまま利用可能
- **oEmbed キャッシュ**（`_oembed_*` post meta）は WordPress 標準の仕組みを使用
- **oEmbed ディスカバリー**の有効/無効制御に対応

## 依存関係

### 必須
- **なし** - WordPress oEmbed API のみで動作

### 推奨
- **Hook コンポーネント** - Named Hook アトリビュートの自動登録
