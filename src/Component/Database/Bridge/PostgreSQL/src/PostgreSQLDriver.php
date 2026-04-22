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

namespace WPPack\Component\Database\Bridge\PostgreSQL;

use WPPack\Component\Database\Bridge\PostgreSQL\Translator\PostgreSQLQueryTranslator;
use WPPack\Component\Database\Driver\AbstractDriver;
use WPPack\Component\Database\Exception\ConnectionException;
use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Platform\PlatformInterface;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Sql\PlaceholderScanner;
use WPPack\Component\Database\Statement;

class PostgreSQLDriver extends AbstractDriver
{
    /** @var \PgSql\Connection|null */
    protected mixed $connection = null;
    protected bool $inTx = false;
    private bool $ownsConnection;
    private int $stmtCounter = 0;

    public function __construct(
        protected readonly string $host,
        protected readonly ?string $username,
        #[\SensitiveParameter]
        protected readonly ?string $password,
        protected readonly string $database,
        protected readonly int $port = 5432,
        /**
         * Persistent connection opt-in. When true, doConnect() calls
         * pg_pconnect() instead of pg_connect(), so the connection is
         * recycled from php-fpm's pool between requests. Default false
         * to match wpdb's per-request connection model; opt-in is
         * intended for WP-CLI workers and queue consumers where the TCP
         * + TLS handshake cost dominates.
         */
        protected readonly bool $persistent = false,
        /**
         * PostgreSQL search_path to set immediately after connect. Accepts
         * a single schema name or a comma-separated list (e.g.
         * 'tenant_42,public'). When null, the server-side default applies
         * — usually `"$user", public`. Doctrine DBAL doesn't expose this
         * knob; we do because WordPress multisite frequently wants to
         * isolate schemas per blog or per tenant.
         *
         * @var list<string>|null
         */
        protected readonly ?array $searchPath = null,
    ) {
        $this->ownsConnection = true;
    }

    /**
     * Wrap an existing PgSql connection.
     */
    public static function fromPostgreSQLConnection(\PgSql\Connection $connection): self
    {
        $driver = new self(host: '', username: null, password: null, database: '');
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
        return new PostgreSQLPlatform();
    }

    public function getQueryTranslator(): \WPPack\Component\Database\Translator\QueryTranslatorInterface
    {
        return new PostgreSQLQueryTranslator($this);
    }

    public function getNativeConnection(): mixed
    {
        return $this->connection;
    }

    public function quoteStringLiteral(string $value): string
    {
        $this->ensureConnected();

        $quoted = pg_escape_literal($this->connection, $value);
        if ($quoted === false) {
            throw new DriverException('Failed to quote string literal.');
        }

        return $quoted;
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

        // PHP's pg_connect() normally caches the native resource per
        // connection string — two Driver instances built with the same
        // DSN would otherwise share a single underlying handle, so
        // calling close() on one of them (tests, scripts rotating a
        // driver) would invalidate the other driver mid-query. Force
        // a fresh connection for non-persistent drivers so each
        // Driver::close() is isolated.
        $connection = $this->persistent
            ? @pg_pconnect($this->buildConnectionString())
            : @pg_connect($this->buildConnectionString(), \PGSQL_CONNECT_FORCE_NEW);

        if ($connection === false) {
            throw new ConnectionException('Failed to connect to PostgreSQL.');
        }

        $this->connection = $connection;

        $this->applySearchPath();
    }

