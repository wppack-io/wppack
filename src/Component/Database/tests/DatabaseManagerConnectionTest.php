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

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Connection;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Platform\MysqlPlatform;
use WpPack\Component\Database\Result;

/**
 * Tests DatabaseManager's Connection delegation and placeholder conversion.
 */
final class DatabaseManagerConnectionTest extends TestCase
{
    private DatabaseManager $db;
    private DriverInterface $mockDriver;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager();
        $this->mockDriver = $this->createMock(DriverInterface::class);
        $this->mockDriver->method('getPlatform')->willReturn(new MysqlPlatform());
    }

    // ── setConnection / getConnection ──

    #[Test]
    public function connectionIsNullByDefault(): void
    {
        self::assertNull($this->db->getConnection());
    }

    #[Test]
    public function setConnectionAndGetConnection(): void
    {
        $connection = new Connection($this->mockDriver);
        $this->db->setConnection($connection);

        self::assertSame($connection, $this->db->getConnection());
    }

    // ── fetchAllAssociative delegation ──

    #[Test]
    public function fetchAllAssociativeDelegatesToConnectionWithNativePlaceholders(): void
    {
        $expected = [['id' => 1, 'name' => 'Alice']];

        $this->mockDriver->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT * FROM posts WHERE id = ?', [1])
            ->willReturn(new Result($expected));

        $this->db->setConnection(new Connection($this->mockDriver));

        $result = $this->db->fetchAllAssociative('SELECT * FROM posts WHERE id = ?', [1]);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function fetchAllAssociativeDelegatesToConnectionWithWpPlaceholders(): void
    {
        $expected = [['id' => 1]];

        $this->mockDriver->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT * FROM posts WHERE status = ? AND id > ?', ['publish', 5])
            ->willReturn(new Result($expected));

        $this->db->setConnection(new Connection($this->mockDriver));

        // %s and %d are converted to ? before delegation
        $result = $this->db->fetchAllAssociative(
            'SELECT * FROM posts WHERE status = %s AND id > %d',
            ['publish', 5],
        );

        self::assertSame($expected, $result);
    }

    #[Test]
    public function fetchAllAssociativeDelegatesWithoutParams(): void
    {
        $expected = [['id' => 1]];

        $this->mockDriver->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT * FROM posts', [])
            ->willReturn(new Result($expected));

        $this->db->setConnection(new Connection($this->mockDriver));

        $result = $this->db->fetchAllAssociative('SELECT * FROM posts');

        self::assertSame($expected, $result);
    }

    // ── fetchAssociative delegation ──

    #[Test]
    public function fetchAssociativeDelegatesToConnection(): void
    {
        $expected = ['id' => 1, 'name' => 'Bob'];

        $this->mockDriver->method('executeQuery')
            ->willReturn(new Result([$expected]));

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertSame($expected, $this->db->fetchAssociative('SELECT * FROM t WHERE id = ?', [1]));
    }

    #[Test]
    public function fetchAssociativeReturnsNullForEmptyResult(): void
    {
        $this->mockDriver->method('executeQuery')
            ->willReturn(new Result([]));

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertNull($this->db->fetchAssociative('SELECT * FROM t WHERE id = ?', [999]));
    }

    // ── fetchOne delegation ──

    #[Test]
    public function fetchOneDelegatesToConnection(): void
    {
        $this->mockDriver->method('executeQuery')
            ->willReturn(new Result([['cnt' => 42]]));

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertSame(42, $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM t'));
    }

    // ── fetchFirstColumn delegation ──

    #[Test]
    public function fetchFirstColumnDelegatesToConnection(): void
    {
        $this->mockDriver->method('executeQuery')
            ->willReturn(new Result([['id' => 1], ['id' => 2], ['id' => 3]]));

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertSame([1, 2, 3], $this->db->fetchFirstColumn('SELECT id FROM t'));
    }

    // ── executeStatement delegation ──

    #[Test]
    public function executeStatementDelegatesToConnection(): void
    {
        $this->mockDriver->expects(self::once())
            ->method('executeStatement')
            ->with('DELETE FROM posts WHERE id = ?', [1])
            ->willReturn(1);

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertSame(1, $this->db->executeStatement('DELETE FROM posts WHERE id = ?', [1]));
    }

    #[Test]
    public function executeStatementConvertsWpPlaceholders(): void
    {
        $this->mockDriver->expects(self::once())
            ->method('executeStatement')
            ->with('UPDATE posts SET title = ? WHERE id = ?', ['Hello', 1])
            ->willReturn(1);

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertSame(1, $this->db->executeStatement(
            'UPDATE posts SET title = %s WHERE id = %d',
            ['Hello', 1],
        ));
    }

    // ── executeQuery delegation ──

    #[Test]
    public function executeQueryDelegatesToConnection(): void
    {
        $this->mockDriver->expects(self::once())
            ->method('executeQuery')
            ->willReturn(new Result([]));

        $this->db->setConnection(new Connection($this->mockDriver));

        self::assertTrue((bool) $this->db->executeQuery('SELECT 1'));
    }

    // ── Placeholder conversion edge cases ──

    #[Test]
    public function literalPercentPreserved(): void
    {
        $this->mockDriver->expects(self::once())
            ->method('executeQuery')
            ->with(self::callback(function (string $sql): bool {
                return str_contains($sql, '%%');
            }), ['test'])
            ->willReturn(new Result([]));

        $this->db->setConnection(new Connection($this->mockDriver));

        $this->db->fetchAllAssociative(
            "SELECT * FROM t WHERE name LIKE '%%' AND status = %s",
            ['test'],
        );
    }

    #[Test]
    public function mixedPlaceholdersNotConverted(): void
    {
        // If query already has ?, don't convert %s (should not happen in practice, but be safe)
        $this->mockDriver->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT * FROM t WHERE id = ? AND status = %s', ['publish'])
            ->willReturn(new Result([]));

        $this->db->setConnection(new Connection($this->mockDriver));

        $this->db->fetchAllAssociative('SELECT * FROM t WHERE id = ? AND status = %s', ['publish']);
    }
}
