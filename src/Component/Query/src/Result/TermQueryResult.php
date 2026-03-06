<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Result;

/**
 * @implements \IteratorAggregate<int, \WP_Term>
 */
final class TermQueryResult implements \IteratorAggregate, \Countable
{
    /**
     * @param list<\WP_Term> $terms
     */
    public function __construct(
        private readonly array $terms,
        public readonly int $total,
        private readonly \WP_Term_Query $wpTermQuery,
    ) {}

    /**
     * @return list<\WP_Term>
     */
    public function all(): array
    {
        return $this->terms;
    }

    public function first(): ?\WP_Term
    {
        return $this->terms[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->terms === [];
    }

    public function count(): int
    {
        return \count($this->terms);
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map(static fn(\WP_Term $term): int => $term->term_id, $this->terms);
    }

    public function wpTermQuery(): \WP_Term_Query
    {
        return $this->wpTermQuery;
    }

    /**
     * @return \ArrayIterator<int, \WP_Term>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->terms);
    }
}
