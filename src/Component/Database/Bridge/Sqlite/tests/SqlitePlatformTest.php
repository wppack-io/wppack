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

namespace WPPack\Component\Database\Bridge\Sqlite\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\Sqlite\SqlitePlatform;

final class SqlitePlatformTest extends TestCase
{
    #[Test]
    public function quoteIdentifier(): void
    {
        $platform = new SqlitePlatform();

        self::assertSame('"posts"', $platform->quoteIdentifier('posts'));
        self::assertSame('"col""name"', $platform->quoteIdentifier('col"name'));
    }

    #[Test]
    public function engine(): void
    {
        self::assertSame('sqlite', (new SqlitePlatform())->getEngine());
    }

    #[Test]
    public function transaction(): void
    {
        self::assertSame('BEGIN', (new SqlitePlatform())->getBeginTransactionSql());
    }

    #[Test]
    public function autoIncrement(): void
    {
        self::assertSame('AUTOINCREMENT', (new SqlitePlatform())->getAutoIncrementKeyword());
    }

    #[Test]
    public function charsetCollateEmpty(): void
    {
        self::assertSame('', (new SqlitePlatform())->getCharsetCollateSql('utf8', 'utf8_general_ci'));
    }
}