    /**
     * Emit `SET search_path TO ...` when a schema list was configured.
     * Each entry is quoted as an identifier to accommodate names with
     * uppercase letters, reserved words, or non-ASCII characters.
     * Nothing runs when searchPath is null — PostgreSQL falls back to its
     * role / database default (`"$user", public`).
     *
     * Protected so the Aurora DSQL subclass (which overrides doConnect()
     * for IAM token refresh) can re-apply it after its own connect path.
     */
    protected function applySearchPath(): void
    {
        if ($this->searchPath === null || $this->searchPath === [] || $this->connection === null) {
            return;
        }

        $parts = array_map(
            static function (string $schema): string {
                // libpq treats identifiers as C-strings; a NUL byte truncates
                // silently server-side and the wrong schema gets selected.
                // Reject early rather than let the connection use a mangled
                // path.
                if (strpbrk($schema, "\0\n\r") !== false) {
                    throw new ConnectionException(
                        'search_path entries must not contain NUL / newline / CR characters.',
                    );
                }

                return '"' . str_replace('"', '""', $schema) . '"';
            },
            $this->searchPath,
        );

        $result = @pg_query($this->connection, 'SET search_path TO ' . implode(', ', $parts));
        if ($result === false) {
            throw new ConnectionException(\sprintf(
                'Failed to set search_path to "%s": %s',
                implode(', ', $this->searchPath),
                pg_last_error($this->connection) ?: 'unknown error',
            ));
        }
    }

    /**
     * Build the libpq connection string.
     *
     * Subclasses (e.g., AuroraDSQLDriver) can override to add SSL, sslnegotiation, etc.
     */
    protected function buildConnectionString(): string
    {
        $esc = static fn(string $v): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $v) . "'";

        // libpq falls back to $PGUSER / $PGPASSWORD (and .pgpass) when the
        // corresponding key is omitted. Keep nulls out of the conn string
        // so "not provided" and "explicit empty" stay distinguishable.
        $parts = [
            'host=' . $esc($this->host),
            'port=' . $this->port,
            'dbname=' . $esc($this->database),
        ];
        if ($this->username !== null) {
            $parts[] = 'user=' . $esc($this->username);
        }
        if ($this->password !== null) {
            $parts[] = 'password=' . $esc($this->password);
        }
        $parts[] = 'client_encoding=' . $esc('UTF8');

        return implode(' ', $parts);
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
            $pgResult = @pg_query_params($this->connection, $this->convertPlaceholders($sql), $this->normalizeZeroDateParams($params));
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
            $pgResult = @pg_query_params($this->connection, $this->convertPlaceholders($sql), $this->normalizeZeroDateParams($params));
        }

        if ($pgResult === false) {
            $this->throwQueryError();
        }

        $affected = pg_affected_rows($pgResult);
        pg_free_result($pgResult);

        return $affected;
    }

    /**
     * Coerce MySQL's "zero date" sentinels ('0000-00-00', '0000-00-00
     * 00:00:00') into the earliest value PostgreSQL accepts. The
     * translator already rewrites these when they appear as string
     * literals in the SQL text, but parameter-bound queries never
     * touch that path — the sentinels land in pg_query_params() and
     * PG rejects them as out-of-range. Keep this in sync with
     * postProcessPostgreSQL().
     *
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    private function normalizeZeroDateParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (!\is_string($value)) {
                continue;
            }
            if ($value === '0000-00-00 00:00:00') {
                $params[$key] = '0001-01-01 00:00:00';
            } elseif ($value === '0000-00-00') {
                $params[$key] = '0001-01-01';
            }
        }

        return $params;
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
            // Skip when the connection is already gone — PG servers clean up
            // prepared statements on their side when the session ends, so a
            // best-effort deallocate on a live connection is enough. The
            // error-suppression `@` used to hide even the 'connection
            // closed' case; now we gate on pg_connection_status first and
            // surface any other failure through the normal error channel.
            if (@pg_connection_status($conn) !== \PGSQL_CONNECTION_OK) {
                return;
            }

            pg_query($conn, "DEALLOCATE \"{$stmtName}\"");
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

    /**
     * @phpstan-assert !null $this->connection
     */
    protected function ensureConnected(): void
    {
        if ($this->connection === null) {
            $this->connect();
        }

        if ($this->connection === null) {
            throw new DriverException('Database connection is not established.');
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
