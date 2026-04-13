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
use WpPack\Component\Database\Platform\PlatformInterface;

/**
 * DBAL-style database connection.
 *
 * Provides a high-level API for database operations, delegating to a DriverInterface.
 * Uses positional ? placeholders for parameterized queries.
 * Optionally logs queries via PSR-3 LoggerInterface.
 */
class Connection
{
    public function __construct(
        private readonly DriverInterface $driver,
        private readonly ?LoggerInterface $logger = null,
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
        $start = microtime(true);
        $result = $this->driver->executeQuery($query, $params)->fetchAllAssociative();
        $this->logQuery($query, $params, $start);

        return $result;
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchAssociative(string $query, array $params = []): ?array
    {
        $start = microtime(true);
        $result = $this->driver->executeQuery($query, $params)->fetchAssociative();
        $this->logQuery($query, $params, $start);

        return $result;
    }

    /**
     * @param list<mixed> $params
     */
    public function fetchOne(string $query, array $params = []): mixed
    {
        $start = microtime(true);
        $result = $this->driver->executeQuery($query, $params)->fetchOne();
        $this->logQuery($query, $params, $start);

        return $result;
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<mixed>
     */
    public function fetchFirstColumn(string $query, array $params = []): array
    {
        $start = microtime(true);
        $result = $this->driver->executeQuery($query, $params)->fetchFirstColumn();
        $this->logQuery($query, $params, $start);

        return $result;
    }

    /**
     * @param list<mixed> $params
     */
    public function executeQuery(string $query, array $params = []): Result
    {
        $start = microtime(true);
        $result = $this->driver->executeQuery($query, $params);
        $this->logQuery($query, $params, $start);

        return $result;
    }

    /**
     * @param list<mixed> $params
     */
    public function executeStatement(string $query, array $params = []): int
    {
        $start = microtime(true);
        $result = $this->driver->executeStatement($query, $params);
        $this->logQuery($query, $params, $start);

        return $result;
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

    /**
     * @param list<mixed> $params
     */
    private function logQuery(string $sql, array $params, float $startTime): void
    {
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger?->debug('Query executed', [
            'sql' => $sql,
            'params' => $params,
            'time_ms' => $elapsed,
        ]);

        // SAVEQUERIES support for WordPress debug bar / Query Monitor
        if (\defined('SAVEQUERIES') && SAVEQUERIES) {
            global $wpdb;

            if (isset($wpdb->queries) && \is_array($wpdb->queries)) {
                $wpdb->queries[] = [$sql, $elapsed / 1000, ''];
            }
        }
    }
}
