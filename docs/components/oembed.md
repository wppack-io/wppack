# OEmbed コンポーネント

OEmbed コンポーネントは、WordPress の oEmbed 機能に対して、拡張されたプロバイダー管理、カスタム埋め込みハンドラー、キャッシュ最適化を備えたモダンなオブジェクト指向アプローチを提供します。

## このコンポーネントの機能

OEmbed コンポーネントは、WordPress の oEmbed 機能を以下のように変革します：

- **オブジェクト指向の oEmbed プロバイダー管理** - クラスベースのプロバイダー
- **カスタム oEmbed プロバイダー登録** - 独自サービス用
- **強化された埋め込みキャッシュ** - 設定可能な戦略
- **埋め込みのカスタマイズとフィルタリング** - 完全な制御
- **レスポンシブ埋め込み処理** - 自動サイズ調整
- **セキュリティとプライバシー制御** - ユーザー保護用
- **カスタム埋め込みテンプレート** - ブランド化された体験
- **埋め込みディスカバリー最適化** - 未知の URL 用
- **リッチメディアメタデータ処理** - 表示の強化

## クイック例

従来の WordPress oEmbed 処理の代わりに：

```php
// Traditional WordPress - limited control and customization
// Add custom oEmbed provider
wp_oembed_add_provider(
    'https://example.com/videos/*',
    'https://example.com/oembed',
    false
);

// Filter oEmbed result
add_filter('oembed_result', function($html, $url, $args) {
    // Basic string manipulation
    if (strpos($url, 'youtube.com') !== false) {
        $html = str_replace('width="560"', 'width="100%"', $html);
    }
    return $html;
}, 10, 3);

// Remove specific provider
wp_oembed_remove_provider('https://example.com/videos/*');

// Get oEmbed HTML
$embed_html = wp_oembed_get('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

// Cache handling is automatic but limited
// No built-in:
// - Provider management
// - Advanced customization
// - Security controls
// - Metadata handling
// - Custom templates
```

このモダンな WpPack アプローチを使用します：

```php
use WpPack\Component\oEmbed\AbstractoEmbedProvider;
use WpPack\Component\oEmbed\Attribute\oEmbedProvider;
use WpPack\Component\oEmbed\oEmbedManager;
use WpPack\Component\oEmbed\EmbedResponse;

#[oEmbedProvider(
    pattern: 'https://example.com/videos/*',
    endpoint: 'https://example.com/oembed',
    format: 'json'
)]
class CustomVideoProvider extends AbstractoEmbedProvider
{
    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private SecurityManager $security
    ) {}

    public function fetch(string $url, array $args = []): ?EmbedResponse
    {
        // Check cache first
        $cacheKey = $this->getCacheKey($url, $args);
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Fetch from provider
        $response = $this->http->get($this->endpoint, [
            'url' => $url,
            'maxwidth' => $args['width'] ?? 600,
            'maxheight' => $args['height'] ?? 400
        ]);

        if (!$response->isSuccessful()) {
            return null;
        }

        $data = $response->json();

        // Create embed response with metadata
        $embed = new EmbedResponse();
        $embed->setType($data['type']);
        $embed->setHtml($this->sanitizeHtml($data['html']));
        $embed->setWidth($data['width']);
        $embed->setHeight($data['height']);
        $embed->setProviderName($data['provider_name']);
        $embed->setProviderUrl($data['provider_url']);
        $embed->setThumbnailUrl($data['thumbnail_url'] ?? null);
        $embed->setTitle($data['title'] ?? null);
        $embed->setAuthorName($data['author_name'] ?? null);

        // Cache the response
        $this->cache->set($cacheKey, $embed, 3600);

        return $embed;
    }

}
```

## コア機能

### プロバイダー管理

oEmbed プロバイダーの登録と管理：

