# Feed コンポーネント

**パッケージ:** `wppack/feed`
**名前空間:** `WpPack\Component\Feed\`
**レイヤー:** Application

WordPress のフィード機能（RSS 2.0、Atom）をアトリビュートベースで管理するコンポーネントです。カスタムフィードの登録（`add_feed()`）、フィードコンテンツの変更、フィードクエリの制御を型安全に行えます。

## インストール

```bash
composer require wppack/feed
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('init', function () {
    add_feed('products', function () {
        load_template(ABSPATH . 'wp-includes/feed-rss2.php');
    });
});

add_action('rss2_item', function () {
    global $post;
    echo '<price>' . get_post_meta($post->ID, '_price', true) . '</price>';
});
```

### After（WpPack）

```php
use WpPack\Component\Feed\AbstractFeed;
use WpPack\Component\Feed\Attribute\AsFeed;
use WpPack\Component\Feed\Attribute\Action\Rss2ItemAction;

#[AsFeed(slug: 'products', title: 'Product Feed')]
class ProductFeed extends AbstractFeed
{
    public function render(): void
    {
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'));
        load_template(ABSPATH . 'wp-includes/feed-rss2.php');
    }

    #[Rss2ItemAction]
    public function addProductPrice(): void
    {
        global $post;
        $price = get_post_meta($post->ID, '_price', true);
        if ($price) {
            echo '<price>' . esc_html($price) . '</price>';
        }
    }
}
```

## 使い方

### 1. カスタムフィード登録（AbstractFeed + AsFeed）

`AbstractFeed` を継承し `#[AsFeed]` アトリビュートでメタデータを指定します。`render()` メソッドでフィード出力を実装します。

```php
use WpPack\Component\Feed\AbstractFeed;
use WpPack\Component\Feed\Attribute\AsFeed;

#[AsFeed(slug: 'products', title: 'Product Feed')]
class ProductFeed extends AbstractFeed
{
    public function render(): void
    {
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
        ]);

        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'));
        load_template(ABSPATH . 'wp-includes/feed-rss2.php');
    }
}
```

#### AsFeed アトリビュート

| パラメータ | 型 | 必須 | デフォルト | 説明 |
|-----------|------|------|-----------|------|
| `slug` | `string` | はい | — | フィード URL スラッグ（`/feed/{slug}/`） |
| `title` | `string` | いいえ | `''` | フィードタイトル（メタデータ用） |

#### FeedRegistry

`FeedRegistry` で複数のフィードを一括管理できます。

```php
use WpPack\Component\Feed\FeedRegistry;

$registry = new FeedRegistry();
$registry->register(new ProductFeed());
$registry->register(new EventFeed());

// 登録確認
$registry->has('products');           // true
$registry->getRegisteredFeeds();      // ['products' => ProductFeed, 'events' => EventFeed]
```

### 2. 既存フィード修正（Named Hook アトリビュート）

既存の RSS/Atom フィードを Named Hook アトリビュートで修正できます。`AbstractFeed` の継承は不要です。

```php
use WpPack\Component\Feed\Attribute\Action\Rss2HeadAction;
use WpPack\Component\Feed\Attribute\Action\Rss2ItemAction;
use WpPack\Component\Feed\Attribute\Filter\TheExcerptRssFilter;

class FeedCustomizer
{
    #[Rss2HeadAction]
    public function addCustomNamespace(): void
    {
        echo 'xmlns:custom="http://example.com/custom"' . "\n";
    }

    #[Rss2ItemAction]
    public function addCustomFields(): void
    {
        global $post;
        $price = get_post_meta($post->ID, '_price', true);
        if ($price) {
            echo '<custom:price>' . esc_html($price) . '</custom:price>';
        }
    }

    #[TheExcerptRssFilter]
    public function customizeExcerpt(string $excerpt): string
    {
        return wp_trim_words(strip_tags($excerpt), 30);
    }
}
```

### フィードクエリの制御

```php
use WpPack\Component\Hook\Attribute\Filter;

class FeedQueryModifier
{
    #[Filter('pre_get_posts')]
    public function modifyFeedQuery(\WP_Query $query): void
    {
        if (!$query->is_feed()) {
            return;
        }

        $query->set('post_type', ['post', 'product']);
        $query->set('posts_per_rss', 30);
        $query->set('orderby', 'modified');
    }
}
```

## Named Hook アトリビュート

### Action

```php
// RSS 2.0
#[Rss2HeadAction(priority?: int = 10)]           // rss2_head — RSS 2.0 ヘッダー
#[Rss2ItemAction(priority?: int = 10)]            // rss2_item — RSS 2.0 アイテム
#[Rss2NsAction(priority?: int = 10)]              // rss2_ns — RSS 2.0 名前空間

// RSS 1.0
#[RssChannelAction(priority?: int = 10)]          // rss_channel — RSS チャンネル
#[RssItemAction(priority?: int = 10)]             // rss_item — RSS アイテム

// Atom
#[AtomHeadAction(priority?: int = 10)]            // atom_head — Atom ヘッダー
#[AtomEntryAction(priority?: int = 10)]           // atom_entry — Atom エントリ

// コメントフィード
#[CommentFeedRssAction(priority?: int = 10)]      // comment_feed_rss — コメントフィード
```

### Filter

```php
// コンテンツフィルター
#[TheContentFeedFilter(priority?: int = 10)]      // the_content_feed — フィードコンテンツ
#[TheExcerptRssFilter(priority?: int = 10)]       // the_excerpt_rss — フィード抜粋
#[TheTitleRssFilter(priority?: int = 10)]         // the_title_rss — フィードタイトル

// フィード設定
#[BloginfoRssFilter(priority?: int = 10)]         // bloginfo_rss — ブログ情報
#[FeedContentTypeFilter(priority?: int = 10)]     // feed_content_type — コンテンツタイプ
#[FeedLinksExtraFilter(priority?: int = 10)]      // feed_links_extra — 追加フィードリンク
#[FeedLinkFilter(priority?: int = 10)]            // feed_link — フィードリンク
#[SelfLinkFilter(priority?: int = 10)]            // self_link — 自己参照リンク
```

## 主要クラス

| クラス | 説明 |
|--------|------|
| `AbstractFeed` | カスタムフィードの基底クラス。`#[AsFeed]` でメタデータ指定、`render()` で出力 |
| `AsFeed` | クラスレベルアトリビュート。`slug`（必須）と `title`（オプション）を指定 |
| `FeedRegistry` | フィードの一括登録・管理。`register()`, `has()`, `getRegisteredFeeds()` |

## 依存関係

### オプション
- **Hook コンポーネント** — Named Hook アトリビュート使用時のみ必要
