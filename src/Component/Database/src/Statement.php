<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Database;

/**
 * Represents a prepared statement.
 *
 * Constructed by drivers from their native statement types.
 * Uses closures to delegate execution to the driver without exposing native types.
 */
final class Statement
{
    /** @var array<int, mixed> */
    private array $boundValues = [];

    /**
     * @param \Closure(list<mixed>): Result $executeQueryFn
     * @param \Closure(list<mixed>): int    $executeStatementFn
     * @param \Closure(): void              $closeFn
     */
    public function __construct(
        private readonly \Closure $executeQueryFn,
        private readonly \Closure $executeStatementFn,
        private readonly \Closure $closeFn,
    ) {}

    /**
     * Bind a value to a positional parameter (1-indexed).
     */
    public function bindValue(int $param, mixed $value): void
    {
        $this->boundValues[$param] = $value;
    }

    /**
     * Execute the prepared statement as a query and return a Result.
     *
     * @param list<mixed> $params Optional parameters (overrides bound values)
     */
    public function executeQuery(array $params = []): Result
    {
        return ($this->executeQueryFn)($this->resolveParams($params));
    }

    /**
     * Execute the prepared statement as a DML statement and return affected rows.
     *
     * @param list<mixed> $params Optional parameters (overrides bound values)
     */
    public function executeStatement(array $params = []): int
    {
        return ($this->executeStatementFn)($this->resolveParams($params));
    }

    public function close(): void
    {
        ($this->closeFn)();
        $this->boundValues = [];
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<mixed>
     */
    private function resolveParams(array $params): array
    {
        if ($params !== []) {
            return $params;
        }

        if ($this->boundValues === []) {
            return [];
        }

        // Convert 1-indexed bound values to 0-indexed list
        ksort($this->boundValues);

        return array_values($this->boundValues);
    }
}
