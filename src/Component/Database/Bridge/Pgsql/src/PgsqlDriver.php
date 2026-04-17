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

namespace WpPack\Component\Database\Bridge\Pgsql;

use WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator;
use WpPack\Component\Database\Driver\AbstractDriver;
use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Sql\PlaceholderScanner;
use WpPack\Component\Database\Statement;

class PgsqlDriver extends AbstractDriver
{
    /** @var \PgSql\Connection|null */
    protected mixed $connection = null;
    protected bool $inTx = false;
    private bool $ownsConnection;
    private int $stmtCounter = 0;

    public function __construct(
        protected readonly string $host,
        protected readonly string $username,
        #[\SensitiveParameter]
        protected readonly string $password,
        protected readonly string $database,
        protected readonly int $port = 5432,
    ) {
        $this->ownsConnection = true;
    }

    /**
     * Wrap an existing PgSql connection.
     */
    public static function fromPgsqlConnection(\PgSql\Connection $connection): self
    {
        $driver = new self(host: '', username: '', password: '', database: '');
        $driver->connection = $connection;
        $driver->ownsConnection = false;

        return $driver;
    }

    public function getName(): string
    {
        return 'pgsql';
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }

    public function getPlatform(): PlatformInterface
    {
        return new PostgresqlPlatform();
    }

    public function getQueryTranslator(): \WpPack\Component\Database\Translator\QueryTranslatorInterface
    {
        return new PostgresqlQueryTranslator($this);
    }

    public function getNativeConnection(): mixed
    {
        return $this->connection;
    }

    public function quoteStringLiteral(string $value): string
    {
        $this->ensureConnected();

        return pg_escape_literal($this->connection, $value);
    }

    public function escapeStringContent(string $value): string
    {
        $this->ensureConnected();

        // pg_escape_string returns a value suitable for splicing inside a
        // single-quoted literal, honouring the current connection's
        // standard_conforming_strings setting. Unlike pg_escape_literal,
        // it never adds the E'...' prefix, so the caller's own outer
        // quotes stay valid.
        return pg_escape_string($this->connection, $value);
    }

    protected function doConnect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $connection = @pg_connect($this->buildConnectionString());

        if ($connection === false) {
            throw new ConnectionException('Failed to connect to PostgreSQL.');
        }

