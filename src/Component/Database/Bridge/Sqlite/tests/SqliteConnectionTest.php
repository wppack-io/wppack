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
use WpPack\Component\Database\Connection;

/**
 * Integration tests for Connection + SqliteDriver with real prepared statements.
 */
final class SqliteConnectionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $driver = new SqliteDriver(':memory:');
        $this->connection = new Connection($driver);
        $this->connection->executeStatement('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, score REAL)');
    }

    #[Test]
    public function insertAndFetchWithPreparedStatements(): void
    {
        $this->connection->executeStatement('INSERT INTO test (name, score) VALUES (?, ?)', ['Alice', 95.5]);
        $this->connection->executeStatement('INSERT INTO test (name, score) VALUES (?, ?)', ['Bob', 88.0]);

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM test ORDER BY id');

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    #[Test]
    public function fetchAssociativeWithParams(): void
    {
        $this->connection->executeStatement('INSERT INTO test (name) VALUES (?)', ['Charlie']);

        $row = $this->connection->fetchAssociative('SELECT * FROM test WHERE name = ?', ['Charlie']);

        self::assertNotNull($row);
        self::assertSame('Charlie', $row['name']);
    }

    #[Test]
    public function fetchOneWithParams(): void
    {
        $this->connection->executeStatement('INSERT INTO test (name) VALUES (?)', ['Dave']);
        $this->connection->executeStatement('INSERT INTO test (name) VALUES (?)', ['Eve']);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test WHERE name LIKE ?', ['D%']);

        self::assertSame(1, (int) $count);
    }

    #[Test]
    public function fetchFirstColumnWithParams(): void
    {
        $this->connection->executeStatement('INSERT INTO test (name, score) VALUES (?, ?)', ['Alice', 95.5]);
        $this->connection->executeStatement('INSERT INTO test (name, score) VALUES (?, ?)', ['Bob', 88.0]);
        $this->connection->executeStatement('INSERT INTO test (name, score) VALUES (?, ?)', ['Charlie', 72.0]);

        $names = $this->connection->fetchFirstColumn('SELECT name FROM test WHERE score > ? ORDER BY name', [80.0]);

        self::assertSame(['Alice', 'Bob'], $names);
    }

    #[Test]
    public function preparedStatementReuse(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO test (name, score) VALUES (?, ?)');
        $stmt->executeStatement(['Alice', 95.5]);
        $stmt->executeStatement(['Bob', 88.0]);
        $stmt->executeStatement(['Charlie', 72.0]);
        $stmt->close();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test');

        self::assertSame(3, (int) $count);
    }

    #[Test]
    public function preparedStatementWithBindValue(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO test (name) VALUES (?)');
        $stmt->bindValue(1, 'Frank');
        $stmt->executeStatement();
        $stmt->close();

        self::assertSame('Frank', $this->connection->fetchOne('SELECT name FROM test'));
    }

    #[Test]
    public function transactionalCommitsOnSuccess(): void
    {
        $result = $this->connection->transactional(function (Connection $conn) {
            $conn->executeStatement('INSERT INTO test (name) VALUES (?)', ['Grace']);

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM test'));
    }

    #[Test]
    public function transactionalRollsBackOnException(): void
    {
        try {
            $this->connection->transactional(function (Connection $conn) {
                $conn->executeStatement('INSERT INTO test (name) VALUES (?)', ['Heidi']);

                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM test'));
    }

    #[Test]
    public function lastInsertId(): void
    {
        $this->connection->executeStatement('INSERT INTO test (name) VALUES (?)', ['Ivan']);

        self::assertSame(1, $this->connection->getDriver()->lastInsertId());
    }

    #[Test]
    public function quoteIdentifierUsesSqlitePlatform(): void
    {
        // SQLite uses double-quotes for identifiers
        self::assertSame('"test"', $this->connection->quoteIdentifier('test'));
    }

    #[Test]
    public function queryTranslatorIsAvailable(): void
    {
        $translator = $this->connection->getDriver()->getQueryTranslator();

        self::assertInstanceOf(
            \WpPack\Component\Database\Bridge\Sqlite\Translator\SqliteQueryTranslator::class,
            $translator,
        );
    }
}
