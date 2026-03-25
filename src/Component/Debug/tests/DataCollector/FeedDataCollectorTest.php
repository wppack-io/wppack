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
    public function getIndicatorValueReturnsTotalCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 4]);

        self::assertSame('4', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 0]);

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
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

        $this->collector->collect();
        $data = $this->collector->getData();

        $feedTypes = array_column($data['feeds'], 'type');
        self::assertContains('comments-rss2', $feedTypes);
    }

    #[Test]
    public function collectDetectsCustomFeeds(): void
    {

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

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsBool($data['feed_discovery']);
    }

    #[Test]
    public function collectBuiltInFeedsAreNotCustom(): void
    {

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
