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

namespace WpPack\Component\Database\Bridge\AuroraDsql\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriver;
use WpPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriverFactory;
use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Dsn\Dsn;

final class AuroraDsqlDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsDsqlScheme(): void
    {
        $factory = new AuroraDsqlDriverFactory();

        if (!\function_exists('pg_connect')) {
            self::assertFalse($factory->supports(Dsn::fromString('dsql://admin:token@host/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('dsql://admin:token@host/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new AuroraDsqlDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('pgsql://host/db')));
    }

    #[Test]
    public function createsDriverWithTokenFromDsn(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDsqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('dsql://admin:mytoken@abc123.dsql.us-east-1.on.aws/mydb'));

        self::assertInstanceOf(AuroraDsqlDriver::class, $driver);
        self::assertSame('dsql', $driver->getName());
        self::assertSame(DatabaseEngine::PostgreSQL, $driver->getPlatform()->getEngine());
    }

    #[Test]
    public function extractsRegionFromEndpoint(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDsqlDriverFactory();

        // Region should be extracted from hostname, not from query parameter
        $driver = $factory->create(Dsn::fromString('dsql://admin:token@xyz.dsql.ap-northeast-1.on.aws/testdb'));

        self::assertInstanceOf(AuroraDsqlDriver::class, $driver);
    }

    #[Test]
    public function definitions(): void
    {
        $defs = AuroraDsqlDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('dsql', $defs[0]->scheme);
        self::assertSame('Aurora DSQL', $defs[0]->label);
    }

    // ── DSQL Translator ──

    #[Test]
    public function truncateConvertedToDeleteFrom(): void
    {
        $translator = new \WpPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator();

        $result = $translator->translate('TRUNCATE TABLE `wp_posts`');

        self::assertStringContainsString('DELETE FROM', $result[0]);
        self::assertStringContainsString('"wp_posts"', $result[0]);
        self::assertStringNotContainsString('TRUNCATE', $result[0]);
    }

    #[Test]
    public function truncateWithoutTableKeyword(): void
    {
        $translator = new \WpPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator();

        $result = $translator->translate('TRUNCATE `wp_options`');

        self::assertStringContainsString('DELETE FROM', $result[0]);
    }

    #[Test]
    public function nonTruncateQueriesDelegateToPostgresql(): void
    {
        $translator = new \WpPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator();

        // Regular queries go through PostgreSQL translator
        $result = $translator->translate('SELECT * FROM `wp_posts` WHERE id = 1');

        self::assertStringContainsString('"wp_posts"', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function dsqlDriverReturnsCorrectTranslator(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDsqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('dsql://admin:token@abc.dsql.us-east-1.on.aws/mydb'));

        self::assertInstanceOf(
            \WpPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator::class,
            $driver->getQueryTranslator(),
        );
    }
}
