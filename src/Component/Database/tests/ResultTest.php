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
use WpPack\Component\Database\Result;

final class ResultTest extends TestCase
{
    #[Test]
    public function fetchAllAssociative(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $result = new Result($rows);

        self::assertSame($rows, $result->fetchAllAssociative());
    }

    #[Test]
    public function fetchAssociative(): void
    {
        $result = new Result([['id' => 1, 'name' => 'test']]);

        self::assertSame(['id' => 1, 'name' => 'test'], $result->fetchAssociative());
    }

    #[Test]
    public function fetchAssociativeReturnsNullWhenEmpty(): void
    {
        $result = new Result([]);

        self::assertNull($result->fetchAssociative());
    }

    #[Test]
    public function fetchOne(): void
    {
        $result = new Result([['count' => 42, 'other' => 'x']]);

        self::assertSame(42, $result->fetchOne());
    }

    #[Test]
    public function fetchOneReturnsNullWhenEmpty(): void
    {
        $result = new Result([]);

        self::assertNull($result->fetchOne());
    }

    #[Test]
    public function fetchFirstColumn(): void
    {
        $result = new Result([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']]);

        self::assertSame([1, 2], $result->fetchFirstColumn());
    }

    #[Test]
    public function rowCount(): void
    {
        $result = new Result([], 5);

        self::assertSame(5, $result->rowCount());
    }

    #[Test]
    public function free(): void
    {
        $result = new Result([['id' => 1]]);
        $result->free();

        self::assertSame([], $result->fetchAllAssociative());
    }
}
