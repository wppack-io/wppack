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

use WpPack\Component\Database\Driver\AbstractDriver;
use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Bridge\Pgsql\PostgresqlPlatform;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

final class PgsqlDriver extends AbstractDriver
{
    /** @var \PgSql\Connection|null */
    private mixed $connection = null;
    private bool $inTx = false;
    private bool $ownsConnection;
    private int $stmtCounter = 0;

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        #[\SensitiveParameter]
        private readonly string $password,
        private readonly string $database,
        private readonly int $port = 5432,
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
        return new \WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator();
    }

    public function getNativeConnection(): mixed
    {
        return $this->connection;
    }

    protected function doConnect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $connStr = \sprintf(
            'host=%s port=%d dbname=%s user=%s password=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->username,
            $this->password,
        );

        $connection = @pg_connect($connStr);

        if ($connection === false) {
            throw new ConnectionException('Failed to connect to PostgreSQL.');
        }

        $this->connection = $connection;
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
            throw new DriverException((string) pg_last_error($this->connection));
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
            throw new DriverException((string) pg_last_error($this->connection));
        }

        $affected = pg_affected_rows($pgResult);
        pg_free_result($pgResult);

        return $affected;
    }

    protected function doPrepare(string $sql): Statement
    {
        $this->ensureConnected();

        $stmtName = 'wppack_stmt_' . (++$this->stmtCounter);
        $pgSql = $this->convertPlaceholders($sql);

        $result = @pg_prepare($this->connection, $stmtName, $pgSql);

        if ($result === false) {
            throw new DriverException((string) pg_last_error($this->connection));
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

    private function ensureConnected(): void
    {
        if ($this->connection === null) {
            $this->connect();
        }
    }

    /**
     * Convert ? positional placeholders to PostgreSQL $1, $2, ... format.
     */
    private function convertPlaceholders(string $sql): string
    {
        $index = 0;

        return (string) preg_replace_callback('/\?/', static function () use (&$index): string {
            return '$' . (++$index);
        }, $sql);
    }
}
