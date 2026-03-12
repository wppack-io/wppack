<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\FeedDataCollector;

final class FeedDataCollectorTest extends TestCase
{
    private FeedDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new FeedDataCollector();
    }

    #[Test]
    public function getNameReturnsFeed(): void
    {
        self::assertSame('feed', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsFeed(): void
    {
        self::assertSame('Feed', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('get_bloginfo')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['feeds']);
        self::assertSame(0, $data['total_count']);
        self::assertSame(0, $data['custom_count']);
        self::assertTrue($data['feed_discovery']);
    }

    #[Test]
    public function getBadgeValueReturnsTotalCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 4]);

        self::assertSame('4', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 0]);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 4]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectGathersBuiltInFeeds(): void
    {
        if (!function_exists('get_bloginfo')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertGreaterThanOrEqual(1, $data['total_count']);
        self::assertNotEmpty($data['feeds']);

        // Check that built-in feeds have the correct structure
        $feedTypes = array_column($data['feeds'], 'type');
        self::assertContains('rss2', $feedTypes);

        foreach ($data['feeds'] as $feed) {
            self::assertArrayHasKey('type', $feed);
            self::assertArrayHasKey('url', $feed);
            self::assertArrayHasKey('is_custom', $feed);
        }
    }

    #[Test]
    public function collectGathersCommentsFeed(): void
    {
        if (!function_exists('get_bloginfo') || !function_exists('get_post_comments_feed_link')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $feedTypes = array_column($data['feeds'], 'type');
        self::assertContains('comments-rss2', $feedTypes);
    }

    #[Test]
    public function collectDetectsCustomFeeds(): void
    {
        if (!function_exists('get_bloginfo')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wp_rewrite;
        $savedExtraFeeds = $wp_rewrite->extra_feeds ?? null;

        $wp_rewrite->extra_feeds = ['custom-feed-test-debug'];

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $customFeeds = array_filter($data['feeds'], static fn(array $f): bool => $f['is_custom']);
            $customTypes = array_column($customFeeds, 'type');
            self::assertContains('custom-feed-test-debug', $customTypes);
            self::assertGreaterThanOrEqual(1, $data['custom_count']);
        } finally {
            if ($savedExtraFeeds !== null) {
                $wp_rewrite->extra_feeds = $savedExtraFeeds;
            } else {
                $wp_rewrite->extra_feeds = [];
            }
        }
    }

    #[Test]
    public function collectFeedDiscoveryReflectsOption(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsBool($data['feed_discovery']);
    }

    #[Test]
    public function collectBuiltInFeedsAreNotCustom(): void
    {
        if (!function_exists('get_bloginfo')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $builtinTypes = ['rss2', 'atom', 'rdf', 'rss', 'comments-rss2'];
        foreach ($data['feeds'] as $feed) {
            if (in_array($feed['type'], $builtinTypes, true)) {
                self::assertFalse($feed['is_custom'], "Feed type {$feed['type']} should not be custom");
            }
        }
    }
}
