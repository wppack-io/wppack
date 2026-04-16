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

namespace WpPack\Component\Database\Bridge\Pgsql\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Dsn\Dsn;

/**
 * Integration tests for PgsqlDriver.
 *
 * Activates only when DATABASE_DSN points to a PostgreSQL backend
 * (e.g. pgsql://wppack:wppack@127.0.0.1:5432/wppack_test). Skipped
 * otherwise so local development without PostgreSQL is unaffected.
 */
final class PgsqlDriverTest extends TestCase
{
    private ?PgsqlDriver $driver = null;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';

        if (!is_string($dsn) || !str_starts_with($dsn, 'pgsql:')) {
            self::markTestSkipped('Requires DATABASE_DSN=pgsql:... (got: ' . ($dsn === '' ? '(unset)' : $dsn) . ')');
        }

        $parsed = Dsn::fromString($dsn);

        $this->driver = new PgsqlDriver(
            host: $parsed->getHost() ?? '127.0.0.1',
            username: $parsed->getUser() ?? 'wppack',
            password: $parsed->getPassword() ?? '',
            database: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            port: $parsed->getPort() ?? 5432,
        );

        $this->driver->connect();
        $this->driver->executeStatement('DROP TABLE IF EXISTS wppack_test_table');
        $this->driver->executeStatement('CREATE TABLE wppack_test_table (id SERIAL PRIMARY KEY, name TEXT, data BYTEA)');
    }

    protected function tearDown(): void
    {
        if ($this->driver !== null && $this->driver->isConnected()) {
            $this->driver->executeStatement('DROP TABLE IF EXISTS wppack_test_table');
            $this->driver->close();
        }
    }

    #[Test]
    public function nameAndPlatform(): void
    {
        self::assertSame('pgsql', $this->driver->getName());
        self::assertSame('pgsql', $this->driver->getPlatform()->getEngine());
    }

    #[Test]
    public function connectAndIsConnected(): void
    {
        self::assertTrue($this->driver->isConnected());
    }

    #[Test]
    public function executeStatementAndQuery(): void
    {
        $this->driver->executeStatement("INSERT INTO wppack_test_table (name) VALUES ('Alice')");
        $this->driver->executeStatement("INSERT INTO wppack_test_table (name) VALUES ('Bob')");

        $result = $this->driver->executeQuery('SELECT * FROM wppack_test_table ORDER BY id');
        $rows = $result->fetchAllAssociative();

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    #[Test]
    public function executeWithParams(): void
    {
        $this->driver->executeStatement('INSERT INTO wppack_test_table (name) VALUES (?)', ['Charlie']);

        $result = $this->driver->executeQuery('SELECT name FROM wppack_test_table WHERE name = ?', ['Charlie']);

        self::assertSame('Charlie', $result->fetchAssociative()['name']);
    }

    #[Test]
    public function preparedStatement(): void
    {
        $stmt = $this->driver->prepare('INSERT INTO wppack_test_table (name) VALUES (?)');
        $stmt->executeStatement(['Dave']);
        $stmt->executeStatement(['Eve']);
        $stmt->close();

        $result = $this->driver->executeQuery('SELECT COUNT(*) AS cnt FROM wppack_test_table');

        self::assertSame(2, (int) $result->fetchOne());
    }

    #[Test]
    public function transaction(): void
    {
        self::assertFalse($this->driver->inTransaction());

        $this->driver->beginTransaction();

        self::assertTrue($this->driver->inTransaction());

        $this->driver->executeStatement("INSERT INTO wppack_test_table (name) VALUES ('Frank')");
        $this->driver->commit();

        self::assertFalse($this->driver->inTransaction());

        $result = $this->driver->executeQuery('SELECT COUNT(*) FROM wppack_test_table');

        self::assertSame(1, (int) $result->fetchOne());
    }

    #[Test]
    public function rollBack(): void
    {
        $this->driver->beginTransaction();
        $this->driver->executeStatement("INSERT INTO wppack_test_table (name) VALUES ('Grace')");
        $this->driver->rollBack();

        $result = $this->driver->executeQuery('SELECT COUNT(*) FROM wppack_test_table');

        self::assertSame(0, (int) $result->fetchOne());
    }

    #[Test]
    public function lastInsertId(): void
    {
        $this->driver->executeStatement("INSERT INTO wppack_test_table (name) VALUES ('Heidi')");

        self::assertSame(1, $this->driver->lastInsertId());
    }

    #[Test]
    public function nativeConnection(): void
    {
        self::assertInstanceOf(\PgSql\Connection::class, $this->driver->getNativeConnection());
    }

    #[Test]
    public function quoteStringLiteralWrapsAndEscapes(): void
    {
        self::assertSame("'hello'", $this->driver->quoteStringLiteral('hello'));
        // PostgreSQL pg_escape_literal doubles embedded single quotes.
        self::assertSame("'O''Brien'", $this->driver->quoteStringLiteral("O'Brien"));
    }

    #[Test]
    public function quoteStringLiteralHandlesEmptyString(): void
    {
        self::assertSame("''", $this->driver->quoteStringLiteral(''));
    }

    #[Test]
    public function quoteStringLiteralHandlesBackslash(): void
    {
        // pg_escape_literal returns the E-prefixed escape-string form
        // (` E'a\\b'`) when the input contains a backslash so the literal
        // stays valid regardless of standard_conforming_strings. Leading
        // whitespace is intentional — it separates the literal from an
        // adjacent identifier when spliced into SQL.
        $quoted = $this->driver->quoteStringLiteral('a\\b');

        self::assertMatchesRegularExpression("/^\\s*E?'.*'$/", $quoted);
        self::assertStringContainsString('a\\', $quoted);
    }

    #[Test]
    public function queryTranslator(): void
    {
        $translator = $this->driver->getQueryTranslator();

        self::assertInstanceOf(
            \WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator::class,
            $translator,
        );
    }
}
