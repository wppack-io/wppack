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

namespace WpPack\Component\Query\Result;

/**
 * @implements \IteratorAggregate<int, \WP_User>
 */
final class UserQueryResult implements \IteratorAggregate, \Countable
{
    /**
     * @param list<\WP_User> $users
     */
    public function __construct(
        private readonly array $users,
        public readonly int $total,
        public readonly int $totalPages,
        public readonly int $currentPage,
        private readonly \WP_User_Query $wpUserQuery,
    ) {}

    /**
     * @return list<\WP_User>
     */
    public function all(): array
    {
        return $this->users;
    }

    public function first(): ?\WP_User
    {
        return $this->users[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->users === [];
    }

    public function count(): int
    {
        return \count($this->users);
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map(static fn(\WP_User $user): int => $user->ID, $this->users);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function wpUserQuery(): \WP_User_Query
    {
        return $this->wpUserQuery;
    }

    /**
     * @return \ArrayIterator<int, \WP_User>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->users);
    }
}
