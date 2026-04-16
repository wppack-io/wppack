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

use Psr\Log\LoggerInterface;
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Exception\QueryException;
use WpPack\Component\Database\Platform\PlatformInterface;

/**
 * DBAL-style database connection.
 *
 * Accepts both native ? placeholders and WordPress %s/%d/%f placeholders.
 * Wraps DriverException into QueryException for consistent error handling.
 * Optionally logs queries via PSR-3 LoggerInterface and QueryLoggerInterface.
 */
class Connection
{
    public function __construct(
        private readonly DriverInterface $driver,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?QueryLoggerInterface $queryLogger = null,
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
     *
     * @throws QueryException
     */
    public function fetchAllAssociative(string $query, array $params = []): array
    {
        return $this->executeWithLogging(
            $query,
            $params,
            fn(string $sql, array $p): array => $this->driver->executeQuery($sql, $p)->fetchAllAssociative(),
        );
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>|null
     *
     * @throws QueryException
     */
    public function fetchAssociative(string $query, array $params = []): ?array
    {
        return $this->executeWithLogging(
            $query,
            $params,
            fn(string $sql, array $p): ?array => $this->driver->executeQuery($sql, $p)->fetchAssociative(),
        );
    }

    /**
     * @param list<mixed> $params
     *
     * @throws QueryException
     */
    public function fetchOne(string $query, array $params = []): mixed
    {
        return $this->executeWithLogging(
            $query,
            $params,
            fn(string $sql, array $p): mixed => $this->driver->executeQuery($sql, $p)->fetchOne(),
        );
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<mixed>
     *
     * @throws QueryException
     */
    public function fetchFirstColumn(string $query, array $params = []): array
    {
        return $this->executeWithLogging(
            $query,
            $params,
            fn(string $sql, array $p): array => $this->driver->executeQuery($sql, $p)->fetchFirstColumn(),
        );
    }

    /**
     * @param list<mixed> $params
     *
     * @throws QueryException
     */
    public function executeQuery(string $query, array $params = []): Result
    {
        return $this->executeWithLogging(
            $query,
            $params,
            fn(string $sql, array $p): Result => $this->driver->executeQuery($sql, $p),
        );
    }

    /**
     * @param list<mixed> $params
     *
     * @throws QueryException
     */
    public function executeStatement(string $query, array $params = []): int
    {
        return $this->executeWithLogging(
            $query,
            $params,
            fn(string $sql, array $p): int => $this->driver->executeStatement($sql, $p),
        );
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
     *
     * @param callable(Connection): T $callback
     *
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

    /**
     * Execute a query with placeholder conversion, logging, and exception wrapping.
     *
     * @template T
     *
     * @param list<mixed> $params
     * @param callable(string, list<mixed>): T $operation
     *
     * @return T
     *
     * @throws QueryException
     */
    private function executeWithLogging(string $query, array $params, callable $operation): mixed
    {
        [$sql, $nativeParams] = PlaceholderConverter::convert($query, $params);

        $start = microtime(true);

        try {
            $result = $operation($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);

        $this->logger?->debug('Query executed', [
            'sql' => $sql,
            'params' => $nativeParams,
            'time_ms' => $elapsed,
        ]);

        $this->queryLogger?->log($sql, $nativeParams, $elapsed);

        return $result;
    }
}
