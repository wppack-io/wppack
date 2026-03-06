# WordPress フィード API 仕様

## 1. 概要

WordPress のフィード API は、サイトのコンテンツを RSS 2.0、Atom、RSS 0.92、RDF/RSS 1.0 形式で配信するためのサブシステムです。フィードはリライトルールによって URL に紐付けられ、テンプレートシステムを通じて XML として出力されます。

WordPress がサポートするフィード形式:

| フィード形式 | スラッグ | URL 例 | テンプレートファイル |
|---|---|---|---|
| RSS 2.0 | `rss2` | `/feed/` または `/feed/rss2/` | `wp-includes/feed-rss2.php` |
| Atom 1.0 | `atom` | `/feed/atom/` | `wp-includes/feed-atom.php` |
| RSS 0.92 | `rss` | `/feed/rss/` | `wp-includes/feed-rss.php` |
| RDF/RSS 1.0 | `rdf` | `/feed/rdf/` | `wp-includes/feed-rdf.php` |

デフォルトのフィード形式は `rss2` で、`default_feed` オプションで変更可能です。

### フィードの種類

| フィード | URL パターン | 説明 |
|---|---|---|
| メインフィード | `/feed/` | 最新の投稿 |
| コメントフィード | `/comments/feed/` | 最新のコメント |
| カテゴリフィード | `/category/{slug}/feed/` | 特定カテゴリの投稿 |
| タグフィード | `/tag/{slug}/feed/` | 特定タグの投稿 |
| 投稿者フィード | `/author/{name}/feed/` | 特定著者の投稿 |
| 検索フィード | `/?s={query}&feed=rss2` | 検索結果 |
| 投稿コメントフィード | `/{post-slug}/feed/` | 特定投稿のコメント |
| カスタムタクソノミーフィード | `/{taxonomy}/{term}/feed/` | カスタムタクソノミーの投稿 |
| カスタム投稿タイプフィード | `/{post-type}/feed/` | カスタム投稿タイプのアーカイブ |

## 2. データ構造

### フィード生成の主要データ

フィードは WordPress のメインクエリ（`WP_Query`）の結果をもとに生成されます。特別なデータ構造はなく、投稿ループとフィードテンプレートの組み合わせで XML を出力します。

### フィードキャッシュ

WordPress はフィードを `wp_options` テーブルの Transient として短期間キャッシュします:

| Transient キー | 説明 |
|---|---|
| `feed_{md5_hash}` | SimplePie で取得した外部フィードのキャッシュ |
| `feed_mod_{md5_hash}` | フィードの最終更新日時 |

キャッシュ期間のデフォルトは 12 時間です。

## 3. API リファレンス

### フィード出力関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `do_feed()` | `(): void` | 適切なフィードテンプレートをロード |
| `do_feed_rss2()` | `(bool $for_comments): void` | RSS 2.0 フィードを出力 |
| `do_feed_atom()` | `(bool $for_comments): void` | Atom フィードを出力 |
| `do_feed_rss()` | `(): void` | RSS 0.92 フィードを出力 |
| `do_feed_rdf()` | `(): void` | RDF/RSS 1.0 フィードを出力 |

### フィードテンプレートタグ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_bloginfo_rss()` | `(string $show = ''): string` | サイト情報をフィード用にエスケープして取得 |
| `bloginfo_rss()` | `(string $show = ''): void` | サイト情報をフィード用に表示 |
| `the_title_rss()` | `(): void` | 投稿タイトルをフィード用に表示 |
| `the_content_feed()` | `(string $feed_type = null): void` | 投稿内容をフィード用に表示 |
| `the_excerpt_rss()` | `(): void` | 抜粋をフィード用に表示 |
| `the_category_rss()` | `(string $type = null): void` | カテゴリをフィード用に表示 |
| `comment_author_rss()` | `(): void` | コメント著者名をフィード用に表示 |
| `comment_text_rss()` | `(): void` | コメント内容をフィード用に表示 |
| `comments_link_feed()` | `(): void` | コメントのパーマリンクを表示 |
| `get_the_date_rss()` | `(): string` | 投稿日を RFC 822 形式で取得 |

