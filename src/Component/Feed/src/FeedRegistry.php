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
    public function all(): array
    {
        return $this->feeds;
    }
}
