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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;

#[CoversClass(Statement::class)]
final class StatementTest extends TestCase
{
    #[Test]
    public function executeQueryForwardsResolvedParametersToDriverClosure(): void
    {
        $captured = [];
        $result = new Result([], 0);

        $stmt = new Statement(
            executeQueryFn: static function (array $params) use (&$captured, $result): Result {
                $captured = $params;

                return $result;
            },
            executeStatementFn: static fn(array $params): int => 0,
            closeFn: static function (): void {},
        );

        // Positional bound values (1-indexed) should be ksorted and flattened
        $stmt->bindValue(2, 'two');
        $stmt->bindValue(1, 'one');

        self::assertSame($result, $stmt->executeQuery());
        self::assertSame(['one', 'two'], $captured);
    }

    #[Test]
    public function executeQueryWithExplicitParamsOverridesBoundValues(): void
    {
        $captured = null;
        $result = new Result([], 0);

        $stmt = new Statement(
            executeQueryFn: static function (array $params) use (&$captured, $result): Result {
                $captured = $params;

                return $result;
            },
            executeStatementFn: static fn(array $params): int => 0,
            closeFn: static function (): void {},
        );

        $stmt->bindValue(1, 'bound');

        $stmt->executeQuery(['explicit']);

        self::assertSame(['explicit'], $captured);
    }

    #[Test]
    public function executeStatementForwardsResolvedParametersAndReturnsAffectedRows(): void
    {
        $captured = null;

        $stmt = new Statement(
            executeQueryFn: static fn(array $p) => throw new \LogicException('not expected'),
            executeStatementFn: static function (array $params) use (&$captured): int {
                $captured = $params;

                return 3;
            },
            closeFn: static function (): void {},
        );

        $stmt->bindValue(1, 'a');
        $stmt->bindValue(2, 'b');

        self::assertSame(3, $stmt->executeStatement());
        self::assertSame(['a', 'b'], $captured);
    }

    #[Test]
    public function executeStatementWithNoBoundValuesReturnsEmptyArray(): void
    {
        $captured = null;

        $stmt = new Statement(
            executeQueryFn: static fn(array $p) => throw new \LogicException('not expected'),
            executeStatementFn: static function (array $params) use (&$captured): int {
                $captured = $params;

                return 0;
            },
            closeFn: static function (): void {},
        );

        $stmt->executeStatement();

        self::assertSame([], $captured);
    }

    #[Test]
    public function closeInvokesCloseClosureAndClearsBoundValues(): void
    {
        $closed = false;

        $stmt = new Statement(
            executeQueryFn: static fn(array $p) => throw new \LogicException('not expected'),
            executeStatementFn: static fn(array $p) => 0,
            closeFn: static function () use (&$closed): void {
                $closed = true;
            },
        );

        $stmt->bindValue(1, 'value');
        $stmt->close();

        self::assertTrue($closed);

        $ref = new \ReflectionProperty($stmt, 'boundValues');
        self::assertSame([], $ref->getValue($stmt));
    }
}
