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

use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Platform\MariadbPlatform;
use WpPack\Component\Database\Platform\MysqlPlatform;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

class MysqlDriver extends AbstractDriver
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
    ) {
        $this->connection = null;
        $this->ownsConnection = true;
    }

    /**
     * Wrap an existing mysqli connection (e.g., from $wpdb->dbh).
     * No new connection is created.
     */
    public static function fromMysqli(\mysqli $connection): self
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

    protected function doConnect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $connection = new \mysqli(
            $this->host,
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

        $this->connection->query(\sprintf("SET SESSION sql_mode = '%s'", $this->connection->real_escape_string(implode(',', $modes))));
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
            $result = $this->connection->query($sql);

            if ($result === false) {
                throw new DriverException($this->connection->error);
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
            $result = $this->connection->query($sql);

            if ($result === false) {
                throw new DriverException($this->connection->error);
            }

            return $this->connection->affected_rows;
        }

        $preparedResult = $this->executePrepared($sql, $params, false);

        return $preparedResult->rowCount();
    }

    protected function doPrepare(string $sql): Statement
    {
        $this->ensureConnected();

        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            throw new DriverException($this->connection->error);
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
            return new MysqlPlatform();
        }

        $serverInfo = $this->connection->server_info;

        if (stripos($serverInfo, 'MariaDB') !== false) {
            return new MariadbPlatform();
        }

        return new MysqlPlatform();
    }

    /**
     * @param list<mixed> $params
     */
    private function executePrepared(string $sql, array $params, bool $fetchRows): Result
    {
        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            throw new DriverException($this->connection->error);
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
