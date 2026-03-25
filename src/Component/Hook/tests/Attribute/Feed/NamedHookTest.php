<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Feed;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Feed\Action\AtomEntryAction;
use WpPack\Component\Hook\Attribute\Feed\Action\AtomHeadAction;
use WpPack\Component\Hook\Attribute\Feed\Action\CommentFeedRssAction;
use WpPack\Component\Hook\Attribute\Feed\Action\Rss2HeadAction;
use WpPack\Component\Hook\Attribute\Feed\Action\Rss2ItemAction;
use WpPack\Component\Hook\Attribute\Feed\Action\Rss2NsAction;
use WpPack\Component\Hook\Attribute\Feed\Action\RssChannelAction;
use WpPack\Component\Hook\Attribute\Feed\Action\RssItemAction;
use WpPack\Component\Hook\Attribute\Feed\Filter\BloginfoRssFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\FeedContentTypeFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\FeedLinksExtraFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\FeedLinkFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\SelfLinkFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\TheContentFeedFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\TheExcerptRssFilter;
use WpPack\Component\Hook\Attribute\Feed\Filter\TheTitleRssFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function atomHeadActionHasCorrectHookName(): void
    {
        $action = new AtomHeadAction();

        self::assertSame('atom_head', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function commentFeedRssActionHasCorrectHookName(): void
    {
        $action = new CommentFeedRssAction();

        self::assertSame('comment_feed_rss', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function rss2HeadActionHasCorrectHookName(): void
    {
        $action = new Rss2HeadAction();

        self::assertSame('rss2_head', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function rssChannelActionHasCorrectHookName(): void
    {
        $action = new RssChannelAction();

        self::assertSame('rss_channel', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function rssItemActionHasCorrectHookName(): void
    {
        $action = new RssItemAction();

        self::assertSame('rss_item', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function feedContentTypeFilterHasCorrectHookName(): void
    {
        $filter = new FeedContentTypeFilter();

        self::assertSame('feed_content_type', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function feedLinksExtraFilterHasCorrectHookName(): void
    {
        $filter = new FeedLinksExtraFilter();

        self::assertSame('feed_links_extra', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function theContentFeedFilterHasCorrectHookName(): void
    {
        $filter = new TheContentFeedFilter();

        self::assertSame('the_content_feed', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function theExcerptRssFilterHasCorrectHookName(): void
    {
        $filter = new TheExcerptRssFilter();

        self::assertSame('the_excerpt_rss', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function theTitleRssFilterHasCorrectHookName(): void
    {
        $filter = new TheTitleRssFilter();

        self::assertSame('the_title_rss', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function rss2ItemActionHasCorrectHookName(): void
    {
        $action = new Rss2ItemAction();

        self::assertSame('rss2_item', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function rss2NsActionHasCorrectHookName(): void
    {
        $action = new Rss2NsAction();

        self::assertSame('rss2_ns', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function atomEntryActionHasCorrectHookName(): void
    {
        $action = new AtomEntryAction();

        self::assertSame('atom_entry', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function bloginfoRssFilterHasCorrectHookName(): void
    {
        $filter = new BloginfoRssFilter();

        self::assertSame('bloginfo_rss', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function feedLinkFilterHasCorrectHookName(): void
    {
        $filter = new FeedLinkFilter();

        self::assertSame('feed_link', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function selfLinkFilterHasCorrectHookName(): void
    {
        $filter = new SelfLinkFilter();

        self::assertSame('self_link', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function atomHeadActionAcceptsCustomPriority(): void
    {
        $action = new AtomHeadAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new AtomEntryAction());
        self::assertInstanceOf(Action::class, new AtomHeadAction());
        self::assertInstanceOf(Action::class, new CommentFeedRssAction());
        self::assertInstanceOf(Action::class, new Rss2HeadAction());
        self::assertInstanceOf(Action::class, new Rss2ItemAction());
        self::assertInstanceOf(Action::class, new Rss2NsAction());
        self::assertInstanceOf(Action::class, new RssChannelAction());
        self::assertInstanceOf(Action::class, new RssItemAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new BloginfoRssFilter());
        self::assertInstanceOf(Filter::class, new FeedContentTypeFilter());
        self::assertInstanceOf(Filter::class, new FeedLinksExtraFilter());
        self::assertInstanceOf(Filter::class, new FeedLinkFilter());
        self::assertInstanceOf(Filter::class, new SelfLinkFilter());
        self::assertInstanceOf(Filter::class, new TheContentFeedFilter());
        self::assertInstanceOf(Filter::class, new TheExcerptRssFilter());
        self::assertInstanceOf(Filter::class, new TheTitleRssFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[Rss2HeadAction]
            public function onRss2Head(): void {}

            #[TheContentFeedFilter]
            public function onTheContentFeed(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onRss2Head');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('rss2_head', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onTheContentFeed');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('the_content_feed', $attributes[0]->newInstance()->hook);
    }
}
