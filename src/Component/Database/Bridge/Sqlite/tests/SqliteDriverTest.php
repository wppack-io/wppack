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

namespace WpPack\Component\Database\Bridge\Sqlite\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Sqlite\SqliteDriver;
use WpPack\Component\Database\Bridge\Sqlite\SqliteDriverFactory;
use WpPack\Component\Dsn\Dsn;

final class SqliteDriverTest extends TestCase
{
    private SqliteDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new SqliteDriver(':memory:');
        $this->driver->connect();
        $this->driver->executeStatement('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, data BLOB)');
    }

    protected function tearDown(): void
    {
        $this->driver->close();
    }

    #[Test]
    public function nameAndPlatform(): void
    {
        self::assertSame('sqlite', $this->driver->getName());
        self::assertSame('sqlite', $this->driver->getPlatform()->getEngine());
    }

    #[Test]
    public function connectAndIsConnected(): void
    {
        $driver = new SqliteDriver(':memory:');

        self::assertFalse($driver->isConnected());

        $driver->connect();

        self::assertTrue($driver->isConnected());

        $driver->close();

        self::assertFalse($driver->isConnected());
    }

    #[Test]
    public function executeStatementAndQuery(): void
    {
        $this->driver->executeStatement("INSERT INTO test (name) VALUES ('Alice')");
        $this->driver->executeStatement("INSERT INTO test (name) VALUES ('Bob')");

        $result = $this->driver->executeQuery('SELECT * FROM test ORDER BY id');
        $rows = $result->fetchAllAssociative();

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    #[Test]
    public function executeWithParams(): void
    {
        $this->driver->executeStatement('INSERT INTO test (name) VALUES (?)', ['Charlie']);

        $result = $this->driver->executeQuery('SELECT name FROM test WHERE name = ?', ['Charlie']);

        self::assertSame('Charlie', $result->fetchAssociative()['name']);
    }

    #[Test]
    public function preparedStatement(): void
    {
        $stmt = $this->driver->prepare('INSERT INTO test (name) VALUES (?)');
        $stmt->executeStatement(['Dave']);
        $stmt->executeStatement(['Eve']);
        $stmt->close();

        $result = $this->driver->executeQuery('SELECT COUNT(*) AS cnt FROM test');

        self::assertSame(2, (int) $result->fetchOne());
    }

    #[Test]
    public function preparedStatementWithBindValue(): void
    {
        $stmt = $this->driver->prepare('INSERT INTO test (name) VALUES (?)');
        $stmt->bindValue(1, 'Frank');
        $stmt->executeStatement();
        $stmt->close();

        $result = $this->driver->executeQuery('SELECT name FROM test');

        self::assertSame('Frank', $result->fetchOne());
    }

    #[Test]
    public function lastInsertId(): void
    {
        $this->driver->executeStatement("INSERT INTO test (name) VALUES ('Grace')");

        self::assertSame(1, $this->driver->lastInsertId());

        $this->driver->executeStatement("INSERT INTO test (name) VALUES ('Heidi')");

        self::assertSame(2, $this->driver->lastInsertId());
    }

    #[Test]
    public function transaction(): void
    {
        self::assertFalse($this->driver->inTransaction());

        $this->driver->beginTransaction();

        self::assertTrue($this->driver->inTransaction());

        $this->driver->executeStatement("INSERT INTO test (name) VALUES ('Ivan')");
        $this->driver->commit();

        self::assertFalse($this->driver->inTransaction());

        $result = $this->driver->executeQuery('SELECT COUNT(*) FROM test');

        self::assertSame(1, (int) $result->fetchOne());
    }

    #[Test]
    public function rollBack(): void
    {
        $this->driver->beginTransaction();
        $this->driver->executeStatement("INSERT INTO test (name) VALUES ('Judy')");
        $this->driver->rollBack();

        $result = $this->driver->executeQuery('SELECT COUNT(*) FROM test');

        self::assertSame(0, (int) $result->fetchOne());
    }

    #[Test]
    public function nativeConnection(): void
    {
        self::assertInstanceOf(\PDO::class, $this->driver->getNativeConnection());
    }

    #[Test]
    public function quoteStringLiteralWrapsAndEscapes(): void
    {
        self::assertSame("'hello'", $this->driver->quoteStringLiteral('hello'));
        // SQLite uses doubled-quote escaping for embedded single quotes.
        self::assertSame("'O''Brien'", $this->driver->quoteStringLiteral("O'Brien"));
    }

    #[Test]
    public function quoteStringLiteralHandlesEmptyString(): void
    {
        self::assertSame("''", $this->driver->quoteStringLiteral(''));
    }

    #[Test]
    public function quoteStringLiteralHandlesNullByte(): void
    {
        // PDO::quote preserves null bytes inside the quoted literal.
        $quoted = $this->driver->quoteStringLiteral("a\x00b");

        self::assertStringStartsWith("'", $quoted);
        self::assertStringEndsWith("'", $quoted);
    }

    #[Test]
    public function fromPdo(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $driver = SqliteDriver::fromPdo($pdo);

        self::assertTrue($driver->isConnected());
        self::assertSame($pdo, $driver->getNativeConnection());
    }

    // ── Factory ──

    #[Test]
    public function factorySupportsSchemes(): void
    {
        $factory = new SqliteDriverFactory();

        self::assertTrue($factory->supports(Dsn::fromString('sqlite:///path')));
        self::assertTrue($factory->supports(Dsn::fromString('sqlite3:///path')));
        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
    }

    #[Test]
    public function factoryCreatesDriver(): void
    {
        $factory = new SqliteDriverFactory();
        $driver = $factory->create(Dsn::fromString('sqlite:///:memory:'));

        self::assertInstanceOf(SqliteDriver::class, $driver);
    }

    #[Test]
    public function factoryDefinitions(): void
    {
        $defs = SqliteDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('sqlite', $defs[0]->scheme);
    }
}
