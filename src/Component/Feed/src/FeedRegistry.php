<?php

declare(strict_types=1);

namespace WpPack\Component\Feed;

final class FeedRegistry
{
    /** @var array<string, AbstractFeed> */
    private array $feeds = [];

    public function register(AbstractFeed $feed): void
    {
        $this->feeds[$feed->slug] = $feed;
        $feed->register();
    }

    public function has(string $slug): bool
    {
        return isset($this->feeds[$slug]);
    }

    /**
     * @return array<string, AbstractFeed>
     */
    public function getRegisteredFeeds(): array
    {
        return $this->feeds;
    }
}
