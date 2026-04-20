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

namespace WPPack\Component\Database\Driver;

use Psr\Log\LoggerInterface;
use WPPack\Component\Database\Exception\ConnectionException;
use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Platform\MariadbPlatform;
use WPPack\Component\Database\Platform\MySQLPlatform;
use WPPack\Component\Database\Platform\PlatformInterface;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;

class MySQLDriver extends AbstractDriver
{
    protected ?\mysqli $connection;
    protected bool $inTx = false;
    private ?PlatformInterface $platform = null;
    private bool $ownsConnection;

    public function __construct(
        protected readonly string $host,
        protected readonly string $username,
        #[\SensitiveParameter]
        protected readonly string $password,
        protected readonly string $database,
        protected readonly int $port = 3306,
        protected readonly ?string $socket = null,
        protected readonly string $charset = 'utf8mb4',
        protected readonly ?LoggerInterface $logger = null,
        /**
         * Persistent connection opt-in. Prefixing the host with `p:` is
         * mysqli's native idiom; we accept it both as a flag here and as
         * a literal `p:` in $host so either style works. Default false
         * to match standard wpdb behaviour (one fresh connection per
         * request); opt-in is intended for WP-CLI workers and queue
         * consumers where the TCP handshake cost dominates.
         */
        protected readonly bool $persistent = false,
    ) {
        $this->connection = null;
        $this->ownsConnection = true;
    }

    /**
     * Wrap an existing mysqli connection (e.g., from $wpdb->dbh).
     * No new connection is created.
     */
    public static function fromMySQLi(\mysqli $connection): self
    {
        $driver = new self(
            host: '',
            username: '',
            password: '',
            database: '',
        );
        $driver->connection = $connection;
        $driver->ownsConnection = false;

        return $driver;
    }

    public function getName(): string
    {
        return 'mysql';
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
        if ($this->platform === null) {
            $this->platform = $this->detectPlatform();
        }

        return $this->platform;
    }

    public function getNativeConnection(): \mysqli
    {
        $this->ensureConnected();

        return $this->connection;
    }

    public function quoteStringLiteral(string $value): string
    {
        $this->ensureConnected();

        return "'" . $this->connection->real_escape_string($value) . "'";
    }

    public function escapeStringContent(string $value): string
    {
        $this->ensureConnected();

        return $this->connection->real_escape_string($value);
    }

    /**
     * Set the session transaction isolation level. Aurora MySQL clusters
     * running under high concurrency usually want READ COMMITTED to avoid
     * gap-lock escalation from the InnoDB default REPEATABLE READ; WP core
     * does not set this explicitly so the cluster default leaks through.
     * Callers can opt in via a one-shot call just after connect().
     *
     * Values accepted (case-insensitive): READ UNCOMMITTED, READ COMMITTED,
     * REPEATABLE READ, SERIALIZABLE.
     */
    public function setIsolationLevel(string $level): void
    {
        $this->ensureConnected();

        $canonical = strtoupper(trim($level));
        $allowed = [
            'READ UNCOMMITTED',
            'READ COMMITTED',
            'REPEATABLE READ',
            'SERIALIZABLE',
        ];
        if (!\in_array($canonical, $allowed, true)) {
            throw new DriverException(
                'Unsupported transaction isolation level: ' . $level,
            );
        }

        if (!$this->connection->query("SET SESSION TRANSACTION ISOLATION LEVEL {$canonical}")) {
            $this->throwQueryError();
        }
    }

    protected function doConnect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        // mysqli's native persistent-connection syntax is the `p:` host
        // prefix. If the caller set persistent=true without also
        // prefixing, do it for them; if they already set it, leave
        // alone. Non-persistent path is unchanged.
        $host = $this->host;
        if ($this->persistent && !str_starts_with($host, 'p:')) {
            $host = 'p:' . $host;
        }

        $connection = new \mysqli(
            $host,
            $this->username,
            $this->password,
            $this->database,
            $this->port,
            $this->socket ?? '',
        );

        if ($connection->connect_error) {
            throw new ConnectionException($connection->connect_error);
        }

        $connection->set_charset($this->charset);
        $this->connection = $connection;

