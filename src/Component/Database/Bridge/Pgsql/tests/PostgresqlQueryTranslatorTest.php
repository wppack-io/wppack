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
    }

    #[Test]
    public function stripUnsigned(): void
    {
        $result = $this->translator->translate('CREATE TABLE `t` (`id` BIGINT UNSIGNED)');

        self::assertStringNotContainsString('UNSIGNED', $result[0]);
    }

    #[Test]
    public function convertDataTypes(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`d` DATETIME, `b` LONGBLOB, `j` JSON, `e` ENUM("a","b"))',
        );

        self::assertStringContainsString('TIMESTAMP', $result[0]);
        self::assertStringContainsString('BYTEA', $result[0]);
        self::assertStringContainsString('JSONB', $result[0]);
    }

    #[Test]
    public function truncateTable(): void
    {
        $result = $this->translator->translate('TRUNCATE TABLE `wp_posts`');

        self::assertStringContainsString('TRUNCATE TABLE', $result[0]);
        self::assertStringContainsString('"wp_posts"', $result[0]);
    }

    #[Test]
    public function ifnullToCoalesce(): void
    {
        $result = $this->translator->translate('SELECT IFNULL(name, "default") FROM t');

        self::assertStringContainsString('COALESCE', $result[0]);
    }

    #[Test]
    public function randToRandom(): void
    {
        $result = $this->translator->translate('SELECT RAND()');

        self::assertStringContainsString('random()', $result[0]);
    }

    #[Test]
    public function curdateToCurrentDate(): void
    {
        $result = $this->translator->translate('SELECT CURDATE()');

        self::assertStringContainsString('CURRENT_DATE', $result[0]);
    }

    #[Test]
    public function unixTimestamp(): void
    {
        $result = $this->translator->translate('SELECT UNIX_TIMESTAMP()');

        self::assertStringContainsString('EXTRACT(EPOCH FROM NOW())', $result[0]);
    }

    #[Test]
    public function fromUnixtime(): void
    {
        $result = $this->translator->translate('SELECT FROM_UNIXTIME(1234567890)');

        self::assertStringContainsString('TO_TIMESTAMP(1234567890)', $result[0]);
    }

    #[Test]
    public function lastInsertId(): void
    {
        $result = $this->translator->translate('SELECT LAST_INSERT_ID()');

        self::assertStringContainsString('lastval()', $result[0]);
    }

    #[Test]
    public function databaseFunction(): void
    {
        $result = $this->translator->translate('SELECT DATABASE()');

        self::assertStringContainsString('CURRENT_DATABASE()', $result[0]);
    }

    #[Test]
    public function dateAdd(): void
    {
        $result = $this->translator->translate("SELECT DATE_ADD('2024-01-01', INTERVAL 1 DAY)");

        self::assertStringContainsString("+ INTERVAL '1 day'", $result[0]);
    }

    #[Test]
    public function dateSub(): void
    {
        $result = $this->translator->translate("SELECT DATE_SUB('2024-01-01', INTERVAL 30 MINUTE)");

        self::assertStringContainsString("- INTERVAL '30 minute'", $result[0]);
    }

    #[Test]
    public function dateFormat(): void
    {
        $result = $this->translator->translate("SELECT DATE_FORMAT(created_at, '%Y-%m-%d')");

        self::assertStringContainsString("TO_CHAR(created_at, 'YYYY-MM-DD')", $result[0]);
    }

    #[Test]
    public function leftFunction(): void
    {
        $result = $this->translator->translate('SELECT LEFT(name, 5) FROM t');

        self::assertStringContainsString('SUBSTRING(name FROM 1 FOR 5)', $result[0]);
    }

    #[Test]
    public function ifFunction(): void
    {
        $result = $this->translator->translate('SELECT IF(status = 1, "active", "inactive") FROM t');

        self::assertStringContainsString('CASE WHEN', $result[0]);
    }

    #[Test]
    public function castAsSigned(): void
    {
        $result = $this->translator->translate('SELECT CAST(val AS SIGNED) FROM t');

        self::assertStringContainsString('CAST(val AS INTEGER)', $result[0]);
    }

    #[Test]
    public function regexpToTildeOperator(): void
    {
        $result = $this->translator->translate("SELECT * FROM t WHERE name REGEXP '^test'");

        self::assertStringContainsString('~*', $result[0]);
        self::assertStringNotContainsString('REGEXP', $result[0]);
    }

    #[Test]
    public function insertIgnore(): void
    {
        $result = $this->translator->translate("INSERT IGNORE INTO `t` VALUES (1, 'a')");

        self::assertStringContainsString('ON CONFLICT DO NOTHING', $result[0]);
        self::assertStringNotContainsString('IGNORE', $result[0]);
    }

    #[Test]
    public function onDuplicateKeyUpdate(): void
    {
        $result = $this->translator->translate(
            "INSERT INTO `t` (id, name) VALUES (1, 'a') ON DUPLICATE KEY UPDATE name = VALUES(name)",
        );

        self::assertStringContainsString('ON CONFLICT DO UPDATE SET', $result[0]);
        self::assertStringContainsString('excluded.name', $result[0]);
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
        self::assertStringContainsString('information_schema.tables', $this->translator->translate('SHOW TABLES')[0]);
    }

    #[Test]
    public function showColumnsFrom(): void
    {
        self::assertStringContainsString('information_schema.columns', $this->translator->translate('SHOW COLUMNS FROM `wp_posts`')[0]);
    }

    #[Test]
    public function showDatabases(): void
    {
        self::assertStringContainsString('pg_database', $this->translator->translate('SHOW DATABASES')[0]);
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

    #[Test]
    public function optimizeTableIgnored(): void
    {
        self::assertSame([], $this->translator->translate('OPTIMIZE TABLE wp_posts'));
    }
}
