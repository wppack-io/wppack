# WordPress oEmbed API 仕様

## 1. 概要

WordPress の oEmbed API は、URL を埋め込みコンテンツ（動画、ツイート、画像等）に自動変換するためのサブシステムです。WordPress は oEmbed の**コンシューマー**（他サイトのコンテンツを埋め込む）と**プロバイダー**（自サイトのコンテンツを他サイトに埋め込み可能にする）の両方の役割を担います。

主要コンポーネント:

| コンポーネント | クラス / ファイル | 説明 |
|---|---|---|
| oEmbed コンシューマー | `WP_oEmbed` | 外部 URL を oEmbed API で解決し、埋め込み HTML を取得 |
| Embed ショートコード | `WP_Embed` | `[embed]` ショートコードと URL 自動埋め込みを処理 |
| oEmbed プロバイダー | REST API エンドポイント | 自サイトの投稿を oEmbed 形式で提供 |
| oEmbed プロキシ | REST API エンドポイント | エディタ用の oEmbed プロキシ |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_embed` | `WP_Embed` | Embed 処理のグローバルインスタンス |

## 2. データ構造

### WP_oEmbed クラス

oEmbed プロトコルの実装クラスです。外部プロバイダーの管理と oEmbed リクエストの処理を行います。

```php
class WP_oEmbed {
    public $providers = [];  // プロバイダーの URL パターン => エンドポイントのマッピング
}
```

### `$providers` の構造

```php
$providers = [
    // [URL パターン, エンドポイント URL, 正規表現フラグ]
    '#https?://((m|www)\.)?youtube\.com/watch.*#i' => ['https://www.youtube.com/oembed', true],
    '#https?://youtu\.be/.*#i'                     => ['https://www.youtube.com/oembed', true],
    '#https?://(.+\.)?vimeo\.com/.*#i'             => ['https://vimeo.com/api/oembed.json', true],
    'https://twitter.com/*/status/*'               => ['https://publish.twitter.com/oembed', false],
    // ... その他のプロバイダー
];
```

- 第 3 要素が `true`: URL パターンは正規表現
- 第 3 要素が `false`: URL パターンはワイルドカード（`*` は任意文字列にマッチ）

### WP_Embed クラス

投稿コンテンツ内の URL や `[embed]` ショートコードを処理するクラスです。

```php
class WP_Embed {
    public $handlers       = [];     // 登録されたハンドラー
    public $post_ID;                 // 現在処理中の投稿ID
    public $usecache       = true;   // キャッシュを使用するか
    public $linkifunknown  = true;   // 未知のURLをリンクにするか
    public $last_attr      = [];     // 最後の属性
    public $last_url       = '';     // 最後の URL
    public $return_false_on_fail = false;
}
```

### oEmbed レスポンスの構造

oEmbed プロバイダーからの JSON レスポンス:

```php
// type: "video" の例
{
    "type": "video",
    "version": "1.0",
    "title": "Video Title",
    "author_name": "Author",
    "author_url": "https://example.com/author",
    "provider_name": "YouTube",
    "provider_url": "https://www.youtube.com/",
    "thumbnail_url": "https://i.ytimg.com/vi/.../hqdefault.jpg",
    "thumbnail_width": 480,
    "thumbnail_height": 360,
    "html": "<iframe ...></iframe>",
    "width": 480,
    "height": 270
}
```

| type | 説明 | 必須フィールド |
|---|---|---|
| `photo` | 静止画像 | `url`, `width`, `height` |
| `video` | 動画プレーヤー | `html`, `width`, `height` |
| `rich` | リッチコンテンツ | `html`, `width`, `height` |
| `link` | リンク | `title` のみ |

### oEmbed キャッシュ

埋め込みデータは `wp_postmeta` にキャッシュされます:

| メタキー | 説明 |
|---|---|
| `_oembed_{md5_hash}` | oEmbed レスポンスの HTML（またはフォールバック HTML） |
| `_oembed_time_{md5_hash}` | キャッシュのタイムスタンプ |

キャッシュのデフォルト有効期間は 1 日で、`oembed_ttl` フィルターで変更可能です。取得に失敗した URL は `{{unknown}}` としてキャッシュされます。

## 3. API リファレンス

### コンシューマー API

| 関数 / メソッド | シグネチャ | 説明 |
|---|---|---|
| `wp_oembed_get()` | `(string $url, array\|string $args = ''): string\|false` | URL の oEmbed HTML を取得 |
| `WP_oEmbed::get_html()` | `(string $url, array\|string $args = ''): string\|false` | oEmbed HTML を取得 |
| `WP_oEmbed::get_provider()` | `(string $url, array $args = []): string\|false` | URL に対応するプロバイダーエンドポイントを返す |
| `WP_oEmbed::fetch()` | `(string $provider, string $url, array\|string $args = ''): object\|false` | プロバイダーから oEmbed データを取得 |
| `WP_oEmbed::data2html()` | `(object $data, string $url): string\|false` | oEmbed データを HTML に変換 |
| `WP_oEmbed::discover()` | `(string $url): string\|false` | URL のページから oEmbed エンドポイントを自動検出 |

### プロバイダー管理 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_oembed_add_provider()` | `(string $format, string $provider, bool $regex = false): void` | oEmbed プロバイダーを追加 |
| `wp_oembed_remove_provider()` | `(string $format): bool` | oEmbed プロバイダーを削除 |

