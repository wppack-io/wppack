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

namespace WpPack\Component\Database\Tests\Platform;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Database\Platform\MariadbPlatform;
use WpPack\Component\Database\Platform\MysqlPlatform;

final class PlatformTest extends TestCase
{
    // ── MySQL ──

    #[Test]
    public function mysqlQuoteIdentifier(): void
    {
        $platform = new MysqlPlatform();

        self::assertSame('`posts`', $platform->quoteIdentifier('posts'));
        self::assertSame('`col``name`', $platform->quoteIdentifier('col`name'));
    }

    #[Test]
    public function mysqlEngine(): void
    {
        self::assertSame(DatabaseEngine::MySQL, (new MysqlPlatform())->getEngine());
    }

    #[Test]
    public function mysqlTransaction(): void
    {
        self::assertSame('START TRANSACTION', (new MysqlPlatform())->getBeginTransactionSql());
    }

    #[Test]
    public function mysqlAutoIncrement(): void
    {
        self::assertSame('AUTO_INCREMENT', (new MysqlPlatform())->getAutoIncrementKeyword());
    }

    #[Test]
    public function mysqlCharsetCollate(): void
    {
        $platform = new MysqlPlatform();

        self::assertSame(
            'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $platform->getCharsetCollateSql('utf8mb4', 'utf8mb4_unicode_ci'),
        );
    }

    #[Test]
    public function mysqlCharsetWithoutCollate(): void
    {
        self::assertSame(
            'DEFAULT CHARSET=utf8mb4',
            (new MysqlPlatform())->getCharsetCollateSql('utf8mb4', ''),
        );
    }

    #[Test]
    public function mysqlSupportsNativePreparedStatements(): void
    {
        self::assertTrue((new MysqlPlatform())->supportsNativePreparedStatements());
    }

    // ── MariaDB ──

    #[Test]
    public function mariadbEngine(): void
    {
        self::assertSame(DatabaseEngine::MariaDB, (new MariadbPlatform())->getEngine());
    }

    #[Test]
    public function mariadbInheritsMysqlBehavior(): void
    {
        $platform = new MariadbPlatform();

        self::assertSame('`test`', $platform->quoteIdentifier('test'));
        self::assertSame('START TRANSACTION', $platform->getBeginTransactionSql());
        self::assertSame('AUTO_INCREMENT', $platform->getAutoIncrementKeyword());
    }

}