### フィード URL 取得

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_bloginfo()` | `('rss2_url')` | メインの RSS 2.0 フィード URL |
| `get_bloginfo()` | `('atom_url')` | メインの Atom フィード URL |
| `get_bloginfo()` | `('comments_rss2_url')` | コメントの RSS 2.0 フィード URL |
| `get_bloginfo()` | `('comments_atom_url')` | コメントの Atom フィード URL |
| `get_feed_link()` | `(string $feed = ''): string` | フィード URL を取得 |
| `get_post_comments_feed_link()` | `(int $post_id = 0, string $feed = ''): string` | 投稿コメントフィード URL |
| `get_category_feed_link()` | `(int $cat_id, string $feed = ''): string` | カテゴリフィード URL |
| `get_tag_feed_link()` | `(int $tag_id, string $feed = ''): string` | タグフィード URL |
| `get_author_feed_link()` | `(int $author_id, string $feed = ''): string` | 著者フィード URL |
| `get_search_feed_link()` | `(string $search_query = '', string $feed = ''): string` | 検索フィード URL |
| `get_post_type_archive_feed_link()` | `(string $post_type, string $feed = ''): string` | 投稿タイプアーカイブフィード URL |

### フィード Discovery（自動検出）

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `feed_links()` | `(array $args = []): void` | `<head>` にメインフィードの `<link>` タグを出力 |
| `feed_links_extra()` | `(array $args = []): void` | `<head>` に追加フィード（カテゴリ等）の `<link>` タグを出力 |

### 外部フィード取得 API（SimplePie）

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `fetch_feed()` | `(string\|string[] $url): SimplePie\|WP_Error` | 外部 RSS/Atom フィードを取得・パース |
| `wp_rss()` | `(string $url, int $num_items = -1): void` | RSS フィードを表示（非推奨、`fetch_feed()` を使用） |

### エスケープ関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `esc_xml()` | `(string $text): string` | XML 用エスケープ（WordPress 5.5+） |
| `wp_strip_all_tags()` | `(string $text, bool $remove_breaks = false): string` | 全 HTML タグを除去 |
| `ent2ncr()` | `(string $text): string` | HTML エンティティを数値参照に変換 |

### カスタムフィード登録

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_feed()` | `(string $feedname, callable $callback): string` | カスタムフィードを登録 |

```php
// カスタムフィードの登録例
add_feed('json', function () {
    header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    $posts = get_posts(['numberposts' => 10]);
    echo wp_json_encode($posts);
});
// URL: /feed/json/
```

## 4. 実行フロー

### フィードリクエスト処理フロー

```
GET /feed/ (or /feed/rss2/)
│
├── WordPress リライトルール解析
│   └── query_vars: ['feed' => 'rss2']
│
├── WP::parse_request()
│   └── $wp_query->is_feed = true
│
├── WP::handle_404()
│   └── フィードが 404 の場合は通常の 404 処理
│
├── template_redirect アクション
│   └── WordPress がフィードリクエストを検出
│
├── do_feed() 呼び出し
│   │
│   ├── $feed = get_query_var('feed')
│   │   └── 'feed' → 'rss2' に正規化（デフォルトフォーマット）
│   │
│   ├── is_comment_feed() の判定
│   │
│   └── do_action("do_feed_{$feed}", $is_comment_feed, $feed)
│       └── do_feed_rss2($is_comment_feed)
│
├── do_feed_rss2() の処理
│   │
│   ├── header('Content-Type: application/rss+xml; charset=...')
│   │
│   ├── コメントフィードの場合:
│   │   └── load_template(ABSPATH . WPINC . '/feed-rss2-comments.php')
│   │
│   └── 投稿フィードの場合:
│       └── load_template(ABSPATH . WPINC . '/feed-rss2.php')
│
└── feed-rss2.php テンプレート
    ├── <?xml version="1.0" ...?> ヘッダー
    ├── <rss version="2.0"> ルート要素
    ├── <channel> 要素（サイト情報）
    │   ├── <title>, <link>, <description>
    │   ├── <lastBuildDate>
    │   └── do_action('rss2_head')
    │
    ├── while (have_posts()): the_post();
    │   └── <item>
    │       ├── <title> → the_title_rss()
    │       ├── <link> → the_permalink_rss()
    │       ├── <dc:creator> → the_author()
    │       ├── <pubDate> → get_the_date_rss()
    │       ├── <category> → the_category_rss()
    │       ├── <guid>
    │       ├── <description> → the_excerpt_rss()
    │       ├── <content:encoded> → the_content_feed('rss2')
    │       └── do_action('rss2_item')
    │
    └── </channel></rss>
```

