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

namespace WPPack\Component\Database\Bridge\PostgreSQL\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLPlatform;

final class PostgreSQLPlatformTest extends TestCase
{
    #[Test]
    public function quoteIdentifier(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame('"posts"', $platform->quoteIdentifier('posts'));
        self::assertSame('"col""name"', $platform->quoteIdentifier('col"name'));
    }

    #[Test]
    public function engine(): void
    {
        self::assertSame('pgsql', (new PostgreSQLPlatform())->getEngine());
    }

    #[Test]
    public function transaction(): void
    {
        self::assertSame('BEGIN', (new PostgreSQLPlatform())->getBeginTransactionSql());
    }

    #[Test]
    public function autoIncrement(): void
    {
        self::assertSame('SERIAL', (new PostgreSQLPlatform())->getAutoIncrementKeyword());
    }
}
