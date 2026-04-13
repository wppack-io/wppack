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

namespace WpPack\Component\Database\Bridge\Sqlite;

use WpPack\Component\Database\Driver\AbstractDriver;
use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Bridge\Sqlite\SqlitePlatform;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

final class SqliteDriver extends AbstractDriver
{
    private ?\PDO $pdo;
    private bool $inTx = false;
    private bool $ownsConnection;

    public function __construct(
        private readonly string $path,
    ) {
        $this->pdo = null;
        $this->ownsConnection = true;
    }

    /**
     * Wrap an existing PDO connection (e.g., from WP_SQLite_DB).
     */
    public static function fromPdo(\PDO $pdo): self
    {
        $driver = new self(':memory:');
        $driver->pdo = $pdo;
        $driver->ownsConnection = false;

        return $driver;
    }

    public function getName(): string
    {
        return 'sqlite';
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }

    public function getPlatform(): PlatformInterface
    {
        return new SqlitePlatform();
    }

    public function getQueryTranslator(): \WpPack\Component\Database\Translator\QueryTranslatorInterface
    {
        return new \WpPack\Component\Database\Bridge\Sqlite\Translator\SqliteQueryTranslator();
    }

    public function getNativeConnection(): ?\PDO
    {
        return $this->pdo;
    }

    protected function doConnect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            $this->pdo = new \PDO('sqlite:' . $this->path);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
            $this->registerFunctions($this->pdo);
        } catch (\PDOException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Register MySQL-compatible user-defined functions for SQLite.
     */
    private function registerFunctions(\PDO $pdo): void
    {
        $pdo->sqliteCreateFunction('REGEXP', static function (?string $pattern, ?string $value): int {
            if ($pattern === null || $value === null) {
                return 0;
            }

            return @preg_match('/' . str_replace('/', '\\/', $pattern) . '/iu', $value) ? 1 : 0;
        }, 2);

        $pdo->sqliteCreateFunction('CONCAT', static function (...$args): string {
            return implode('', array_map(static fn($v) => (string) ($v ?? ''), $args));
        });

        $pdo->sqliteCreateFunction('CONCAT_WS', static function (string $separator, ...$args): string {
            return implode($separator, array_filter(array_map(static fn($v) => $v === null ? null : (string) $v, $args), static fn($v) => $v !== null));
        });

        $pdo->sqliteCreateFunction('CHAR_LENGTH', static function (?string $value): int {
            return $value === null ? 0 : mb_strlen($value);
        }, 1);

        $pdo->sqliteCreateFunction('FIELD', static function (mixed $search, ...$values): int {
            foreach ($values as $i => $val) {
                if ((string) $val === (string) $search) {
                    return $i + 1;
                }
            }

            return 0;
        });
    }

    protected function doClose(): void
    {
        if ($this->ownsConnection) {
            $this->pdo = null;
        }

        $this->inTx = false;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $this->ensureConnected();

        try {
            if ($params === []) {
                $pdoStmt = $this->pdo->query($sql);
            } else {
                $pdoStmt = $this->pdo->prepare($sql);
                $pdoStmt->execute($params);
            }

            /** @var list<array<string, mixed>> */
            $rows = $pdoStmt->fetchAll(\PDO::FETCH_ASSOC);

            return new Result($rows);
        } catch (\PDOException $e) {
            throw new DriverException($e->getMessage(), 0, $e);
        }
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $this->ensureConnected();

        try {
            if ($params === []) {
                $affected = $this->pdo->exec($sql);

                return $affected === false ? 0 : $affected;
            }

            $pdoStmt = $this->pdo->prepare($sql);
            $pdoStmt->execute($params);

            return $pdoStmt->rowCount();
        } catch (\PDOException $e) {
            throw new DriverException($e->getMessage(), 0, $e);
        }
    }

    protected function doPrepare(string $sql): Statement
    {
        $this->ensureConnected();

        try {
            $pdoStmt = $this->pdo->prepare($sql);
        } catch (\PDOException $e) {
            throw new DriverException($e->getMessage(), 0, $e);
        }

        $executeQuery = static function (array $params) use ($pdoStmt): Result {
            try {
                $pdoStmt->execute($params !== [] ? $params : null);

                /** @var list<array<string, mixed>> */
                $rows = $pdoStmt->fetchAll(\PDO::FETCH_ASSOC);

                return new Result($rows);
            } catch (\PDOException $e) {
                throw new DriverException($e->getMessage(), 0, $e);
            }
        };

        $executeStatement = static function (array $params) use ($pdoStmt): int {
            try {
                $pdoStmt->execute($params !== [] ? $params : null);

                return $pdoStmt->rowCount();
            } catch (\PDOException $e) {
                throw new DriverException($e->getMessage(), 0, $e);
            }
        };

        $close = static function () use ($pdoStmt): void {
            $pdoStmt->closeCursor();
        };

        return new Statement($executeQuery, $executeStatement, $close);
    }

    protected function doLastInsertId(): int
    {
        $this->ensureConnected();

        return (int) $this->pdo->lastInsertId();
    }

    protected function doBeginTransaction(): void
    {
        $this->ensureConnected();
        $this->pdo->beginTransaction();
        $this->inTx = true;
    }

    protected function doCommit(): void
    {
        $this->ensureConnected();
        $this->pdo->commit();
        $this->inTx = false;
    }

    protected function doRollBack(): void
    {
        $this->ensureConnected();
        $this->pdo->rollBack();
        $this->inTx = false;
    }

    private function ensureConnected(): void
    {
        if ($this->pdo === null) {
            $this->connect();
        }
    }
}