```php
class oEmbedService
{
    public function __construct(
        private oEmbedManager $oEmbed,
        private ProviderRegistry $providers
    ) {}

    #[Action('init')]
    public function onInit(): void
    {
        // Register custom providers
        $this->providers->register(new PodcastProvider());
        $this->providers->register(new CustomVideoProvider());
    }
}
```

## このコンポーネントの使用場面

**最適な用途：**
- リッチメディアコンテンツを含むサイト
- カスタムコンテンツプロバイダー
- プライバシー重視のアプリケーション
- パフォーマンスが重要なサイト
- ブランド化された埋め込み体験
- 埋め込み制御が必要なサイト
- カスタムプレイヤーを持つアプリケーション

**代替を検討すべき場合：**
- 基本的なブログの埋め込み
- 標準プロバイダーのみを使用するサイト
- デフォルトの WordPress oEmbed で十分な場合
- 最小限の埋め込み要件

## WordPress 統合

このコンポーネントは WordPress の oEmbed 機能を強化します：

- **プロバイダー登録** - wp_oembed_add_provider() を使用
- **埋め込みディスカバリー** - oEmbed ディスカバリーを拡張
- **キャッシュ統合** - WordPress transients と連携
- **フィルター互換性** - oembed_result フィルターをサポート
- **ブロックエディター** - 埋め込みブロックと統合
- **セキュリティ** - WordPress のサニタイズを使用

## 高度な機能

### 自動ディスカバリー

oEmbed エンドポイントの自動検出：

```php
class AutoDiscoveryService
{
    public function __construct(
        private oEmbedManager $oEmbed,
        private DiscoveryInterface $discovery,
        private CacheInterface $cache
    ) {}

    public function tryEmbed(string $url): ?EmbedResponse
    {
        // Check if we have a known provider
        if ($embed = $this->oEmbed->getEmbed($url)) {
            return $embed;
        }

        // Try discovery
        $endpoint = $this->discovery->discover($url);

        if (!$endpoint) {
            return null;
        }

        // Create temporary provider
        $provider = new GenericProvider($endpoint['endpoint'], $endpoint['format']);

        return $provider->fetch($url);
    }
}
```

### カスタムテンプレート

ブランド化された埋め込み体験の作成：

```php
class CustomEmbedTemplates
{
    #[Filter('wppack_oembed_html', priority: 10)]
    public function customizeEmbedOutput(string $html, EmbedResponse $embed, string $url): string
    {
        // Use custom template for specific providers
        $provider = strtolower($embed->getProviderName());

        if ($this->templates->exists("oembed/{$provider}")) {
            return $this->templates->render("oembed/{$provider}", [
                'embed' => $embed,
                'url' => $url,
                'html' => $html
            ]);
        }

        return $html;
    }
}
```

## インストール

```bash
composer require wppack/oembed
```

詳細なインストール手順については、[インストールガイド](../../guides/installation.md)を参照してください。

## はじめに

1. **[oEmbed クイックスタート](quick-start.md)** - 5分で最初のプロバイダーを作成
2. **[コンポーネント概要](../README.md)** - 他の WpPack コンポーネントを探索
3. **[WordPress 統合](../../guides/wordpress-integration.md)** - WordPress パターン

## 依存関係

### 必須
- **HttpClient コンポーネント** - oEmbed データの取得用

### 推奨
- **Hook コンポーネント** - WordPress 統合用
- **Cache コンポーネント** - レスポンスキャッシュ用
- **Security コンポーネント** - 埋め込みサニタイズ用
- **Config コンポーネント** - 設定管理用

## パフォーマンス機能

- **WordPress transients 連携** - oEmbed キャッシュの活用
- **遅延読み込み** - オンデマンドで埋め込みを読み込み
- **プロバイダー最適化** - 効率的なディスカバリー

## セキュリティ機能

- **HTML サニタイズ** - 埋め込み出力のクリーニング
- **プライバシーモード** - 強化されたプライバシーオプション
- **URL バリデーション** - 悪意のある URL の防止
- **コンテンツフィルタリング** - 不要な要素の除去