        $this->connection = $connection;
    }

    /**
     * Build the libpq connection string.
     *
     * Subclasses (e.g., AuroraDsqlDriver) can override to add SSL, sslnegotiation, etc.
     */
    protected function buildConnectionString(): string
    {
        $esc = static fn(string $v): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $v) . "'";

        return \sprintf(
            'host=%s port=%d dbname=%s user=%s password=%s client_encoding=%s',
            $esc($this->host),
            $this->port,
            $esc($this->database),
            $esc($this->username),
            $esc($this->password),
            $esc('UTF8'),
        );
    }

    /**
     * Check if libpq supports sslnegotiation=direct (libpq 17+).
     */
    protected static function supportsDirectSslNegotiation(): bool
    {
        if (\defined('PGSQL_LIBPQ_VERSION')) {
            return version_compare((string) \PGSQL_LIBPQ_VERSION, '17.0', '>=');
        }

        return false;
    }

    protected function doClose(): void
    {
        if ($this->connection !== null && $this->ownsConnection) {
            pg_close($this->connection);
        }

        $this->connection = null;
        $this->inTx = false;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $this->ensureConnected();

        if ($params === []) {
            $pgResult = @pg_query($this->connection, $sql);
        } else {
            $pgResult = @pg_query_params($this->connection, $this->convertPlaceholders($sql), $params);
        }

        if ($pgResult === false) {
            $this->throwQueryError();
        }

        /** @var list<array<string, mixed>> */
        $rows = pg_fetch_all($pgResult, \PGSQL_ASSOC) ?: [];
        pg_free_result($pgResult);

        return new Result($rows);
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $this->ensureConnected();

        if ($params === []) {
            $pgResult = @pg_query($this->connection, $sql);
        } else {
            $pgResult = @pg_query_params($this->connection, $this->convertPlaceholders($sql), $params);
        }

        if ($pgResult === false) {
            $this->throwQueryError();
        }

        $affected = pg_affected_rows($pgResult);
        pg_free_result($pgResult);

        return $affected;
    }

    /**
     * Raise a DriverException carrying the current pg error, dropping the
     * connection handle first when the failure looks like a permanent
     * server-side disconnect. PostgreSQL reports gone-away as a variety of
     * strings — 'server closed the connection unexpectedly' (plain exit),
     * 'SSL SYSCALL error' (TLS read failure), 'terminating connection due
     * to administrator command' (pg_terminate_backend or failover) — none
     * of which map to an errno the way MySQL does, so we pattern-match on
     * the text. A dead connection left in place would fail every
     * subsequent query until the wpdb instance is rebuilt; nulling it lets
     * ensureConnected() re-open transparently on the next call.
     */
    private function throwQueryError(): never
    {
        $message = (string) pg_last_error($this->connection);

        if (self::isConnectionLostError($message) || !self::isConnectionAlive($this->connection)) {
            if ($this->ownsConnection) {
                @pg_close($this->connection);
            }

            $this->connection = null;
            $this->inTx = false;
        }

        throw new DriverException($message);
    }

    private static function isConnectionLostError(string $message): bool
    {
        static $needles = [
            'server closed the connection',
            'terminating connection',
            'SSL SYSCALL error',
            'could not receive data from server',
            'connection to server was lost',
        ];

        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function isConnectionAlive(mixed $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        $status = @pg_connection_status($connection);

        return $status === \PGSQL_CONNECTION_OK;
    }

    protected function doPrepare(string $sql): Statement
    {
        $this->ensureConnected();

        $stmtName = 'wppack_stmt_' . (++$this->stmtCounter);
        $pgSql = $this->convertPlaceholders($sql);

        $result = @pg_prepare($this->connection, $stmtName, $pgSql);

        if ($result === false) {
            $this->throwQueryError();
        }

        $conn = $this->connection;

        $executeQuery = static function (array $params) use ($conn, $stmtName): Result {
            $pgResult = @pg_execute($conn, $stmtName, $params);

            if ($pgResult === false) {
                throw new DriverException((string) pg_last_error($conn));
            }

            /** @var list<array<string, mixed>> */
            $rows = pg_fetch_all($pgResult, \PGSQL_ASSOC) ?: [];
            pg_free_result($pgResult);

            return new Result($rows);
        };

        $executeStatement = static function (array $params) use ($conn, $stmtName): int {
            $pgResult = @pg_execute($conn, $stmtName, $params);

            if ($pgResult === false) {
                throw new DriverException((string) pg_last_error($conn));
            }

            $affected = pg_affected_rows($pgResult);
            pg_free_result($pgResult);

            return $affected;
        };

        $close = static function () use ($conn, $stmtName): void {
            @pg_query($conn, "DEALLOCATE \"{$stmtName}\"");
        };

        return new Statement($executeQuery, $executeStatement, $close);
    }

    protected function doLastInsertId(): int
    {
        $this->ensureConnected();

        $result = @pg_query($this->connection, 'SELECT lastval()');

        if ($result === false) {
            return 0;
        }

        $row = pg_fetch_row($result);
        pg_free_result($result);

        return $row !== false ? (int) $row[0] : 0;
    }

    protected function doBeginTransaction(): void
    {
        $this->ensureConnected();

        $result = @pg_query($this->connection, 'BEGIN');

        if ($result === false) {
            throw new DriverException((string) pg_last_error($this->connection));
        }

        pg_free_result($result);
        $this->inTx = true;
    }

    protected function doCommit(): void
    {
        $this->ensureConnected();

        $result = @pg_query($this->connection, 'COMMIT');

        if ($result === false) {
            throw new DriverException((string) pg_last_error($this->connection));
        }

        pg_free_result($result);
        $this->inTx = false;
    }

    protected function doRollBack(): void
    {
        $this->ensureConnected();

        $result = @pg_query($this->connection, 'ROLLBACK');

        if ($result === false) {
            throw new DriverException((string) pg_last_error($this->connection));
        }

        pg_free_result($result);
        $this->inTx = false;
    }

    protected function ensureConnected(): void
    {
        if ($this->connection === null) {
            $this->connect();
        }
    }

    /**
     * Convert ? placeholders to PostgreSQL $1, $2, ... format.
     *
     * Skips ? characters inside single-quoted string literals to prevent
     * data corruption (e.g., 'What?' must not become 'What$1').
     */
    protected function convertPlaceholders(string $sql): string
    {
        return PlaceholderScanner::replace(
            $sql,
            static fn(int $index): string => '$' . ($index + 1),
        );
    }
}
