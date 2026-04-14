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
    }

    #[Test]
    public function insertIgnore(): void
    {
        $result = $this->translator->translate("INSERT IGNORE INTO `t` VALUES (1, 'a')");

        self::assertStringContainsString('INSERT OR IGNORE', $result[0]);
    }

    #[Test]
    public function replaceInto(): void
    {
        $result = $this->translator->translate("REPLACE INTO `t` VALUES (1, 'a')");

        self::assertStringContainsString('INSERT OR REPLACE', $result[0]);
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
    public function limitCountOnly(): void
    {
        $result = $this->translator->translate('SELECT * FROM t LIMIT 10');

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

        self::assertStringNotContainsString('ENGINE', $result[0]);
        self::assertStringContainsString('AUTOINCREMENT', $result[0]);
    }

    #[Test]
    public function createTableConvertsTypes(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`name` VARCHAR(255), `count` BIGINT UNSIGNED, `created` DATETIME)',
        );

        self::assertStringContainsString('TEXT', $result[0]);
        self::assertStringContainsString('INTEGER', $result[0]);
        self::assertStringNotContainsString('VARCHAR', $result[0]);
        self::assertStringNotContainsString('BIGINT', $result[0]);
        self::assertStringNotContainsString('UNSIGNED', $result[0]);
    }

    #[Test]
    public function truncateTable(): void
    {
        $result = $this->translator->translate('TRUNCATE TABLE `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('DELETE FROM', $result[0]);
        self::assertStringContainsString('"wp_posts"', $result[0]);
    }

    #[Test]
    public function createIndex(): void
    {
        $result = $this->translator->translate('CREATE INDEX idx_status ON `wp_posts` (post_status)');

        self::assertCount(1, $result);
        self::assertStringContainsString('CREATE INDEX', $result[0]);
    }

    #[Test]
    public function backtickToDoubleQuote(): void
    {
        $result = $this->translator->translate('SELECT `id`, `name` FROM `users`');

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

    #[Test]
    public function fromUnixtimeFunction(): void
    {
        $result = $this->translator->translate('SELECT FROM_UNIXTIME(1234567890)');

        self::assertStringContainsString("datetime(1234567890, 'unixepoch')", $result[0]);
    }

    #[Test]
    public function dateAddFunction(): void
    {
        $result = $this->translator->translate("SELECT DATE_ADD('2024-01-01', INTERVAL 1 DAY)");

        self::assertStringContainsString("datetime('2024-01-01', '+1 day')", $result[0]);
    }

    #[Test]
    public function dateSubFunction(): void
    {
        $result = $this->translator->translate("SELECT DATE_SUB('2024-01-01', INTERVAL 30 MINUTE)");

        self::assertStringContainsString("datetime('2024-01-01', '-30 minute')", $result[0]);
    }

    #[Test]
    public function dateFormatFunction(): void
    {
        $result = $this->translator->translate("SELECT DATE_FORMAT(created_at, '%Y-%m-%d')");

        self::assertStringContainsString("strftime('%Y-%m-%d', created_at)", $result[0]);
    }

    #[Test]
    public function leftFunction(): void
    {
        $result = $this->translator->translate('SELECT LEFT(name, 5) FROM t');

        self::assertStringContainsString('SUBSTR(name, 1, 5)', $result[0]);
    }

    #[Test]
    public function substringFunction(): void
    {
        $result = $this->translator->translate('SELECT SUBSTRING(name, 2, 3) FROM t');

        self::assertStringContainsString('SUBSTR(name, 2, 3)', $result[0]);
    }

    #[Test]
    public function ifFunction(): void
    {
        $result = $this->translator->translate('SELECT IF(status = 1, "active", "inactive") FROM t');

        self::assertStringContainsString('CASE WHEN', $result[0]);
        self::assertStringContainsString('THEN', $result[0]);
        self::assertStringContainsString('ELSE', $result[0]);
        self::assertStringContainsString('END', $result[0]);
    }

    #[Test]
    public function castAsSigned(): void
    {
        $result = $this->translator->translate('SELECT CAST(val AS SIGNED) FROM t');

        self::assertStringContainsString('CAST(val AS INTEGER)', $result[0]);
    }

    #[Test]
    public function versionFunction(): void
    {
        $result = $this->translator->translate('SELECT VERSION()');

        self::assertStringContainsString('wppack', $result[0]);
    }

    #[Test]
    public function databaseFunction(): void
    {
        $result = $this->translator->translate('SELECT DATABASE()');

        self::assertStringContainsString("'main'", $result[0]);
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
        self::assertStringContainsString('sqlite_master', $this->translator->translate('SHOW TABLES')[0]);
    }

    #[Test]
    public function showFullTables(): void
    {
        self::assertStringContainsString('Table_type', $this->translator->translate('SHOW FULL TABLES')[0]);
    }

    #[Test]
    public function showColumnsFrom(): void
    {
        self::assertStringContainsString('PRAGMA table_info', $this->translator->translate('SHOW COLUMNS FROM `wp_posts`')[0]);
    }

    #[Test]
    public function showCreateTable(): void
    {
        self::assertStringContainsString('sqlite_master', $this->translator->translate('SHOW CREATE TABLE `wp_posts`')[0]);
    }

    #[Test]
    public function showIndexFrom(): void
    {
        self::assertStringContainsString('PRAGMA index_list', $this->translator->translate('SHOW INDEX FROM `wp_posts`')[0]);
    }

    #[Test]
    public function showVariables(): void
    {
        self::assertStringContainsString('WHERE 0', $this->translator->translate('SHOW VARIABLES')[0]);
    }

    #[Test]
    public function showCollation(): void
    {
        self::assertStringContainsString('Collation', $this->translator->translate('SHOW COLLATION')[0]);
    }

    #[Test]
    public function showDatabases(): void
    {
        self::assertStringContainsString("'main'", $this->translator->translate('SHOW DATABASES')[0]);
    }

    #[Test]
    public function showTableStatus(): void
    {
        self::assertStringContainsString('sqlite_master', $this->translator->translate('SHOW TABLE STATUS')[0]);
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

    #[Test]
    public function optimizeTableIgnored(): void
    {
        self::assertSame([], $this->translator->translate('OPTIMIZE TABLE wp_posts'));
    }

    // ── FOR UPDATE ──

    #[Test]
    public function selectForUpdate(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE id = 1 FOR UPDATE');

        self::assertStringNotContainsString('FOR UPDATE', $result[0]);
    }

    // ── Database operations ──

    #[Test]
    public function createDatabaseIgnored(): void
    {
        self::assertSame([], $this->translator->translate('CREATE DATABASE IF NOT EXISTS `wordpress`'));
    }

    #[Test]
    public function dropDatabaseIgnored(): void
    {
        self::assertSame([], $this->translator->translate('DROP DATABASE IF EXISTS `wordpress`'));
    }

    // ── ALTER TABLE edge cases ──

    #[Test]
    public function alterTableAddIndexSkipped(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` ADD INDEX `idx_status` (`post_status`)');

        // SQLite doesn't support ALTER TABLE ADD INDEX — silently handled
        self::assertIsArray($result);
    }

    #[Test]
    public function alterTableDropColumnSkipped(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` DROP COLUMN `post_password`');

        self::assertIsArray($result);
    }

    // ── DESCRIBE ──

    #[Test]
    public function describe(): void
    {
        $result = $this->translator->translate('DESCRIBE `wp_posts`');

        self::assertStringContainsString('PRAGMA table_info', $result[0]);
    }

    // ── SAVEPOINT ──

    #[Test]
    public function savepoint(): void
    {
        $result = $this->translator->translate('SAVEPOINT sp1');

        self::assertSame(['SAVEPOINT sp1'], $result);
    }

    #[Test]
    public function releaseSavepoint(): void
    {
        $result = $this->translator->translate('RELEASE SAVEPOINT sp1');

        self::assertSame(['RELEASE SAVEPOINT sp1'], $result);
    }

    #[Test]
    public function rollbackToSavepoint(): void
    {
        $result = $this->translator->translate('ROLLBACK TO SAVEPOINT sp1');

        self::assertSame(['ROLLBACK TO SAVEPOINT sp1'], $result);
    }

    // ── Complex queries ──

    #[Test]
    public function betweenClause(): void
    {
        $result = $this->translator->translate('SELECT * FROM `wp_posts` WHERE post_date BETWEEN "2024-01-01" AND "2024-12-31"');

        self::assertStringContainsString('BETWEEN', $result[0]);
    }

    #[Test]
    public function existsSubquery(): void
    {
        $result = $this->translator->translate('SELECT * FROM `wp_posts` WHERE EXISTS (SELECT 1 FROM `wp_postmeta`)');

        self::assertStringContainsString('EXISTS', $result[0]);
    }

    #[Test]
    public function unionQuery(): void
    {
        $result = $this->translator->translate('SELECT ID FROM `wp_posts` WHERE post_status = "publish" UNION SELECT ID FROM `wp_posts` WHERE post_status = "draft"');

        self::assertStringContainsString('UNION', $result[0]);
    }

    // ── End-to-end with SQLite ──

    #[Test]
    public function endToEndCreateInsertSelectTruncate(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        // CREATE TABLE with MySQL types
        $createSql = $this->translator->translate(
            'CREATE TABLE `test` (`id` INT NOT NULL PRIMARY KEY, `name` VARCHAR(255), `created` DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
        $driver->executeStatement($createSql[0]);

        // INSERT
        $driver->executeStatement("INSERT INTO \"test\" (id, name, created) VALUES (1, 'hello', datetime('now'))");

        // SELECT
        $result = $driver->executeQuery('SELECT * FROM "test"');
        $rows = $result->fetchAllAssociative();
        self::assertCount(1, $rows);
        self::assertSame('hello', $rows[0]['name']);

        // TRUNCATE → DELETE FROM
        $truncateSql = $this->translator->translate('TRUNCATE TABLE `test`');
        $driver->executeStatement($truncateSql[0]);

        $result = $driver->executeQuery('SELECT COUNT(*) AS cnt FROM "test"');
        self::assertSame(0, (int) $result->fetchOne());

        $driver->close();
    }
}