## 次のステップ

- **[oEmbed クイックスタート](quick-start.md)** - 最初のプロバイダーを構築
- **[コンポーネント概要](../README.md)** - 他の WpPack コンポーネントを探索
- **[WordPress 統合](../../guides/wordpress-integration.md)** - WordPress パターン

# OEmbed クイックスタート

WpPack OEmbed コンポーネントを5分で始めましょう。このガイドでは、カスタム oEmbed プロバイダーの作成、埋め込みの変換、WordPress oEmbed 機能の強化方法を紹介します。

## インストールとセットアップ

### 1. コンポーネントのインストール

```bash
composer require wppack/oembed
```

### 2. 基本的なサービスセットアップ

```php
use WpPack\Component\oEmbed\oEmbedManager;
use WpPack\Component\oEmbed\ProviderRegistry;

class oEmbedService
{
    public function __construct(
        private oEmbedManager $oEmbed,
        private ProviderRegistry $providers
    ) {}
}
```

## 最初のカスタムプロバイダー

### 1. シンプルなプロバイダーの作成

```php
use WpPack\Component\oEmbed\AbstractoEmbedProvider;
use WpPack\Component\oEmbed\Attribute\oEmbedProvider;
use WpPack\Component\oEmbed\EmbedResponse;

#[oEmbedProvider(
    pattern: 'https://videos.example.com/watch/*',
    endpoint: 'https://videos.example.com/oembed',
    format: 'json'
)]
class VideoProvider extends AbstractoEmbedProvider
{
    public function fetch(string $url, array $args = []): ?EmbedResponse
    {
        // Extract video ID from URL
        if (!preg_match('/watch\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return null;
        }

        $videoId = $matches[1];

        // Create embed HTML
        $html = sprintf(
            '<iframe src="https://videos.example.com/embed/%s" width="%d" height="%d" frameborder="0" allowfullscreen></iframe>',
            esc_attr($videoId),
            $args['width'] ?? 640,
            $args['height'] ?? 360
        );

        // Create response
        $embed = new EmbedResponse();
        $embed->setType('video');
        $embed->setHtml($html);
        $embed->setWidth($args['width'] ?? 640);
        $embed->setHeight($args['height'] ?? 360);
        $embed->setProviderName('Example Videos');
        $embed->setProviderUrl('https://videos.example.com');

        return $embed;
    }
}
```

### 2. プロバイダーの登録

```php
class MyoEmbedService
{
    public function __construct(
        private ProviderRegistry $providers
    ) {}

    #[Action('init')]
    public function onInit(): void
    {
        // Register your custom provider
        $this->providers->register(new VideoProvider());
    }
}
```

### 3. プロバイダーの使用

```php
// In your content
$url = 'https://videos.example.com/watch/abc123';
$embed = $container->get(oEmbedManager::class)->getEmbed($url);

if ($embed) {
    echo $embed->getHtml();
}

// Or use WordPress function
echo wp_oembed_get('https://videos.example.com/watch/abc123');
```

## ポッドキャストプロバイダーの例

### 1. ポッドキャストプロバイダーの作成

```php
#[oEmbedProvider(
    pattern: 'https://podcasts.example.com/episode/*',
    endpoint: 'https://podcasts.example.com/oembed',
    format: 'json'
)]
class PodcastProvider extends AbstractoEmbedProvider
{
    public function __construct(
        private HttpClientInterface $http
    ) {}

    public function fetch(string $url, array $args = []): ?EmbedResponse
    {
        // Get episode data from API
        $response = $this->http->get($this->endpoint, [
            'url' => $url,
            'format' => 'json'
        ]);

        if (!$response->isSuccessful()) {
            return null;
        }

        $data = $response->json();

        // Create audio player HTML
        $html = $this->createAudioPlayer($data);

        // Build response
        $embed = new EmbedResponse();
        $embed->setType('rich');
        $embed->setHtml($html);
        $embed->setTitle($data['title']);
        $embed->setAuthorName($data['show_name']);
        $embed->setProviderName('Example Podcasts');
        $embed->setProviderUrl('https://podcasts.example.com');
        $embed->setThumbnailUrl($data['cover_art']);

        return $embed;
    }

    private function createAudioPlayer(array $data): string
    {
        return sprintf(
            '<div class="podcast-embed">
                <img src="%s" alt="%s" class="podcast-cover">
                <div class="podcast-info">
                    <h3>%s</h3>
                    <p class="podcast-show">%s</p>
                    <audio controls src="%s">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>',
            esc_url($data['cover_art']),
            esc_attr($data['title']),
            esc_html($data['title']),
            esc_html($data['show_name']),
            esc_url($data['audio_url'])
        );
    }
}
```

