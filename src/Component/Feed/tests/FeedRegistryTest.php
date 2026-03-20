<?php

declare(strict_types=1);

namespace WpPack\Component\Feed\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Feed\AbstractFeed;
use WpPack\Component\Feed\Attribute\AsFeed;
use WpPack\Component\Feed\FeedRegistry;

final class FeedRegistryTest extends TestCase
{
    #[Test]
    public function registerDelegatesToFeed(): void
    {
        $registry = new FeedRegistry();
        $feed = new RegistryTestFeed();

        $registry->register($feed);

        self::assertTrue($registry->has('registry-feed'));
    }

    #[Test]
    public function hasReturnsTrueAfterRegistration(): void
    {
        $registry = new FeedRegistry();
        $registry->register(new RegistryTestFeed());

        self::assertTrue($registry->has('registry-feed'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownSlug(): void
    {
        $registry = new FeedRegistry();

        self::assertFalse($registry->has('unknown-feed'));
    }

    #[Test]
    public function allReturnsEmptyByDefault(): void
    {
        $registry = new FeedRegistry();

        self::assertSame([], $registry->all());
    }

    #[Test]
    public function allReturnsRegisteredFeeds(): void
    {
        $registry = new FeedRegistry();
        $feed = new RegistryTestFeed();
        $registry->register($feed);

        $feeds = $registry->all();

        self::assertCount(1, $feeds);
        self::assertSame($feed, $feeds['registry-feed']);
    }

    #[Test]
    public function laterRegistrationOverridesSameSlug(): void
    {
        $registry = new FeedRegistry();
        $first = new RegistryTestFeed();
        $second = new RegistryTestFeed();

        $registry->register($first);
        $registry->register($second);

        $feeds = $registry->all();

        self::assertCount(1, $feeds);
        self::assertSame($second, $feeds['registry-feed']);
    }
}

#[AsFeed(slug: 'registry-feed', label: 'Registry Test Feed')]
class RegistryTestFeed extends AbstractFeed
{
    public function render(): void
    {
        echo '<rss>registry test</rss>';
    }
}
