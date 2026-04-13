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

namespace WpPack\Component\Database;

/**
 * Represents a result set from a database query.
 *
 * Constructed by drivers from their native result types.
 */
final class Result
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private array $rows,
        private readonly int $affectedRows = 0,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchAssociative(): ?array
    {
        return $this->rows[0] ?? null;
    }

    public function fetchOne(): mixed
    {
        if ($this->rows === []) {
            return null;
        }

        $firstRow = $this->rows[0];

        return array_values($firstRow)[0] ?? null;
    }

    /**
     * @return list<mixed>
     */
    public function fetchFirstColumn(): array
    {
        $column = [];

        foreach ($this->rows as $row) {
            $column[] = array_values($row)[0] ?? null;
        }

        return $column;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    public function free(): void
    {
        $this->rows = [];
    }
}
