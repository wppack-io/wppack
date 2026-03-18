## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Feed/Subscriber/`

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
