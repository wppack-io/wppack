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

namespace WPPack\Component\Database\Bridge\Sqlite;

use WPPack\Component\Database\Bridge\Sqlite\SqlitePlatform;
use WPPack\Component\Database\Driver\AbstractDriver;
use WPPack\Component\Database\Exception\ConnectionException;
use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Platform\PlatformInterface;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;

final class SqliteDriver extends AbstractDriver
{
    private ?\Pdo\Sqlite $pdo;
    private bool $inTx = false;
    private bool $ownsConnection;

    public function __construct(
        private readonly string $path,
    ) {
        $this->pdo = null;
        $this->ownsConnection = true;
    }

    /**
     * Wrap an existing Pdo\Sqlite connection.
     */
    public static function fromPdo(\Pdo\Sqlite $pdo): self
    {
        $driver = new self(':memory:');
        $driver->pdo = $pdo;
        $driver->ownsConnection = false;
        $driver->registerFunctions($pdo);

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

    public function getQueryTranslator(): \WPPack\Component\Database\Translator\QueryTranslatorInterface
    {
        return new \WPPack\Component\Database\Bridge\Sqlite\Translator\SqliteQueryTranslator();
    }

    public function getNativeConnection(): ?\PDO
    {
        return $this->pdo;
    }

    public function quoteStringLiteral(string $value): string
    {
        $this->ensureConnected();

        // PDO::quote historically returned false for unsupported input (most
        // notably embedded NUL bytes on some SQLite builds). PHP 8.5 changed
        // the contract so the same cases raise PDOException instead. Some
        // third-party PDO builds raise a bare \Error (e.g. ValueError on
        // PHP 8.4 in strict mode). Catch \Throwable so every runtime ends
        // up with a uniform DriverException.
        try {
            $quoted = $this->pdo->quote($value);
        } catch (\Throwable $e) {
            throw new DriverException($e->getMessage(), 0, $e);
        }

        if ($quoted === false) {
            throw new DriverException('PDO::quote() is not supported by the current SQLite PDO driver');
        }

        return $quoted;
    }

    public function escapeStringContent(string $value): string
    {
        // SQLite's canonical escape inside a single-quoted literal is simply
        // doubling the quote. We don't call PDO::quote() and strip its outer
        // quotes because doing so would require an open connection just to
        // do a pure string transform.
        return str_replace("'", "''", $value);
    }

    protected function doConnect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            // Pdo\Sqlite is native from PHP 8.4 and polyfilled by
            // symfony/polyfill-php84 on 8.2–8.3. Its createFunction() method
            // replaces PDO::sqliteCreateFunction() which is deprecated in 8.5.
            $this->pdo = new \Pdo\Sqlite('sqlite:' . $this->path);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS _mysql_data_types_cache ('
                . '"table" TEXT NOT NULL, '
                . '"column_or_index" TEXT NOT NULL, '
                . '"mysql_type" TEXT NOT NULL, '
                . 'PRIMARY KEY("table", "column_or_index"))');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS _wppack_locks ('
                . 'lock_name TEXT PRIMARY KEY, '
                . 'lock_time INTEGER NOT NULL)');
            $this->registerFunctions($this->pdo);
        } catch (\PDOException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Register MySQL-compatible user-defined functions for SQLite.
     *
     * Uses Pdo\Sqlite::createFunction() — native on PHP 8.4+, polyfilled by
     * symfony/polyfill-php84 on 8.2–8.3 — so we never touch
     * PDO::sqliteCreateFunction() deprecated in 8.5.
     */
    private function registerFunctions(\Pdo\Sqlite $pdo): void
    {
        $pdo->createFunction('REGEXP', static function (?string $pattern, ?string $value): int {
            if ($pattern === null || $value === null) {
                return 0;
            }

            return @preg_match('/' . str_replace('/', '\\/', $pattern) . '/iu', $value) ? 1 : 0;
        }, 2);

        $pdo->createFunction('CONCAT', static function (...$args): string {
            return implode('', array_map(static fn($v) => (string) ($v ?? ''), $args));
        });

        $pdo->createFunction('CONCAT_WS', static function (string $separator, ...$args): string {
            return implode($separator, array_filter(array_map(static fn($v) => $v === null ? null : (string) $v, $args), static fn($v) => $v !== null));
        });

        $pdo->createFunction('CHAR_LENGTH', static function (?string $value): int {
            return $value === null ? 0 : mb_strlen($value);
        }, 1);

        $pdo->createFunction('FIELD', static function (mixed $search, ...$values): int {
            foreach ($values as $i => $val) {
                if ((string) $val === (string) $search) {
                    return $i + 1;
                }
            }

            return 0;
        });

        $pdo->createFunction('MD5', static function (?string $value): ?string {
            return $value === null ? null : md5($value);
        }, 1);

        $pdo->createFunction('LOG', static function (null|int|float|string ...$args): null|float {
            if ($args === [] || $args[0] === null) {
                return null;
            }

            if (\count($args) === 1) {
                return log((float) $args[0]);
            }

            // LOG(base, value)
            $base = (float) $args[0];
            $value = (float) ($args[1] ?? 0);

            return $base > 0 && $base !== 1.0 && $value > 0
                ? log($value) / log($base)
                : null;
        });

        $pdo->createFunction('UNHEX', static function (?string $hex): ?string {
            if ($hex === null) {
                return null;
            }

            $result = @hex2bin($hex);

            return $result === false ? null : $result;
        }, 1);

        $pdo->createFunction('FROM_BASE64', static function (?string $str): ?string {
            if ($str === null) {
                return null;
            }

            $result = base64_decode($str, true);

            return $result === false ? null : $result;
        }, 1);

        $pdo->createFunction('TO_BASE64', static function (?string $str): ?string {
            return $str === null ? null : base64_encode($str);
        }, 1);

        $pdo->createFunction('INET_ATON', static function (?string $ip): ?int {
            if ($ip === null) {
                return null;
            }

            $long = ip2long($ip);

            if ($long === false) {
                return null;
            }

            // Ensure unsigned result (ip2long returns signed on 32-bit PHP)
            return $long < 0 ? (int) sprintf('%u', $long) : $long;
        }, 1);

        $pdo->createFunction('INET_NTOA', static function (null|int|float|string $num): ?string {
            if ($num === null) {
                return null;
            }

            return long2ip((int) $num);
        }, 1);

        // Note: CHECK → INSERT is not atomic across processes (TOCTOU).
        // Acceptable for SQLite's typical single-server/dev usage.
        $pdo->createFunction('GET_LOCK', static function (?string $name, ?int $timeout) use ($pdo): int {
            if ($name === null) {
                return 0;
            }

            // Re-entrant: if already held, return 1 (matches MySQL 8.0 behaviour)
            $check = $pdo->prepare('SELECT COUNT(*) FROM _wppack_locks WHERE lock_name = ?');
            $check->execute([$name]);
            if ($check->fetchColumn()) {
                return 1;
            }

            $stmt = $pdo->prepare('INSERT OR IGNORE INTO _wppack_locks (lock_name, lock_time) VALUES (?, strftime(\'%s\', \'now\'))');
            $stmt->execute([$name]);

            return (int) (bool) $pdo->query('SELECT changes()')->fetchColumn();
        }, 2);

        $pdo->createFunction('RELEASE_LOCK', static function (?string $name) use ($pdo): int {
            if ($name === null) {
                return 0;
            }

            $stmt = $pdo->prepare('DELETE FROM _wppack_locks WHERE lock_name = ?');
            $stmt->execute([$name]);

            return (int) (bool) $pdo->query('SELECT changes()')->fetchColumn();
        }, 1);

        $pdo->createFunction('IS_FREE_LOCK', static function (?string $name) use ($pdo): int {
            if ($name === null) {
                return 1;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM _wppack_locks WHERE lock_name = ?');
            $stmt->execute([$name]);

            return $stmt->fetchColumn() ? 0 : 1;
        }, 1);

        // Note: ISNULL is not registered as UDF because SQLite uses ISNULL as a
        // postfix operator (expr ISNULL). The translator transforms ISNULL(x) → (x IS NULL).
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
