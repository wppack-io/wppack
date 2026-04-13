<?php

declare(strict_types=1);

namespace WpPack\Component\Database\Bridge\Sqlite\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Sqlite\SqlitePlatform;
use WpPack\Component\Database\DatabaseEngine;

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
        self::assertSame(DatabaseEngine::SQLite, (new SqlitePlatform())->getEngine());
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
