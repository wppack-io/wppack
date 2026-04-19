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

use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Platform\PlatformInterface;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;
use WPPack\Component\Database\Translator\NullQueryTranslator;
use WPPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Template method base class for database drivers.
 *
 * Subclasses implement the do* methods. The public methods wrap them
 * with unified exception handling.
 */
abstract class AbstractDriver implements DriverInterface
{
    abstract public function getName(): string;

    abstract protected function doConnect(): void;

    abstract protected function doClose(): void;

    /** @param list<mixed> $params */
    abstract protected function doExecuteQuery(string $sql, array $params = []): Result;

    /** @param list<mixed> $params */
    abstract protected function doExecuteStatement(string $sql, array $params = []): int;

    abstract protected function doPrepare(string $sql): Statement;

    abstract protected function doLastInsertId(): int;

    abstract protected function doBeginTransaction(): void;

    abstract protected function doCommit(): void;

    abstract protected function doRollBack(): void;

    abstract public function inTransaction(): bool;

    abstract public function isConnected(): bool;

    abstract public function getPlatform(): PlatformInterface;

    abstract public function getNativeConnection(): mixed;

    /**
     * Every driver must supply its own string-literal quoting. The previous
     * addslashes() fallback silently produced MySQL-shaped output on other
     * engines, which is a footgun for new drivers that forget to override.
     * Making this abstract surfaces the miss at class-load time instead of
     * at data-corruption time.
     */
    abstract public function quoteStringLiteral(string $value): string;

    /**
     * Default implementation escapes a value for splicing into a single-quoted
     * literal by doubling embedded single quotes (SQL-92 conforming form).
     * This is safe for SQLite, PostgreSQL with standard_conforming_strings=on,
     * and any other engine that honours doubled-quote escaping. Drivers that
     * need a different wire escape (MySQL backslash form, pg_escape_string
     * context-aware) override this method.
     */
    public function escapeStringContent(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * Default: NullQueryTranslator (passthrough).
     * Override in Bridge drivers for non-MySQL engines.
     */
    public function getQueryTranslator(): QueryTranslatorInterface
    {
        return new NullQueryTranslator();
    }

    public function connect(): void
    {
        $this->execute(fn() => $this->doConnect());
    }

    public function close(): void
    {
        $this->execute(fn() => $this->doClose());
    }

    public function executeQuery(string $sql, array $params = []): Result
    {
        return $this->execute(fn(): Result => $this->doExecuteQuery($sql, $params));
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        return $this->execute(fn(): int => $this->doExecuteStatement($sql, $params));
    }

    public function prepare(string $sql): Statement
    {
        return $this->execute(fn(): Statement => $this->doPrepare($sql));
    }

    public function lastInsertId(): int
    {
        return $this->execute(fn(): int => $this->doLastInsertId());
    }

    public function beginTransaction(): void
    {
        $this->execute(fn() => $this->doBeginTransaction());
    }

    public function commit(): void
    {
        $this->execute(fn() => $this->doCommit());
    }

    public function rollBack(): void
    {
        $this->execute(fn() => $this->doRollBack());
    }

    /**
     * @template T
     * @param \Closure(): T $operation
     * @return T
     */
    protected function execute(\Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (DriverException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DriverException($e->getMessage(), 0, $e);
        }
    }
}