### 2. プレイヤーのスタイリング

```css
.podcast-embed {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.podcast-cover {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
}

.podcast-info {
    flex: 1;
}

.podcast-info h3 {
    margin: 0 0 0.5rem;
}

.podcast-show {
    color: #666;
    margin: 0 0 1rem;
}

.podcast-info audio {
    width: 100%;
}
```

## プロバイダーのテスト

### 1. カスタムプロバイダーのテスト

```php
use PHPUnit\Framework\TestCase;

class VideoProviderTest extends TestCase
{
    private VideoProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new VideoProvider();
    }

    public function testFetchValidVideo(): void
    {
        $url = 'https://videos.example.com/watch/abc123';
        $embed = $this->provider->fetch($url);

        $this->assertNotNull($embed);
        $this->assertEquals('video', $embed->getType());
        $this->assertStringContainsString('abc123', $embed->getHtml());
    }

    public function testFetchInvalidUrl(): void
    {
        $url = 'https://videos.example.com/invalid';
        $embed = $this->provider->fetch($url);

        $this->assertNull($embed);
    }
}
```

## 次のステップ

基本を学んだので、次に進みましょう：

1. **[oEmbed 概要を探索](overview.md)** - 高度な機能とパターン
2. **[コンポーネント概要](../README.md)** - 他の WpPack コンポーネントを探索
3. **[WordPress 統合](../../guides/wordpress-integration.md)** - WordPress パターン

## クイックリファレンス

### プロバイダーアトリビュート

```php
#[oEmbedProvider(
    pattern: 'https://example.com/videos/*',
    endpoint: 'https://example.com/oembed',
    format: 'json|xml',
    ssl: true
)]
```

### EmbedResponse メソッド

```php
$embed->setType('video|photo|link|rich');
$embed->setHtml($html);
$embed->setWidth($width);
$embed->setHeight($height);
$embed->setTitle($title);
$embed->setAuthorName($author);
$embed->setProviderName($provider);
$embed->setProviderUrl($url);
$embed->setThumbnailUrl($thumbnail);
```

### Manager メソッド

```php
// Get embed
$embed = $oEmbed->getEmbed($url, $args);

// Register provider
$oEmbed->addProvider($provider);

```

# OEmbed コンポーネント Named Hook アトリビュート

OEmbed コンポーネントは、WordPress oEmbed 機能のための Named Hook アトリビュートを提供します。これらのアトリビュートにより、型安全性とモダンな PHP 機能を活用して、埋め込みコンテンツの管理、oEmbed プロバイダーのカスタマイズ、埋め込み動作の制御が可能になります。

## OEmbed プロバイダーフック

### #[OembedProvidersFilter]

**WordPress フック:** `oembed_providers`
**使用場面:** oEmbed プロバイダーの追加または変更。