        $this->setCompatibleSqlMode();
        $this->verifyCharsetAlignment();
    }

    /**
     * Cross-check the charset / collation the server actually gave us
     * against what the driver asked for. Aurora clusters running a
     * mix of MySQL 5.7 and 8.0 can hand back `utf8mb4_0900_ai_ci`
     * (8.0 default) when `utf8mb4_unicode_ci` was requested, and the
     * sort order differs between the two — a silent collation
     * mismatch produces wrong-looking WHERE results. Log a warning
     * when divergence is detected so operators can pin the cluster
     * parameter group explicitly.
     */
    private function verifyCharsetAlignment(): void
    {
        if ($this->connection === null) {
            return;
        }

        $result = @$this->connection->query("SHOW VARIABLES WHERE Variable_name IN ('character_set_client', 'character_set_connection', 'collation_connection')");

        if (!$result instanceof \mysqli_result) {
            return;
        }

        $actual = [];
        while ($row = $result->fetch_assoc()) {
            $actual[(string) $row['Variable_name']] = (string) $row['Value'];
        }
        $result->free();

        $clientCharset = $actual['character_set_client'] ?? null;
        if ($clientCharset !== null && $clientCharset !== $this->charset) {
            $this->logger?->warning('MySQL character set mismatch', [
                'requested' => $this->charset,
                'actual' => $clientCharset,
                'variables' => $actual,
            ]);
        }
    }

    /**
     * Remove sql_mode flags incompatible with WordPress-style DDL/DML
     * (matches the behavior of wpdb::set_sql_mode()).
     */
    private function setCompatibleSqlMode(): void
    {
        if ($this->connection === null) {
            return;
        }

        $result = $this->connection->query('SELECT @@SESSION.sql_mode');

        if (!$result instanceof \mysqli_result) {
            return;
        }

        $row = $result->fetch_row();
        $result->free();

        if ($row === null || !isset($row[0])) {
            return;
        }

        $modes = array_filter(array_map('trim', explode(',', (string) $row[0])), static fn(string $m): bool => $m !== '');
        $incompatible = ['NO_ZERO_DATE', 'ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'TRADITIONAL', 'ANSI'];
        $modes = array_values(array_diff($modes, $incompatible));

        // Sql-mode names arrive from @@SESSION.sql_mode, which only ever
        // returns server-validated identifiers — no user input, no need to
        // escape. Keep the ' ... ' wrapping for syntax only.
        $this->connection->query(\sprintf("SET SESSION sql_mode = '%s'", implode(',', $modes)));
    }

    protected function doClose(): void
    {
        if ($this->connection !== null && $this->ownsConnection) {
            $this->connection->close();
        }

        $this->connection = null;
        $this->inTx = false;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $this->ensureConnected();

        if ($params === []) {
            try {
                $result = $this->connection->query($sql);
            } catch (\mysqli_sql_exception $e) {
                // Modern mysqli defaults to exception reporting mode, so
                // query() never returns false on error — it throws. Route
                // the exception through our gone-away detection path so
                // the handle is still dropped for errno 2006 / 2013.
                $this->throwQueryError($e);
            }

            if ($result === false) {
                $this->throwQueryError();
            }

            if ($result === true) {
                return new Result([], $this->connection->affected_rows);
            }

            /** @var list<array<string, mixed>> */
            $rows = $result->fetch_all(\MYSQLI_ASSOC);
            $result->free();

            return new Result($rows);
        }

        return $this->executePrepared($sql, $params, true);
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $this->ensureConnected();

        if ($params === []) {
            try {
                $result = $this->connection->query($sql);
            } catch (\mysqli_sql_exception $e) {
                $this->throwQueryError($e);
            }

            if ($result === false) {
                $this->throwQueryError();
            }

            return $this->connection->affected_rows;
        }

        $preparedResult = $this->executePrepared($sql, $params, false);

        return $preparedResult->rowCount();
    }

    /**
     * Raise a DriverException with the current mysqli error, dropping the
     * stale connection handle when the failure indicates the server-side
     * socket is gone. Code 2006 is "MySQL server has gone away" (typical on
     * long-idle WP-CLI workers that blow past wait_timeout); 2013 is "Lost
     * connection during query" (server forcibly killed us). In both cases
     * the mysqli handle is dead and every subsequent call will fail — we
     * null it so ensureConnected() opens a fresh socket on the next query.
     * We deliberately do not auto-retry the failed statement: a partially
     * applied write cannot be safely replayed without caller knowledge.
     */
    private function throwQueryError(?\mysqli_sql_exception $exception = null): never
    {
        // Pull the error metadata either from the raised exception (modern
        // mysqli_report(MYSQLI_REPORT_STRICT) mode) or the handle itself
        // (legacy false-return mode). Both paths need the same gone-away
        // cleanup.
        if ($exception !== null) {
            $errno = $exception->getCode();
            $message = $exception->getMessage();
        } else {
            $errno = $this->connection->errno;
            $message = $this->connection->error;
        }

        if ($errno === 2006 || $errno === 2013) {
            if ($this->ownsConnection && $this->connection !== null) {
                @$this->connection->close();
            }

            $this->connection = null;
            $this->inTx = false;
        }

        throw new DriverException($message, 0, $exception, $errno);
    }

    protected function doPrepare(string $sql): Statement
    {
        $this->ensureConnected();

        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            $this->throwQueryError();
        }

        $executeQuery = function (array $params) use ($stmt): Result {
            $this->bindAndExecute($stmt, $params);

            $result = $stmt->get_result();

            if ($result === false) {
                return new Result([], $stmt->affected_rows);
            }

            /** @var list<array<string, mixed>> */
            $rows = $result->fetch_all(\MYSQLI_ASSOC);
            $result->free();

            return new Result($rows);
        };

        $executeStatement = function (array $params) use ($stmt): int {
            $this->bindAndExecute($stmt, $params);

            return $stmt->affected_rows;
        };

        $close = static function () use ($stmt): void {
            $stmt->close();
        };

        return new Statement($executeQuery, $executeStatement, $close);
    }

    protected function doLastInsertId(): int
    {
        $this->ensureConnected();

        return (int) $this->connection->insert_id;
    }

    protected function doBeginTransaction(): void
    {
        $this->ensureConnected();

        if (!$this->connection->begin_transaction()) {
            throw new DriverException($this->connection->error);
        }

        $this->inTx = true;
    }

    protected function doCommit(): void
    {
        $this->ensureConnected();

        if (!$this->connection->commit()) {
            throw new DriverException($this->connection->error);
        }

        $this->inTx = false;
    }

    protected function doRollBack(): void
    {
        $this->ensureConnected();

        if (!$this->connection->rollback()) {
            throw new DriverException($this->connection->error);
        }

        $this->inTx = false;
    }

    private function ensureConnected(): void
    {
        if ($this->connection === null) {
            $this->connect();
        }
    }

    private function detectPlatform(): PlatformInterface
    {
        if ($this->connection === null) {
            return new MySQLPlatform();
        }

        $serverInfo = $this->connection->server_info;

        if (stripos($serverInfo, 'MariaDB') !== false) {
            return new MariadbPlatform();
        }

        return new MySQLPlatform();
    }

    /**
     * @param list<mixed> $params
     */
    private function executePrepared(string $sql, array $params, bool $fetchRows): Result
    {
        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            $this->throwQueryError();
        }

        try {
            $this->bindAndExecute($stmt, $params);

            if ($fetchRows) {
                $result = $stmt->get_result();

                if ($result === false) {
                    return new Result([], $stmt->affected_rows);
                }

                /** @var list<array<string, mixed>> */
                $rows = $result->fetch_all(\MYSQLI_ASSOC);
                $result->free();

                return new Result($rows);
            }

            return new Result([], $stmt->affected_rows);
        } finally {
            $stmt->close();
        }
    }

    /**
     * @param list<mixed> $params
     */
    private function bindAndExecute(\mysqli_stmt $stmt, array $params): void
    {
        if ($params !== []) {
            $types = '';

            foreach ($params as $param) {
                $types .= match (true) {
                    \is_int($param) => 'i',
                    \is_float($param) => 'd',
                    \is_string($param) => 's',
                    default => 's',
                };
            }

            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new DriverException($stmt->error);
        }
    }
}
