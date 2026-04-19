# WPPack Feed

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=feed)](https://codecov.io/github/wppack-io/wppack)

A component for WordPress feed management (RSS 2.0, Atom). Provides `AbstractFeed` + `#[AsFeed]` attribute for custom feed registration, and Named Hook attributes for modifying existing feeds.

## Installation

```bash
composer require wppack/feed
```

## Usage

### Custom Feed Registration

```php
use WPPack\Component\Feed\AbstractFeed;
use WPPack\Component\Feed\Attribute\AsFeed;

#[AsFeed(slug: 'products', label: 'Product Feed')]
class ProductFeed extends AbstractFeed
{
    public function render(): void
    {
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'));
        load_template(ABSPATH . 'wp-includes/feed-rss2.php');
    }
}
```

### FeedRegistry

```php
use WPPack\Component\Feed\FeedRegistry;

$registry = new FeedRegistry();
$registry->register(new ProductFeed());
$registry->has('products');              // true
$registry->all();         // ['products' => ProductFeed]
```

### Named Hook Attributes

```php
use WPPack\Component\Hook\Attribute\Feed\Action\Rss2HeadAction;
use WPPack\Component\Hook\Attribute\Feed\Action\Rss2ItemAction;
use WPPack\Component\Hook\Attribute\Feed\Filter\TheContentFeedFilter;

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

    #[TheContentFeedFilter]
    public function filterContent(string $content): string
    {
        return strip_tags($content, '<p><a><strong><em>');
    }
}
```

**Action Attributes:**
- `#[Rss2HeadAction]` — `rss2_head`
- `#[Rss2ItemAction]` — `rss2_item`
- `#[Rss2NsAction]` — `rss2_ns`
- `#[RssChannelAction]` — `rss_channel`
- `#[RssItemAction]` — `rss_item`
- `#[AtomHeadAction]` — `atom_head`
- `#[AtomEntryAction]` — `atom_entry`
- `#[CommentFeedRssAction]` — `comment_feed_rss`

**Filter Attributes:**
- `#[TheContentFeedFilter]` — `the_content_feed`
- `#[TheExcerptRssFilter]` — `the_excerpt_rss`
- `#[TheTitleRssFilter]` — `the_title_rss`
- `#[BloginfoRssFilter]` — `bloginfo_rss`
- `#[FeedContentTypeFilter]` — `feed_content_type`
- `#[FeedLinksExtraFilter]` — `feed_links_extra`
- `#[FeedLinkFilter]` — `feed_link`
- `#[SelfLinkFilter]` — `self_link`

## Documentation

See [docs/components/feed/](../../../docs/components/feed/) for full documentation.

## License

MIT
