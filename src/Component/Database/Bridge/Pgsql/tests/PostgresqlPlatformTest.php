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
use WpPack\Component\Database\Bridge\Pgsql\PostgresqlPlatform;

final class PostgresqlPlatformTest extends TestCase
{
    #[Test]
    public function quoteIdentifier(): void
    {
        $platform = new PostgresqlPlatform();

        self::assertSame('"posts"', $platform->quoteIdentifier('posts'));
        self::assertSame('"col""name"', $platform->quoteIdentifier('col"name'));
    }

    #[Test]
    public function engine(): void
    {
        self::assertSame('pgsql', (new PostgresqlPlatform())->getEngine());
    }

    #[Test]
    public function transaction(): void
    {
        self::assertSame('BEGIN', (new PostgresqlPlatform())->getBeginTransactionSql());
    }

    #[Test]
    public function autoIncrement(): void
    {
        self::assertSame('SERIAL', (new PostgresqlPlatform())->getAutoIncrementKeyword());
    }
}
