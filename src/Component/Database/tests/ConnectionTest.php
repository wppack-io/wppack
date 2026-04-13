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
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Platform\MysqlPlatform;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

final class ConnectionTest extends TestCase
{
    #[Test]
    public function fetchAllAssociative(): void
    {
        $rows = [['id' => 1, 'name' => 'test']];
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result($rows));

        $connection = new Connection($driver);

        self::assertSame($rows, $connection->fetchAllAssociative('SELECT * FROM t'));
    }

    #[Test]
    public function fetchAssociative(): void
    {
        $row = ['id' => 1];
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result([$row]));

        $connection = new Connection($driver);

        self::assertSame($row, $connection->fetchAssociative('SELECT * FROM t LIMIT 1'));
    }

    #[Test]
    public function fetchAssociativeReturnsNullForEmptyResult(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result([]));

        $connection = new Connection($driver);

        self::assertNull($connection->fetchAssociative('SELECT * FROM t WHERE 1=0'));
    }

    #[Test]
    public function fetchOne(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result([['count' => 42]]));

        $connection = new Connection($driver);

        self::assertSame(42, $connection->fetchOne('SELECT COUNT(*) FROM t'));
    }

    #[Test]
    public function fetchFirstColumn(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result([['id' => 1], ['id' => 2], ['id' => 3]]));

        $connection = new Connection($driver);

        self::assertSame([1, 2, 3], $connection->fetchFirstColumn('SELECT id FROM t'));
    }

    #[Test]
    public function executeStatement(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeStatement')->willReturn(5);

        $connection = new Connection($driver);

        self::assertSame(5, $connection->executeStatement('DELETE FROM t'));
    }

    #[Test]
    public function transactionalCommitsOnSuccess(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::once())->method('beginTransaction');
        $driver->expects(self::once())->method('commit');
        $driver->expects(self::never())->method('rollBack');

        $connection = new Connection($driver);

        $result = $connection->transactional(fn () => 'ok');

        self::assertSame('ok', $result);
    }

    #[Test]
    public function transactionalRollsBackOnException(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::once())->method('beginTransaction');
        $driver->expects(self::never())->method('commit');
        $driver->expects(self::once())->method('rollBack');

        $connection = new Connection($driver);

        $this->expectException(\RuntimeException::class);
        $connection->transactional(fn () => throw new \RuntimeException('fail'));
    }

    #[Test]
    public function quoteIdentifierDelegatesToPlatform(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn(new MysqlPlatform());

        $connection = new Connection($driver);

        self::assertSame('`posts`', $connection->quoteIdentifier('posts'));
    }

    #[Test]
    public function getPlatform(): void
    {
        $platform = new MysqlPlatform();
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn($platform);

        $connection = new Connection($driver);

        self::assertSame($platform, $connection->getPlatform());
    }
}
