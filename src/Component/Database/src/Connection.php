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

use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Platform\PlatformInterface;

/**
 * DBAL-style database connection.
 *
 * Provides a high-level API for database operations, delegating to a DriverInterface.
 * Uses positional ? placeholders for parameterized queries.
 */
class Connection
{
    public function __construct(
        private readonly DriverInterface $driver,
    ) {}

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->driver->getPlatform();
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(string $query, array $params = []): array
    {
        return $this->driver->executeQuery($query, $params)->fetchAllAssociative();
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchAssociative(string $query, array $params = []): ?array
    {
        return $this->driver->executeQuery($query, $params)->fetchAssociative();
    }

    /**
     * @param list<mixed> $params
     */
    public function fetchOne(string $query, array $params = []): mixed
    {
        return $this->driver->executeQuery($query, $params)->fetchOne();
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<mixed>
     */
    public function fetchFirstColumn(string $query, array $params = []): array
    {
        return $this->driver->executeQuery($query, $params)->fetchFirstColumn();
    }

    /**
     * @param list<mixed> $params
     */
    public function executeQuery(string $query, array $params = []): Result
    {
        return $this->driver->executeQuery($query, $params);
    }

    /**
     * @param list<mixed> $params
     */
    public function executeStatement(string $query, array $params = []): int
    {
        return $this->driver->executeStatement($query, $params);
    }

    public function prepare(string $sql): Statement
    {
        return $this->driver->prepare($sql);
    }

    public function lastInsertId(): int
    {
        return $this->driver->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->driver->beginTransaction();
    }

    public function commit(): void
    {
        $this->driver->commit();
    }

    public function rollBack(): void
    {
        $this->driver->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->driver->inTransaction();
    }

    /**
     * Execute a callable within a transaction.
     *
     * @template T
     * @param callable(Connection): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->getPlatform()->quoteIdentifier($identifier);
    }
}
