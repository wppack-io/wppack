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
use WpPack\Component\Database\DatabaseEngine;

/**
 * Integration tests for PgsqlDriver.
 *
 * Requires a running PostgreSQL instance. Skipped when WPPACK_TEST_PGSQL_HOST
 * is not set (local development without PostgreSQL).
 */
final class PgsqlDriverTest extends TestCase
{
    private ?PgsqlDriver $driver = null;

    protected function setUp(): void
    {
        $host = $_SERVER['WPPACK_TEST_PGSQL_HOST'] ?? $_ENV['WPPACK_TEST_PGSQL_HOST'] ?? '';

        if ($host === '') {
            self::markTestSkipped('PostgreSQL not available (set WPPACK_TEST_PGSQL_HOST).');
        }

        $this->driver = new PgsqlDriver(
            host: $host,
            username: $_SERVER['WPPACK_TEST_PGSQL_USER'] ?? $_ENV['WPPACK_TEST_PGSQL_USER'] ?? 'wppack',
            password: $_SERVER['WPPACK_TEST_PGSQL_PASSWORD'] ?? $_ENV['WPPACK_TEST_PGSQL_PASSWORD'] ?? 'wppack',
            database: $_SERVER['WPPACK_TEST_PGSQL_DATABASE'] ?? $_ENV['WPPACK_TEST_PGSQL_DATABASE'] ?? 'wppack_test',
            port: (int) ($_SERVER['WPPACK_TEST_PGSQL_PORT'] ?? $_ENV['WPPACK_TEST_PGSQL_PORT'] ?? '5432'),
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
        self::assertSame(DatabaseEngine::PostgreSQL, $this->driver->getPlatform()->getEngine());
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
    public function queryTranslator(): void
    {
        $translator = $this->driver->getQueryTranslator();

        self::assertInstanceOf(
            \WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator::class,
            $translator,
        );
    }
}