```php
use WpPack\Component\OEmbed\Attribute\OembedProvidersFilter;
use WpPack\Component\OEmbed\OEmbedRegistry;

class OEmbedProviderManager
{
    private OEmbedRegistry $registry;

    public function __construct(OEmbedRegistry $registry)
    {
        $this->registry = $registry;
    }

    #[OembedProvidersFilter(priority?: int = 10)]
    public function registerCustomProviders(array $providers): array
    {
        // Add custom video platform
        $providers['#https?://(?:www\.)?customvideo\.com/watch/([^/]+)#i'] = [
            'https://customvideo.com/oembed?url={url}',
            true // Supports discovery
        ];

        // Add internal documentation provider
        $providers['#https?://docs\.example\.com/([^/]+)/([^/]+)#i'] = [
            'https://docs.example.com/api/oembed',
            false
        ];

        // Add code sharing platform
        $providers['#https?://(?:www\.)?codeshare\.io/([a-zA-Z0-9]+)#i'] = [
            'https://codeshare.io/api/oembed',
            true
        ];

        // Modify existing provider
        if (isset($providers['#https?://(?:www\.)?youtube\.com/watch#i'])) {
            // Use privacy-enhanced mode
            $providers['#https?://(?:www\.)?youtube\.com/watch#i'][0] =
                str_replace('youtube.com', 'youtube-nocookie.com', $providers['#https?://(?:www\.)?youtube\.com/watch#i'][0]);
        }

        return $providers;
    }
}
```

### #[OembedFetchUrlFilter]

**WordPress フック:** `oembed_fetch_url`
**使用場面:** フェッチ前の oEmbed リクエスト URL の変更。

```php
use WpPack\Component\OEmbed\Attribute\OembedFetchUrlFilter;

class OEmbedRequestCustomizer
{
    #[OembedFetchUrlFilter(priority?: int = 10)]
    public function customizeOembedUrl(string $provider, string $url, array $args): string
    {
        // Add API key for authenticated requests
        if (str_contains($provider, 'customvideo.com')) {
            $api_key = get_option('customvideo_api_key');
            $provider = add_query_arg('api_key', $api_key, $provider);
        }

        // Add default parameters
        $defaults = [
            'maxwidth' => 800,
            'maxheight' => 600,
            'format' => 'json',
            'scheme' => 'https',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($args[$key])) {
                $provider = add_query_arg($key, $value, $provider);
            }
        }

        // Add custom styling parameters
        if ($this->shouldAddCustomStyling($url)) {
            $provider = add_query_arg([
                'theme' => 'dark',
                'color' => 'blue',
                'autoplay' => '0',
                'controls' => '1',
            ], $provider);
        }

        return $provider;
    }

    private function shouldAddCustomStyling(string $url): bool
    {
        return str_contains($url, 'youtube.com') || str_contains($url, 'vimeo.com');
    }
}
```

## OEmbed レスポンスフック

### #[OembedResultFilter]

**WordPress フック:** `oembed_result`
**使用場面:** キャッシュ前の oEmbed HTML の変更。

```php
use WpPack\Component\OEmbed\Attribute\OembedResultFilter;

class OEmbedResponseProcessor
{
    #[OembedResultFilter(priority?: int = 10)]
    public function processOembedResult($html, string $url, array $args, int $post_id)
    {
        if (!$html) {
            return $html;
        }

        // Wrap in responsive container
        if ($this->isVideoEmbed($html)) {
            $html = $this->makeResponsive($html);
        }

        // Add lazy loading
        $html = $this->addLazyLoading($html);

        // Add privacy notice for external content
        if ($this->requiresPrivacyNotice($url)) {
            $html = $this->addPrivacyNotice($html, $url);
        }

        // Add custom attributes
        $html = $this->addCustomAttributes($html, $url);

        return $html;
    }

    private function makeResponsive(string $html): string
    {
        return sprintf(
            '<div class="wppack-embed-responsive wppack-embed-responsive-16by9">%s</div>',
            preg_replace(
                '/(width|height)="\d+"/i',
                '',
                $html
            )
        );
    }

    private function addLazyLoading(string $html): string
    {
        // Add loading="lazy" to iframes
        if (preg_match('/<iframe[^>]*>/i', $html, $matches)) {
            $iframe = $matches[0];
            if (!str_contains($iframe, 'loading=')) {
                $new_iframe = str_replace('<iframe', '<iframe loading="lazy"', $iframe);
                $html = str_replace($iframe, $new_iframe, $html);
            }
        }

        return $html;
    }

    private function requiresPrivacyNotice(string $url): bool
    {
        $privacy_required = ['youtube.com', 'facebook.com', 'instagram.com', 'twitter.com'];

        foreach ($privacy_required as $domain) {
            if (str_contains($url, $domain)) {
                return true;
            }
        }

        return false;
    }
}
```

