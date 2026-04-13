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

namespace WpPack\Component\Database\Bridge\Sqlite\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Sqlite\Translator\SqliteQueryTranslator;

final class SqliteQueryTranslatorTest extends TestCase
{
    private SqliteQueryTranslator $translator;

    protected function setUp(): void
    {
        $this->translator = new SqliteQueryTranslator();
    }

    // ── DML ──

    #[Test]
    public function selectPassesThrough(): void
    {
        $result = $this->translator->translate('SELECT * FROM `posts` WHERE id = 1');

        self::assertCount(1, $result);
        self::assertStringContainsString('"posts"', $result[0]);
        self::assertStringContainsString('WHERE id = 1', $result[0]);
    }

    #[Test]
    public function insertIgnore(): void
    {
        $result = $this->translator->translate("INSERT IGNORE INTO `t` VALUES (1, 'a')");

        self::assertCount(1, $result);
        self::assertStringContainsString('INSERT OR IGNORE', $result[0]);
    }

    #[Test]
    public function replaceInto(): void
    {
        $result = $this->translator->translate("REPLACE INTO `t` VALUES (1, 'a')");

        self::assertCount(1, $result);
        self::assertStringContainsString('INSERT OR REPLACE', $result[0]);
    }

    #[Test]
    public function limitOffsetCount(): void
    {
        $result = $this->translator->translate('SELECT * FROM t LIMIT 10, 20');

        self::assertCount(1, $result);
        self::assertStringContainsString('LIMIT 20 OFFSET 10', $result[0]);
    }

    #[Test]
    public function limitCountOnly(): void
    {
        $result = $this->translator->translate('SELECT * FROM t LIMIT 10');

        self::assertCount(1, $result);
        self::assertStringContainsString('LIMIT 10', $result[0]);
        self::assertStringNotContainsString('OFFSET', $result[0]);
    }

    // ── DDL ──

    #[Test]
    public function createTableStripsEngine(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`id` INT NOT NULL AUTO_INCREMENT) ENGINE=InnoDB',
        );

        self::assertCount(1, $result);
        self::assertStringNotContainsString('ENGINE', $result[0]);
        self::assertStringContainsString('AUTOINCREMENT', $result[0]);
    }

    #[Test]
    public function createTableConvertsTypes(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`name` VARCHAR(255), `count` BIGINT UNSIGNED, `created` DATETIME)',
        );

        self::assertCount(1, $result);
        self::assertStringContainsString('TEXT', $result[0]);
        self::assertStringContainsString('INTEGER', $result[0]);
        self::assertStringNotContainsString('VARCHAR', $result[0]);
        self::assertStringNotContainsString('BIGINT', $result[0]);
        self::assertStringNotContainsString('UNSIGNED', $result[0]);
        self::assertStringNotContainsString('DATETIME', $result[0]);
    }

    #[Test]
    public function backtickToDoubleQuote(): void
    {
        $result = $this->translator->translate('SELECT `id`, `name` FROM `users`');

        self::assertCount(1, $result);
        self::assertStringContainsString('"id"', $result[0]);
        self::assertStringContainsString('"name"', $result[0]);
        self::assertStringContainsString('"users"', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    // ── Functions ──

    #[Test]
    public function nowFunction(): void
    {
        $result = $this->translator->translate('SELECT NOW()');

        self::assertStringContainsString("datetime('now')", $result[0]);
    }

    #[Test]
    public function curdateFunction(): void
    {
        $result = $this->translator->translate('SELECT CURDATE()');

        self::assertStringContainsString("date('now')", $result[0]);
    }

    #[Test]
    public function randFunction(): void
    {
        $result = $this->translator->translate('SELECT RAND()');

        self::assertStringContainsString('random()', $result[0]);
    }

    #[Test]
    public function lastInsertIdFunction(): void
    {
        $result = $this->translator->translate('SELECT LAST_INSERT_ID()');

        self::assertStringContainsString('last_insert_rowid()', $result[0]);
    }

    #[Test]
    public function unixTimestampFunction(): void
    {
        $result = $this->translator->translate('SELECT UNIX_TIMESTAMP()');

        self::assertStringContainsString("strftime('%s','now')", $result[0]);
    }

    // ── Transaction ──

    #[Test]
    public function startTransaction(): void
    {
        $result = $this->translator->translate('START TRANSACTION');

        self::assertSame(['BEGIN'], $result);
    }

    // ── SHOW statements ──

    #[Test]
    public function showTables(): void
    {
        $result = $this->translator->translate('SHOW TABLES');

        self::assertCount(1, $result);
        self::assertStringContainsString('sqlite_master', $result[0]);
    }

    #[Test]
    public function showFullTables(): void
    {
        $result = $this->translator->translate('SHOW FULL TABLES');

        self::assertCount(1, $result);
        self::assertStringContainsString('sqlite_master', $result[0]);
        self::assertStringContainsString('Table_type', $result[0]);
    }

    #[Test]
    public function showColumnsFrom(): void
    {
        $result = $this->translator->translate('SHOW COLUMNS FROM `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('PRAGMA table_info', $result[0]);
        self::assertStringContainsString('wp_posts', $result[0]);
    }

    #[Test]
    public function showCreateTable(): void
    {
        $result = $this->translator->translate('SHOW CREATE TABLE `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('sqlite_master', $result[0]);
        self::assertStringContainsString('wp_posts', $result[0]);
    }

    #[Test]
    public function showIndexFrom(): void
    {
        $result = $this->translator->translate('SHOW INDEX FROM `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('PRAGMA index_list', $result[0]);
    }

    #[Test]
    public function showVariables(): void
    {
        $result = $this->translator->translate('SHOW VARIABLES');

        self::assertCount(1, $result);
        self::assertStringContainsString('WHERE 0', $result[0]);
    }

    // ── Ignored statements ──

    #[Test]
    public function setSessionIgnored(): void
    {
        self::assertSame([], $this->translator->translate("SET SESSION sql_mode = ''"));
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

    // ── FOR UPDATE ──

    #[Test]
    public function selectForUpdate(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE id = 1 FOR UPDATE');

        self::assertCount(1, $result);
        self::assertStringNotContainsString('FOR UPDATE', $result[0]);
    }
}
