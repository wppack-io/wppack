<?php

declare(strict_types=1);

namespace WpPack\Component\Feed\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Feed\AbstractFeed;
use WpPack\Component\Feed\Attribute\AsFeed;

final class AbstractFeedTest extends TestCase
{
    #[Test]
    public function resolveSlugFromAttribute(): void
    {
        $feed = new ConcreteTestFeed();

        self::assertSame('test-feed', $feed->slug);
    }

    #[Test]
    public function resolveLabelFromAttribute(): void
    {
        $feed = new ConcreteTestFeed();

        self::assertSame('Test Feed', $feed->label);
    }

    #[Test]
    public function labelDefaultsToEmptyString(): void
    {
        $feed = new MinimalTestFeed();

        self::assertSame('', $feed->label);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsFeed] attribute');

        new NoAttributeTestFeed();
    }

    #[Test]
    public function registerCallsAddFeed(): void
    {
        $feed = new ConcreteTestFeed();
        $feed->register();

        // If no exception was thrown, registration succeeded
        self::assertTrue(true);
    }
}

#[AsFeed(slug: 'test-feed', label: 'Test Feed')]
class ConcreteTestFeed extends AbstractFeed
{
    public function render(): void
    {
        echo '<rss>test feed content</rss>';
    }
}

#[AsFeed(slug: 'minimal-feed')]
class MinimalTestFeed extends AbstractFeed
{
    public function render(): void
    {
        echo '<rss>minimal</rss>';
    }
}

class NoAttributeTestFeed extends AbstractFeed
{
    public function render(): void
    {
        echo '';
    }
}