### Embed ハンドラー API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_embed_register_handler()` | `(string $id, string $regex, callable $callback, int $priority = 10): void` | カスタム Embed ハンドラーを登録 |
| `wp_embed_unregister_handler()` | `(string $id, int $priority = 10): void` | Embed ハンドラーを解除 |

### WP_Embed メソッド

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `autoembed()` | `(string $content): string` | コンテンツ内の URL を自動埋め込み |
| `run_shortcode()` | `(string $content): string` | `[embed]` ショートコードを処理 |
| `shortcode()` | `(array $attr, string $url = ''): string\|false` | ショートコードのコールバック |
| `cache_oembed()` | `(int $post_id): void` | 投稿のoEmbedキャッシュを更新 |
| `delete_oembed_caches()` | `(int $post_id): void` | 投稿のoEmbedキャッシュを削除 |
| `maybe_make_link()` | `(string $url): string\|false` | URLを `<a>` タグにフォールバック |

### プロバイダー側 API（自サイトを oEmbed で公開）

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_oembed_response_data()` | `(int\|WP_Post $post, int $width): array\|false` | 投稿の oEmbed レスポンスデータを生成 |
| `get_oembed_endpoint_url()` | `(string $permalink = '', string $format = 'json'): string` | oEmbed エンドポイント URL を取得 |
| `get_post_embed_url()` | `(int\|WP_Post $post = null): string\|false` | 投稿の埋め込み iframe URL |
| `get_post_embed_html()` | `(int $width, int $height, int\|WP_Post $post = null): string\|false` | 投稿の埋め込み iframe HTML |
| `wp_oembed_add_discovery_links()` | `(): void` | `<head>` に oEmbed discovery リンクを出力 |

### REST API エンドポイント

| エンドポイント | メソッド | 説明 |
|---|---|---|
| `/oembed/1.0/embed` | GET | 自サイトの投稿を oEmbed 形式で返す |
| `/oembed/1.0/proxy` | GET | 外部 URL の oEmbed データをプロキシ取得（認証必要） |

## 4. 実行フロー

### URL 自動埋め込みフロー

```
the_content フィルター実行
│
├── WP_Embed::autoembed($content)
│   │
│   ├── 正規表現でコンテンツ内の独立行 URL を検出
│   │   └── パターン: 行の先頭から末尾まで URL のみの行
│   │
│   └── 各 URL に対して WP_Embed::shortcode([], $url) を呼び出し
│
├── WP_Embed::shortcode($attr, $url)
│   │
│   ├── キャッシュチェック
│   │   ├── $post_id が有効な場合:
│   │   │   ├── $cache_key = '_oembed_' . md5($url . serialize($rawattr))
│   │   │   └── get_post_meta($post_id, $cache_key, true)
│   │   │       ├── キャッシュヒット: キャッシュされた HTML を返す
│   │   │       └── '{{unknown}}' の場合: $this->maybe_make_link($url) を返す
│   │   │
│   │   └── $post_id がない場合:
│   │       └── キャッシュなし、毎回取得
│   │
│   ├── カスタムハンドラーチェック
│   │   └── $this->handlers の正規表現とマッチするか確認
│   │       └── マッチした場合: ハンドラーのコールバックを呼び出して返す
│   │
│   ├── wp_oembed_get($url, $attr)
│   │   │
│   │   └── WP_oEmbed::get_html($url, $args)
│   │       │
│   │       ├── WP_oEmbed::get_provider($url)
│   │       │   ├── $this->providers をループ
│   │       │   │   ├── 正規表現パターン: preg_match()
│   │       │   │   └── ワイルドカード: パターンを正規表現に変換してマッチ
│   │       │   │
│   │       │   ├── apply_filters('oembed_providers', $providers)
│   │       │   │
│   │       │   └── プロバイダーが見つからない場合:
│   │       │       └── discover が有効なら WP_oEmbed::discover($url)
│   │       │
│   │       ├── WP_oEmbed::fetch($provider, $url, $args)
│   │       │   ├── エンドポイント URL を構築
│   │       │   │   └── ?url={$url}&maxwidth={$maxwidth}&maxheight={$maxheight}&format=json
│   │       │   ├── apply_filters('oembed_fetch_url', $oembed_url, $url, $args)
│   │       │   ├── wp_safe_remote_get($oembed_url) で HTTP リクエスト
│   │       │   └── JSON/XML レスポンスをパース
│   │       │
│   │       └── WP_oEmbed::data2html($data, $url)
│   │           ├── type に応じた HTML 生成:
│   │           │   ├── photo: <a href><img src></a>
│   │           │   ├── video/rich: $data->html をサニタイズ
│   │           │   └── link: タイトルのみ
│   │           └── apply_filters('oembed_dataparse', $return, $data, $url)
│   │
│   ├── apply_filters('embed_oembed_html', $html, $url, $attr, $post_id)
│   │
│   ├── キャッシュに保存
│   │   └── update_post_meta($post_id, $cache_key, $html)
│   │
│   └── HTML を返す（または失敗時は $this->maybe_make_link($url)）
```

### oEmbed Discovery（自動検出）フロー

```
WP_oEmbed::discover($url)
│
├── wp_safe_remote_get($url) で HTML ページを取得
│
├── HTML から <link> タグを探索
│   ├── <link rel="alternate" type="application/json+oembed" href="..." />
│   └── <link rel="alternate" type="text/xml+oembed" href="..." />
│
├── apply_filters('oembed_discovery_links', $links)
│
└── 見つかったエンドポイント URL を返す
```

## 5. 組み込み oEmbed プロバイダー

WordPress がデフォルトでサポートする主要プロバイダー:

| プロバイダー | URL パターン |
|---|---|
| YouTube | `youtube.com/watch`, `youtu.be/*` |
| Vimeo | `vimeo.com/*` |
| Twitter/X | `twitter.com/*/status/*`, `x.com/*/status/*` |
| Instagram | `instagram.com/p/*`, `instagram.com/reel/*` |
| Spotify | `open.spotify.com/*` |
| SoundCloud | `soundcloud.com/*` |
| Flickr | `flickr.com/photos/*` |
| TikTok | `tiktok.com/*/video/*` |
| WordPress.tv | `wordpress.tv/*` |
| Reddit | `reddit.com/r/*/comments/*` |
| SlideShare | `slideshare.net/*` |
| TED | `ted.com/talks/*` |
| Amazon Kindle | `amazon.com/*/dp/*` |

完全なリストは `WP_oEmbed::__construct()` で定義されています。

## 6. 埋め込みテンプレート（プロバイダー側）

WordPress は自サイトの投稿を他サイトに埋め込み可能にするテンプレートを提供します:

| テンプレートファイル | 説明 |
|---|---|
| `wp-includes/theme-compat/embed.php` | デフォルトの埋め込みテンプレート |
| `embed.php`（テーマ内） | テーマによるオーバーライド |
| `embed-{post-type}.php` | 投稿タイプ別テンプレート |

埋め込み iframe の URL パターン: `/{post-slug}/embed/`

## 7. セキュリティ

### URL サニタイゼーション

- `wp_safe_remote_get()` による安全な HTTP リクエスト
- `wp_filter_oembed_result()` による iframe の `sandbox` 属性追加
- 許可されたタグのみ通過（`wp_kses` ベース）
- プロバイダーホワイトリスト方式（未登録プロバイダーは Discovery が必要）

### 埋め込み iframe のサンドボックス

```html
<iframe sandbox="allow-scripts" security="restricted"
    src="https://example.com/post-slug/embed/"
    width="600" height="338"
    title="Post Title"
    frameborder="0"
    marginwidth="0" marginheight="0"
    scrolling="no"
    class="wp-embedded-content">
</iframe>
```

## 8. フック一覧

### Action フック

| フック名 | パラメータ | 説明 |
|---|---|---|
| `embed_head` | なし | 埋め込みテンプレートの `<head>` 内 |
| `embed_footer` | なし | 埋め込みテンプレートのフッター |
| `embed_content` | なし | 埋め込みテンプレートのコンテンツ |
| `embed_content_meta` | なし | 埋め込みテンプレートのメタ情報 |

### Filter フック

| フック名 | パラメータ | 戻り値 | 説明 |
|---|---|---|---|
| `oembed_providers` | `array $providers` | `array` | プロバイダー一覧 |
| `oembed_fetch_url` | `string $provider_url, string $url, array $args` | `string` | oEmbed リクエスト URL |
| `oembed_result` | `string $data, string $url, array $args` | `string` | oEmbed 取得結果 |
| `oembed_dataparse` | `string $return, object $data, string $url` | `string` | oEmbed データから生成した HTML |
| `embed_oembed_html` | `string $cache, string $url, array $attr, int $post_id` | `string` | 最終的な埋め込み HTML |
| `embed_oembed_discover` | `bool $discover` | `bool` | oEmbed Discovery の有効/無効 |
| `oembed_ttl` | `int $time, string $url, array $attr, int $post_id` | `int` | キャッシュの有効期間（秒） |
| `oembed_remote_get_args` | `array $args, string $url` | `array` | リモート取得の HTTP 引数 |
| `embed_defaults` | `array $defaults, string $url` | `array` | デフォルトの width/height |
| `embed_handler_html` | `string $return, string $url, array $attr` | `string` | カスタムハンドラーの HTML |
| `oembed_response_data` | `array $data, WP_Post $post, int $width, int $height` | `array` | プロバイダー側のレスポンスデータ |
| `oembed_request_post_id` | `int $post_id, string $url` | `int` | リクエストされた投稿ID |
| `embed_html` | `string $output, WP_Post $post, int $width, int $height` | `string` | 埋め込み iframe の HTML |
| `embed_thumbnail_id` | `int\|false $thumbnail_id, WP_Post $post` | `int\|false` | 埋め込みのサムネイルID |
| `embed_thumbnail_image_size` | `string\|int[] $image_size, int $thumbnail_id` | `string\|int[]` | サムネイル画像サイズ |
| `embed_site_title_html` | `string $site_title, array $args` | `string` | 埋め込みのサイトタイトル HTML |
| `wp_filter_oembed_result` | `string $result, object $data, string $url` | `string` | サニタイズ後の oEmbed 結果 |
| `oembed_iframe_title_attribute` | `string $title, WP_Post $post` | `string` | iframe の title 属性 |
