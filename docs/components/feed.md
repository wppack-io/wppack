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
use WpPack\Component\Feed\Attribute\AsFeed;
use WpPack\Component\Feed\Attribute\Rss2ItemAction;

#[AsFeed(slug: 'products', title: 'Product Feed')]
class ProductFeed
{
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

## クイックスタート

### カスタムフィードの登録

```php
use WpPack\Component\Feed\Attribute\AsFeed;

#[AsFeed(slug: 'products', title: 'Product Feed')]
class ProductFeed
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

### フィードコンテンツの変更

```php
use WpPack\Component\Feed\Attribute\Rss2ItemAction;
use WpPack\Component\Feed\Attribute\Rss2HeadAction;
use WpPack\Component\Feed\Attribute\TheExcerptRssFilter;

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

```php
// フィードヘッダー
#[Rss2HeadAction(priority?: int = 10)]           // rss2_head — RSS 2.0 ヘッダー
#[AtomHeadAction(priority?: int = 10)]            // atom_head — Atom ヘッダー
#[Rss2NsAction(priority?: int = 10)]              // rss2_ns — RSS 2.0 名前空間

// フィードアイテム
#[Rss2ItemAction(priority?: int = 10)]            // rss2_item — RSS 2.0 アイテム
#[AtomEntryAction(priority?: int = 10)]           // atom_entry — Atom エントリ

// コンテンツフィルター
#[TheContentFeedFilter(priority?: int = 10)]      // the_content_feed — フィードコンテンツ
#[TheExcerptRssFilter(priority?: int = 10)]       // the_excerpt_rss — フィード抜粋
#[TheTitleRssFilter(priority?: int = 10)]         // the_title_rss — フィードタイトル

// フィード設定
#[BloginfoRssFilter(priority?: int = 10)]         // bloginfo_rss — ブログ情報
#[FeedLinkFilter(priority?: int = 10)]            // feed_link — フィードリンク
#[SelfLinkFilter(priority?: int = 10)]            // self_link — 自己参照リンク
```

## 依存関係

### 必須
- **Hook コンポーネント** — フック登録用
