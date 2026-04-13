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
use WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator;

final class PostgresqlQueryTranslatorTest extends TestCase
{
    private PostgresqlQueryTranslator $translator;

    protected function setUp(): void
    {
        $this->translator = new PostgresqlQueryTranslator();
    }

    #[Test]
    public function backtickToDoubleQuote(): void
    {
        $result = $this->translator->translate('SELECT `id` FROM `users`');

        self::assertStringContainsString('"id"', $result[0]);
        self::assertStringContainsString('"users"', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function autoIncrementToSerial(): void
    {
        $result = $this->translator->translate('CREATE TABLE `t` (`id` INT NOT NULL AUTO_INCREMENT)');

        self::assertStringContainsString('SERIAL', $result[0]);
        self::assertStringNotContainsString('AUTO_INCREMENT', $result[0]);
    }

    #[Test]
    public function stripUnsigned(): void
    {
        $result = $this->translator->translate('CREATE TABLE `t` (`id` BIGINT UNSIGNED)');

        self::assertStringNotContainsString('UNSIGNED', $result[0]);
    }

    #[Test]
    public function ifnullToCoalesce(): void
    {
        $result = $this->translator->translate('SELECT IFNULL(name, "default") FROM t');

        self::assertStringContainsString('COALESCE', $result[0]);
        self::assertStringNotContainsString('IFNULL', $result[0]);
    }

    #[Test]
    public function randToRandom(): void
    {
        $result = $this->translator->translate('SELECT RAND()');

        self::assertStringContainsString('random()', $result[0]);
    }

    #[Test]
    public function insertIgnore(): void
    {
        $result = $this->translator->translate("INSERT IGNORE INTO `t` VALUES (1, 'a')");

        self::assertStringContainsString('ON CONFLICT DO NOTHING', $result[0]);
        self::assertStringNotContainsString('IGNORE', $result[0]);
    }

    #[Test]
    public function limitOffsetCount(): void
    {
        $result = $this->translator->translate('SELECT * FROM t LIMIT 10, 20');

        self::assertStringContainsString('LIMIT 20 OFFSET 10', $result[0]);
    }

    #[Test]
    public function showTables(): void
    {
        $result = $this->translator->translate('SHOW TABLES');

        self::assertStringContainsString('information_schema.tables', $result[0]);
    }

    #[Test]
    public function showColumnsFrom(): void
    {
        $result = $this->translator->translate('SHOW COLUMNS FROM `wp_posts`');

        self::assertStringContainsString('information_schema.columns', $result[0]);
        self::assertStringContainsString('wp_posts', $result[0]);
    }

    #[Test]
    public function setNamesIgnored(): void
    {
        self::assertSame([], $this->translator->translate('SET NAMES utf8mb4'));
    }

    #[Test]
    public function lockTablesIgnored(): void
    {
        self::assertSame([], $this->translator->translate('LOCK TABLES wp_posts WRITE'));
    }

    #[Test]
    public function unlockTablesIgnored(): void
    {
        self::assertSame([], $this->translator->translate('UNLOCK TABLES'));
    }
}
