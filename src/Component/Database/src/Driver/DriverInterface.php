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

namespace WpPack\Component\Database\Driver;

use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

/**
 * Service Provider Interface for database drivers.
 *
 * Drivers implement this interface to provide access to a specific database engine.
 * Parameters use positional ? placeholders. Each driver converts internally to its
 * native format (e.g., $1/$2 for PostgreSQL, :param1 for RDS Data API).
 */
interface DriverInterface
{
    public function getName(): string;

    public function connect(): void;

    public function isConnected(): bool;

    public function close(): void;

    /**
     * @param list<mixed> $params
     */
    public function executeQuery(string $sql, array $params = []): Result;

    /**
     * @param list<mixed> $params
     */
    public function executeStatement(string $sql, array $params = []): int;

    public function prepare(string $sql): Statement;

    public function lastInsertId(): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    public function getPlatform(): PlatformInterface;

    /**
     * Return the underlying native connection handle.
     *
     * For MySQL: \mysqli, for SQLite: \PDO, for PgSql: \PgSql\Connection.
     * Returns null for HTTP-based drivers (RDS Data API, Aurora DSQL).
     */
    public function getNativeConnection(): mixed;
}
