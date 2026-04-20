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

namespace WPPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\Database\Connection;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Database\Platform\MySQLPlatform;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;

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
    public function executeQueryReturnsResultFromDriver(): void
    {
        $result = new Result([['id' => 1]]);
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn($result);

        $connection = new Connection($driver);

        self::assertSame($result, $connection->executeQuery('SELECT 1'));
    }

    #[Test]
    public function lastInsertIdDelegatesToDriver(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('lastInsertId')->willReturn(42);

        self::assertSame(42, (new Connection($driver))->lastInsertId());
    }

    #[Test]
    public function inTransactionDelegatesToDriver(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('inTransaction')->willReturn(true);

        self::assertTrue((new Connection($driver))->inTransaction());
    }

    #[Test]
    public function driverExceptionIsWrappedInQueryException(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willThrowException(
            new \WPPack\Component\Database\Exception\DriverException('native driver error'),
        );

        $connection = new Connection($driver);

        $this->expectException(\WPPack\Component\Database\Exception\QueryException::class);
        $this->expectExceptionMessage('native driver error');

        $connection->executeQuery('SELECT 1');
    }

    #[Test]
    public function translatorReturningEmptyStopsExecutionWithZeroRowsAffected(): void
    {
        $translator = new class implements \WPPack\Component\Database\Translator\QueryTranslatorInterface {
            public function translate(string $sql): array
            {
                return [];
            }
        };

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::never())->method('executeStatement');

        $connection = new Connection($driver, translator: $translator);

        self::assertSame(0, $connection->executeStatement('DROP TABLE throw_away'));
    }

    #[Test]
    public function getDriverReturnsInjectedDriver(): void
    {
        $driver = $this->createMock(DriverInterface::class);

        self::assertSame($driver, (new Connection($driver))->getDriver());
    }

    #[Test]
    public function prepareDelegatesToDriver(): void
    {
        $stmt = new Statement(
            static fn(array $p): Result => new Result([]),
            static fn(array $p): int => 0,
            static function (): void {},
        );

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('prepare')->with('SELECT 1')->willReturn($stmt);

        self::assertSame($stmt, (new Connection($driver))->prepare('SELECT 1'));
    }

    #[Test]
    public function translatorExpandingToMultipleStatementsRunsEachAndReturnsLastResult(): void
    {
        $translator = new class implements \WPPack\Component\Database\Translator\QueryTranslatorInterface {
            public function translate(string $sql): array
            {
                // Translator that splits one input into two native statements:
                // the first without placeholders (params stripped) and the
                // second with them retained.
                return [
                    'CREATE SEQUENCE seq_id',
                    'INSERT INTO t (x) VALUES (?)',
                ];
            }
        };

        $received = [];

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$received): int {
                $received[] = ['sql' => $sql, 'params' => $params];

                return \count($received);
            });

        $connection = new Connection($driver, translator: $translator);

        $result = $connection->executeStatement('CREATE TABLE t (x INT AUTO_INCREMENT PRIMARY KEY)', [1]);

        // Last statement's result is returned (2)
        self::assertSame(2, $result);
        self::assertCount(2, $received);
        // First statement has no placeholder → params trimmed to []
        self::assertSame([], $received[0]['params']);
        // Second retains the original params
        self::assertSame([1], $received[1]['params']);
    }

    #[Test]
    public function transactionalCommitsOnSuccess(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::once())->method('beginTransaction');
        $driver->expects(self::once())->method('commit');
        $driver->expects(self::never())->method('rollBack');

        $connection = new Connection($driver);

        $result = $connection->transactional(fn() => 'ok');

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
        $connection->transactional(fn() => throw new \RuntimeException('fail'));
    }

    #[Test]
    public function quoteIdentifierDelegatesToPlatform(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn(new MySQLPlatform());

        $connection = new Connection($driver);

        self::assertSame('`posts`', $connection->quoteIdentifier('posts'));
    }

    #[Test]
    public function getPlatform(): void
    {
        $platform = new MySQLPlatform();
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn($platform);

        $connection = new Connection($driver);

        self::assertSame($platform, $connection->getPlatform());
    }

    // ── Logger ──

    #[Test]
    public function loggerReceivesDebugOnQuery(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result([['id' => 1]]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with('Query executed', self::callback(function (array $context): bool {
                return $context['sql'] === 'SELECT 1'
                    && $context['params'] === []
                    && isset($context['time_ms']);
            }));

        $connection = new Connection($driver, $logger);
        $connection->fetchAllAssociative('SELECT 1');
    }

    #[Test]
    public function loggerReceivesDebugOnStatement(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeStatement')->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with('Query executed', self::callback(function (array $context): bool {
                return $context['sql'] === 'DELETE FROM t WHERE id = ?'
                    && $context['params'] === [1];
            }));

        $connection = new Connection($driver, $logger);
        $connection->executeStatement('DELETE FROM t WHERE id = ?', [1]);
    }

    #[Test]
    public function noLoggerDoesNotFail(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('executeQuery')->willReturn(new Result([]));

        // No logger — should not throw
        $connection = new Connection($driver);
        $connection->fetchAllAssociative('SELECT 1');

        self::assertTrue(true);
    }
}