### #[EmbedOembedHtmlFilter]

**WordPress フック:** `embed_oembed_html`
**使用場面:** 表示前のキャッシュされた oEmbed HTML のフィルタリング。

```php
use WpPack\Component\OEmbed\Attribute\EmbedOembedHtmlFilter;

class OEmbedDisplayFilter
{
    #[EmbedOembedHtmlFilter(priority?: int = 10)]
    public function filterEmbedHtml(string $html, string $url, array $attr, int $post_id): string
    {
        // Apply AMP compatibility
        if (function_exists('is_amp_endpoint') && is_amp_endpoint()) {
            $html = $this->convertToAmp($html);
        }

        // Add schema.org markup
        $html = $this->addSchemaMarkup($html, $url);

        // Add custom CSS classes based on provider
        $provider = $this->detectProvider($url);
        if ($provider) {
            $html = $this->addProviderClass($html, $provider);
        }

        // Add click tracking
        if (get_option('wppack_track_embeds')) {
            $html = $this->addClickTracking($html, $url, $post_id);
        }

        return $html;
    }

    private function convertToAmp(string $html): string
    {
        // Convert iframe to amp-iframe
        $html = preg_replace(
            '/<iframe([^>]*)>(.*?)<\/iframe>/is',
            '<amp-iframe$1 layout="responsive" sandbox="allow-scripts allow-same-origin">$2</amp-iframe>',
            $html
        );

        return $html;
    }

    private function addSchemaMarkup(string $html, string $url): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'embedUrl' => $url,
            'uploadDate' => get_the_date('c'),
        ];

        $script = sprintf(
            '<script type="application/ld+json">%s</script>',
            json_encode($schema)
        );

        return $html . $script;
    }
}
```

## OEmbed ディスカバリーフック

### #[OembedDiscoveryLinksFilter]

**WordPress フック:** `oembed_discovery_links`
**使用場面:** サイトヘッダー内の oEmbed ディスカバリーリンクの変更。

```php
use WpPack\Component\OEmbed\Attribute\OembedDiscoveryLinksFilter;

class OEmbedDiscoveryManager
{
    #[OembedDiscoveryLinksFilter(priority?: int = 10)]
    public function customizeDiscoveryLinks(string $output): string
    {
        // Add custom oEmbed endpoint for specific post types
        if (is_singular('product')) {
            $output .= sprintf(
                '<link rel="alternate" type="application/json+oembed" href="%s" title="%s" />' . "\n",
                esc_url($this->getProductOembedUrl('json')),
                esc_attr(get_the_title())
            );

            $output .= sprintf(
                '<link rel="alternate" type="text/xml+oembed" href="%s" title="%s" />' . "\n",
                esc_url($this->getProductOembedUrl('xml')),
                esc_attr(get_the_title())
            );
        }

        return $output;
    }

    private function getProductOembedUrl(string $format = 'json'): string
    {
        return add_query_arg([
            'url' => urlencode(get_permalink()),
            'format' => $format,
        ], home_url('/wp-json/oembed/1.0/embed-product'));
    }
}
```

## 実践的な例

### 完全な OEmbed システム

