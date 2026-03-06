<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Result;

/**
 * @implements \IteratorAggregate<int, \WP_Post>
 */
final class PostQueryResult implements \IteratorAggregate, \Countable
{
    /**
     * @param list<\WP_Post> $posts
     */
    public function __construct(
        private readonly array $posts,
        public readonly int $total,
        public readonly int $totalPages,
        public readonly int $currentPage,
        private readonly \WP_Query $wpQueryInstance,
    ) {}

    /**
     * @return list<\WP_Post>
     */
    public function all(): array
    {
        return $this->posts;
    }

    public function first(): ?\WP_Post
    {
        return $this->posts[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->posts === [];
    }

    public function count(): int
    {
        return \count($this->posts);
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map(static fn(\WP_Post $post): int => $post->ID, $this->posts);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function wpQuery(): \WP_Query
    {
        return $this->wpQueryInstance;
    }

    /**
     * @return \ArrayIterator<int, \WP_Post>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->posts);
    }
}