### 投稿数の制御

フィードに含まれる投稿数は `posts_per_rss` オプション（デフォルト: 10）で制御されます。管理画面の「設定 > 表示設定 > RSS/Atom フィードで表示する最新の投稿数」に対応します。

## 5. フック一覧

### Action フック

| フック名 | パラメータ | 説明 |
|---|---|---|
| `do_feed_{$feed}` | `bool $is_comment_feed, string $feed` | フィード出力（`do_feed_rss2`, `do_feed_atom` 等） |
| `rss_tag_pre` | `string $context` | RSS タグ出力前 |
| `rss2_head` | なし | RSS 2.0 `<channel>` 内の追加出力 |
| `rss2_item` | なし | RSS 2.0 `<item>` 内の追加出力 |
| `atom_head` | なし | Atom `<feed>` 内の追加出力 |
| `atom_entry` | なし | Atom `<entry>` 内の追加出力 |
| `rss_head` | なし | RSS 0.92 ヘッド内の追加出力 |
| `rss_item` | なし | RSS 0.92 アイテム内の追加出力 |
| `rdf_header` | なし | RDF ヘッダー内の追加出力 |
| `rdf_item` | なし | RDF アイテム内の追加出力 |
| `commentrss2_item` | `int $comment_id, int $comment_post_id` | コメント RSS 2.0 アイテム内の追加出力 |

### Filter フック

| フック名 | パラメータ | 戻り値 | 説明 |
|---|---|---|---|
| `default_feed` | `string $default_feed` | `string` | デフォルトフィード形式（`rss2`） |
| `feed_content_type` | `string $content_type, string $type` | `string` | フィードの Content-Type ヘッダー |
| `the_title_rss` | `string $title` | `string` | フィード内の投稿タイトル |
| `the_content_feed` | `string $content, string $feed_type` | `string` | フィード内の投稿コンテンツ |
| `the_excerpt_rss` | `string $excerpt` | `string` | フィード内の抜粋 |
| `comment_text_rss` | `string $comment_text` | `string` | フィード内のコメントテキスト |
| `the_category_rss` | `string $the_list, string $type` | `string` | フィード内のカテゴリ出力 |
| `rss_update_period` | `string $period` | `string` | RSS 更新間隔（`hourly` 等） |
| `rss_update_frequency` | `int $frequency` | `int` | RSS 更新頻度 |
| `self_link` | `string $url` | `string` | フィードの self リンク |
| `feed_links_show_posts_feed` | `bool $show` | `bool` | メインフィードリンクの表示 |
| `feed_links_show_comments_feed` | `bool $show` | `bool` | コメントフィードリンクの表示 |
| `feed_links_extra_show_post_comments_feed` | `bool $show` | `bool` | 投稿コメントフィードリンクの表示 |
| `post_comments_feed_link` | `string $link` | `string` | 投稿コメントフィード URL |
| `post_comments_feed_link_html` | `string $link_html, int $post_id, string $feed` | `string` | 投稿コメントフィードの HTML リンク |
| `wp_feed_cache_transient_lifetime` | `int $lifetime, string $url` | `int` | フィードキャッシュの有効期間（秒） |
| `wp_feed_options` | `SimplePie $feed, string $url` | ― | SimplePie オプションの設定 |