```php
use WpPack\Component\Hook\Attribute\InitAction;
use WpPack\Component\OEmbed\Attribute\OembedProvidersFilter;
use WpPack\Component\OEmbed\Attribute\OembedResultFilter;
use WpPack\Component\OEmbed\OEmbedService;
use WpPack\Component\OEmbed\OEmbedCache;

class WpPackOEmbedSystem
{
    private OEmbedService $service;
    private OEmbedCache $cache;
    private Logger $logger;

    public function __construct(
        OEmbedService $service,
        OEmbedCache $cache,
        Logger $logger
    ) {
        $this->service = $service;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    #[InitAction]
    public function initializeOEmbed(): void
    {
        // Register custom embed handlers
        $this->registerCustomHandlers();

        // Set up caching strategy
        $this->configureCaching();

        // Register REST endpoints
        $this->registerEndpoints();
    }

    #[OembedProvidersFilter(priority?: int = 10)]
    public function registerProviders(array $providers): array
    {
        // Add company video platform
        $providers['#https?://video\.company\.com/([0-9]+)#i'] = [
            'https://video.company.com/api/oembed?format=json',
            true
        ];

        // Add documentation embeds
        $providers['#https?://docs\.company\.com/([^/]+)/([^/]+)#i'] = [
            'https://docs.company.com/api/oembed',
            false
        ];

        // Add code repository embeds
        $providers['#https?://code\.company\.com/([^/]+)/([^/]+)#i'] = [
            'https://code.company.com/api/oembed',
            true
        ];

        return $providers;
    }

    #[OembedResultFilter(priority?: int = 10)]
    public function enhanceOembedResult($html, string $url, array $args, int $post_id)
    {
        if (!$html) {
            // Try custom handler for failed embeds
            $html = $this->handleFailedEmbed($url, $args);
        }

        if (!$html) {
            return $html;
        }

        // Cache enhanced version
        $cache_key = $this->cache->generateKey($url, $args);
        $cached = $this->cache->get($cache_key);

        if ($cached) {
            return $cached;
        }

        // Enhance embed
        $enhanced = $this->enhanceEmbed($html, $url, $args);

        // Cache enhanced result
        $this->cache->set($cache_key, $enhanced, HOUR_IN_SECONDS);

        return $enhanced;
    }

    private function enhanceEmbed(string $html, string $url, array $args): string
    {
        // Add wrapper with metadata
        $wrapper = '<div class="wppack-oembed" data-url="' . esc_attr($url) . '"';

        // Add provider info
        $provider = $this->detectProvider($url);
        if ($provider) {
            $wrapper .= ' data-provider="' . esc_attr($provider) . '"';
        }

        // Add responsive wrapper for videos
        if ($this->isVideoEmbed($html)) {
            $wrapper .= ' data-type="video">';
            $html = $this->makeResponsive($html);
        } else {
            $wrapper .= ' data-type="rich">';
        }

        // Add lazy loading
        $html = $this->addLazyLoading($html);

        // Add privacy features
        if ($this->requiresConsent($url)) {
            $html = $this->addConsentLayer($html, $url);
        }

        return $wrapper . $html . '</div>';
    }

    private function addConsentLayer(string $html, string $url): string
    {
        $provider = $this->detectProvider($url);

        $consent_html = sprintf(
            '<div class="wppack-oembed-consent" data-embed-url="%s">
                <div class="consent-message">
                    <p>%s</p>
                    <button class="consent-button" data-action="accept">%s</button>
                    <button class="consent-button" data-action="deny">%s</button>
                </div>
                <div class="embed-container" style="display:none;">%s</div>
            </div>',
            esc_attr($url),
            sprintf(
                __('This content is hosted by %s. By loading it, you accept their privacy policy.', 'wppack'),
                esc_html($provider)
            ),
            __('Accept & Load', 'wppack'),
            __('Deny', 'wppack'),
            $html
        );

        return $consent_html;
    }
}
```

## Hook アトリビュートリファレンス

### 利用可能な Hook アトリビュート

