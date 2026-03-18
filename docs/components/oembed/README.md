# OEmbed コンポーネント

**パッケージ:** `wppack/oembed`
**名前空間:** `WpPack\Component\OEmbed\`
**レイヤー:** Application

WordPress oEmbed API をモダンな PHP でラップし、プロバイダー自動登録と oEmbed 関連の Named Hook アトリビュートを提供するコンポーネントです。

## インストール

```bash
composer require wppack/oembed
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// Traditional WordPress - procedural and scattered
add_action('init', function() {
    wp_oembed_add_provider(
        'https://example.com/videos/*',
        'https://example.com/oembed',
        false
    );
    wp_oembed_add_provider(
        '#https?://custom\.site/.*#i',
        'https://custom.site/oembed',
        true
    );
});

add_filter('oembed_result', function ($html, $url, $args) {
    if (str_contains($url, 'youtube.com')) {
        $html = '<div class="video-wrapper">' . $html . '</div>';
    }
    return $html;
}, 10, 3);
```

### After（WpPack）

```php
use WpPack\Component\OEmbed\OEmbedProviderDefinition;
use WpPack\Component\OEmbed\OEmbedProviderInterface;
use WpPack\Component\Hook\Attribute\OEmbed\Filter\OembedResultFilter;

class MyOEmbedProviders implements OEmbedProviderInterface
{
    public function getProviders(): array
    {
        return [
            new OEmbedProviderDefinition('https://example.com/videos/*', 'https://example.com/oembed'),
            new OEmbedProviderDefinition('#https?://custom\.site/.*#i', 'https://custom.site/oembed', regex: true),
        ];
    }
}

// DI コンテナで auto-tag → OEmbedProviderRegistry に自動収集

final class VideoEmbedCustomizer
{
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

## 主要クラス

| クラス | 説明 |
|--------|------|
| `OEmbedProviderDefinition` | oEmbed プロバイダー定義（値オブジェクト） |
| `OEmbedProviderInterface` | プロバイダーを提供するインターフェース |
| `OEmbedProviderRegistry` | プロバイダーの登録・管理 |

## OEmbedProviderInterface

DI コンテナで auto-tag し、`OEmbedProviderRegistry` に自動収集されるパターンです。

```php
use WpPack\Component\OEmbed\OEmbedProviderDefinition;
use WpPack\Component\OEmbed\OEmbedProviderInterface;

class CustomProviders implements OEmbedProviderInterface
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

## OEmbedProviderRegistry

プロバイダーの登録・管理を行うレジストリです。

### プロバイダー経由の一括登録

`addProvider()` でプロバイダーを収集し、`register()` で `init` のタイミングに一括登録します。

```php
use WpPack\Component\OEmbed\OEmbedProviderRegistry;

$registry = new OEmbedProviderRegistry();
$registry->addProvider(new CustomProviders());
$registry->register(); // wp_oembed_add_provider() が呼ばれる
```

### 直接登録

```php
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\OEmbed\OEmbedProviderRegistry;

class OEmbedManager
{
    public function __construct(
        private readonly OEmbedProviderRegistry $registry,
    ) {}

    #[InitAction]
    public function registerProviders(): void
    {
        $this->registry->addDefinition(
            'https://example.com/*',
            'https://example.com/oembed',
        );

        $this->registry->addDefinition(
            '#https?://custom\.site/.*#i',
            'https://custom.site/oembed',
            regex: true,
        );
    }
}
```

### API リファレンス

```php
$registry->addProvider(OEmbedProviderInterface $provider): void
$registry->register(): void                          // プロバイダーの定義を一括登録
$registry->addDefinition(string $format, string $endpoint, bool $regex = false): void
$registry->removeProvider(string $format): void
$registry->hasProvider(string $format): bool
$registry->getRegisteredProviders(): array           // list<OEmbedProviderDefinition>
```

## OEmbedProviderDefinition

oEmbed プロバイダーの定義を表す値オブジェクトです。

```php
use WpPack\Component\OEmbed\OEmbedProviderDefinition;

// ワイルドカードパターン
$wildcard = new OEmbedProviderDefinition(
    format: 'https://example.com/*',
    endpoint: 'https://example.com/oembed',
);

// 正規表現パターン
$regex = new OEmbedProviderDefinition(
    format: '#https?://custom\.site/.*#i',
    endpoint: 'https://custom.site/oembed',
    regex: true,
);
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/oembed.md) を参照してください。
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
