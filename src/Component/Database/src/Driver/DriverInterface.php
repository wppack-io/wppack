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
     * Return the query translator for this driver.
     *
     * Used by the db.php drop-in to translate WordPress MySQL queries
     * to the target engine's dialect. MySQL drivers return NullQueryTranslator.
     */
    public function getQueryTranslator(): \WpPack\Component\Database\Translator\QueryTranslatorInterface;

    /**
     * Return the underlying native connection handle.
     *
     * For MySQL: \mysqli, for SQLite: \PDO, for PgSql: \PgSql\Connection.
     * Returns null for HTTP-based drivers (RDS Data API, Aurora DSQL).
     */
    public function getNativeConnection(): mixed;

    /**
     * Escape $value and wrap it in single quotes so the result is a valid
     * SQL string literal for this engine (e.g. `'O''Brien'` on MySQL).
     *
     * Used by WpPackWpdb for debug/display interpolation of '?' placeholders
     * — the driver still executes queries with native parameter binding.
     */
    public function quoteStringLiteral(string $value): string;

    /**
     * Escape $value for splicing inside a single-quoted SQL literal, without
     * wrapping quotes. Used by wpdb::_real_escape() compatibility so legacy
     * plugin code that concatenates values into SQL receives engine-correct
     * escaping (mysqli_real_escape_string on MySQL, pg_escape_string on
     * PostgreSQL, etc.) rather than a blanket `addslashes()` that produces
     * mangled output on non-MySQL engines.
     *
     * Note: new code should use prepared statements via prepare() — this
     * method exists purely for legacy plugin compatibility.
     */
    public function escapeStringContent(string $value): string;
}