```php
// Provider Management
#[OembedProvidersFilter(priority?: int = 10)]        // oEmbed プロバイダーの追加/変更

// Request Handling
#[OembedFetchUrlFilter(priority?: int = 10)]         // oEmbed リクエスト URL の変更
#[PreOembedResultFilter(priority?: int = 10)]        // oEmbed 結果の前処理
#[OembedTtlFilter(priority?: int = 10)]              // キャッシュ期間の設定

// Response Processing
#[OembedResultFilter(priority?: int = 10)]           // oEmbed HTML の処理
#[EmbedOembedHtmlFilter(priority?: int = 10)]        // キャッシュされた HTML のフィルタリング
#[OembedDataparseFilter(priority?: int = 10)]        // oEmbed データの解析

// Discovery
#[OembedDiscoveryLinksFilter(priority?: int = 10)]   // ディスカバリーリンクの変更
#[OembedResponseDataFilter(priority?: int = 10)]     // レスポンスデータのフィルタリング

// Display
#[EmbedDefaultsFilter(priority?: int = 10)]          // デフォルトサイズの設定
#[EmbedHandlersFilter(priority?: int = 10)]          // 埋め込みハンドラーの登録
```

## 従来の WordPress vs WpPack

### Before（従来の WordPress）
```php
// Traditional oEmbed customization
add_filter('oembed_providers', function($providers) {
    $providers['#https?://example\.com/video/([0-9]+)#i'] = array(
        'https://example.com/oembed',
        true
    );
    return $providers;
});

add_filter('embed_oembed_html', function($html, $url) {
    if (strpos($url, 'youtube.com') !== false) {
        $html = '<div class="video-wrapper">' . $html . '</div>';
    }
    return $html;
}, 10, 2);
```

### After（WpPack）
```php
use WpPack\Component\OEmbed\Attribute\OembedProvidersFilter;
use WpPack\Component\OEmbed\Attribute\EmbedOembedHtmlFilter;
use WpPack\Component\OEmbed\OEmbedService;

class VideoEmbedManager
{
    private OEmbedService $oembed;

    public function __construct(OEmbedService $oembed)
    {
        $this->oembed = $oembed;
    }

    #[OembedProvidersFilter(priority?: int = 10)]
    public function registerProviders(array $providers): array
    {
        return $this->oembed->addProvider(
            $providers,
            '#https?://example\.com/video/([0-9]+)#i',
            'https://example.com/oembed'
        );
    }

    #[EmbedOembedHtmlFilter(priority?: int = 10)]
    public function enhanceVideoEmbed(string $html, string $url): string
    {
        if ($this->oembed->isVideo($url)) {
            return $this->oembed->wrapResponsive($html);
        }
        return $html;
    }
}
```

### メリット
- **型安全性** - パラメータと戻り値が型付けされている
- **サービス統合** - OEmbed サービスの注入が容易
- **テスト容易性** - メソッドをユニットテスト可能
- **整理** - 関連機能がグループ化されている
- **再利用性** - ロジックをプロジェクト間で共有可能

## ベストプラクティス

1. **パフォーマンス**
   - oEmbed の結果を適切にキャッシュする
   - 埋め込みに遅延読み込みを使用する
   - API リクエストを最小限にする
   - 可能な限りバッチ処理する

2. **セキュリティ**
   - プロバイダー URL を検証する
   - 埋め込み HTML をサニタイズする
   - 同意管理を実装する
   - HTTPS エンドポイントを使用する

3. **ユーザーエクスペリエンス**
   - 埋め込みをレスポンシブにする
   - フォールバックを提供する
   - 読み込み状態を表示する
   - エラーを適切に処理する

4. **プライバシー**
   - 同意レイヤーを実装する
   - プライバシー強化モードを使用する
   - データ共有を文書化する
   - オプトアウトオプションを提供する

## 次のステップ

- **[OEmbed コンポーネント概要](overview.md)** - oEmbed 機能について学ぶ
- **[OEmbed クイックスタート](quick-start.md)** - WpPack で埋め込みを実装する
- **[Hook コンポーネント](../hook/overview.md)** - WordPress フック管理全般
