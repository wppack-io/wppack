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

        self::assertCount(2, $result);
        self::assertStringContainsString('DELETE FROM', $result[0]);
        self::assertStringContainsString('"wp_posts"', $result[0]);
        self::assertStringContainsString('sqlite_sequence', $result[1]);
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
        $result = $this->translator->translate('SHOW CREATE TABLE `wp_posts`');

        self::assertStringContainsString('pragma_table_info', $result[0]);
        self::assertStringContainsString('Create Table', $result[0]);
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
    public function alterTableAddIndex(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` ADD INDEX `idx_status` (`post_status`)');

        self::assertCount(1, $result);
        self::assertStringContainsString('CREATE INDEX', $result[0]);
        self::assertStringContainsString('"idx_status"', $result[0]);
        self::assertStringContainsString('ON', $result[0]);
    }

    #[Test]
    public function alterTableAddUniqueIndex(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` ADD UNIQUE INDEX `slug_idx` (`post_name`)');

        self::assertStringContainsString('CREATE UNIQUE INDEX', $result[0]);
    }

    #[Test]
    public function alterTableDropIndex(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` DROP INDEX `post_date_gmt`');

        self::assertStringContainsString('DROP INDEX IF EXISTS', $result[0]);
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

    // ── Complex queries and subqueries ──

    #[Test]
    public function subqueryInWhere(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` WHERE post_author IN (SELECT ID FROM `wp_users` WHERE user_login LIKE "%admin%")',
        );

        self::assertStringContainsString('IN (SELECT', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function subqueryInSelect(): void
    {
        $result = $this->translator->translate(
            'SELECT p.ID, (SELECT COUNT(*) FROM `wp_postmeta` m WHERE m.post_id = p.ID) AS meta_count FROM `wp_posts` p',
        );

        self::assertStringContainsString('(SELECT COUNT', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function nestedSubquery(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` WHERE ID IN (SELECT post_id FROM `wp_postmeta` WHERE meta_value IN (SELECT ID FROM `wp_posts` WHERE post_type = "attachment"))',
        );

        // Double-nested IN (SELECT ... IN (SELECT ...))
        self::assertCount(1, $result);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function derivedTable(): void
    {
        $result = $this->translator->translate(
            'SELECT sub.cnt FROM (SELECT post_status, COUNT(*) AS cnt FROM `wp_posts` GROUP BY post_status) AS sub WHERE sub.cnt > 10',
        );

        self::assertStringContainsString('(SELECT', $result[0]);
        self::assertStringContainsString('GROUP BY', $result[0]);
    }

    #[Test]
    public function correlatedSubqueryWithFunction(): void
    {
        $result = $this->translator->translate(
            'SELECT p.*, (SELECT MAX(comment_date) FROM `wp_comments` c WHERE c.comment_post_ID = p.ID) AS last_comment FROM `wp_posts` p WHERE p.post_status = "publish"',
        );

        self::assertStringContainsString('(SELECT MAX', $result[0]);
    }

    #[Test]
    public function complexJoinWithFunctions(): void
    {
        $result = $this->translator->translate(
            'SELECT p.ID, DATE_FORMAT(p.post_date, "%Y-%m-%d") AS d, IF(p.comment_count > 0, "yes", "no") AS c FROM `wp_posts` p INNER JOIN `wp_users` u ON p.post_author = u.ID WHERE p.post_date > DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 5, 10',
        );

        self::assertStringContainsString('strftime', $result[0]);
        self::assertStringContainsString('CASE WHEN', $result[0]);
        self::assertStringContainsString("datetime(", $result[0]);
        self::assertStringContainsString('LIMIT 10 OFFSET 5', $result[0]);
    }

    #[Test]
    public function multipleJoins(): void
    {
        $result = $this->translator->translate(
            'SELECT p.ID, t.name FROM `wp_posts` p JOIN `wp_term_relationships` tr ON p.ID = tr.object_id JOIN `wp_term_taxonomy` tt ON tr.term_taxonomy_id = tt.term_taxonomy_id JOIN `wp_terms` t ON tt.term_id = t.term_id WHERE tt.taxonomy = "post_tag"',
        );

        self::assertCount(1, $result);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function insertWithSubquery(): void
    {
        $result = $this->translator->translate(
            'INSERT INTO `wp_postmeta` (post_id, meta_key, meta_value) SELECT ID, "_migrated", "1" FROM `wp_posts` WHERE post_type = "post"',
        );

        self::assertStringContainsString('INSERT INTO', $result[0]);
        self::assertStringContainsString('SELECT', $result[0]);
    }

    #[Test]
    public function updateWithSubqueryInWhere(): void
    {
        $result = $this->translator->translate(
            'UPDATE `wp_posts` SET post_status = "trash" WHERE ID IN (SELECT post_id FROM `wp_postmeta` WHERE meta_key = "_expired")',
        );

        self::assertStringContainsString('UPDATE', $result[0]);
        self::assertStringContainsString('IN (SELECT', $result[0]);
    }

    #[Test]
    public function deleteWithSubquery(): void
    {
        $result = $this->translator->translate(
            'DELETE FROM `wp_postmeta` WHERE post_id NOT IN (SELECT ID FROM `wp_posts`)',
        );

        self::assertStringContainsString('NOT IN (SELECT', $result[0]);
    }

    #[Test]
    public function groupByHavingOrderLimit(): void
    {
        $result = $this->translator->translate(
            'SELECT post_author, COUNT(*) AS post_count FROM `wp_posts` WHERE post_status = "publish" GROUP BY post_author HAVING post_count > 5 ORDER BY post_count DESC LIMIT 20',
        );

        self::assertStringContainsString('GROUP BY', $result[0]);
        self::assertStringContainsString('HAVING', $result[0]);
        self::assertStringContainsString('ORDER BY', $result[0]);
        self::assertStringContainsString('LIMIT 20', $result[0]);
    }

    #[Test]
    public function unionAllWithOrder(): void
    {
        $result = $this->translator->translate(
            'SELECT "post" AS type, ID FROM `wp_posts` WHERE post_status = "publish" UNION ALL SELECT "page" AS type, ID FROM `wp_posts` WHERE post_type = "page" ORDER BY type LIMIT 50',
        );

        self::assertStringContainsString('UNION ALL', $result[0]);
    }

    #[Test]
    public function nestedIfnullAndLeft(): void
    {
        $result = $this->translator->translate(
            'SELECT ID, IFNULL(post_excerpt, LEFT(post_content, 100)) AS excerpt FROM `wp_posts`',
        );

        self::assertStringContainsString('IFNULL', $result[0]);
        self::assertStringContainsString('SUBSTR(', $result[0]);
    }

    #[Test]
    public function regexpInWhere(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_options` WHERE option_name REGEXP "^_transient_"',
        );

        // REGEXP stays as-is for SQLite UDF
        self::assertStringContainsString('REGEXP', $result[0]);
    }

    #[Test]
    public function existsWithCorrelatedSubquery(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` p WHERE EXISTS (SELECT 1 FROM `wp_postmeta` m WHERE m.post_id = p.ID AND m.meta_key = "_featured") AND p.post_status = "publish"',
        );

        self::assertStringContainsString('EXISTS (SELECT', $result[0]);
    }

    #[Test]
    public function unixTimestampInSubquery(): void
    {
        $result = $this->translator->translate(
            'UPDATE `wp_posts` SET post_status = "trash" WHERE ID IN (SELECT post_id FROM `wp_postmeta` WHERE meta_value < UNIX_TIMESTAMP())',
        );

        self::assertStringContainsString("strftime('%s','now')", $result[0]);
    }

    // ── Multi-line and schema queries ──

    #[Test]
    public function multiLineCreateTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE `wp_posts` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint(20) unsigned NOT NULL DEFAULT 0,
  `post_date` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
  `post_content` longtext NOT NULL,
  `post_title` text NOT NULL,
  `post_status` varchar(20) NOT NULL DEFAULT "publish",
  PRIMARY KEY (`ID`),
  KEY `post_author` (`post_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
SQL;

        $result = $this->translator->translate($sql);

        self::assertGreaterThanOrEqual(1, \count($result));
        self::assertStringContainsString('INTEGER', $result[0]);
        self::assertStringContainsString('TEXT', $result[0]);
        self::assertStringContainsString('AUTOINCREMENT', $result[0]);
        self::assertStringNotContainsString('ENGINE=', $result[0]);
        self::assertStringNotContainsString('CHARSET', $result[0]);
        self::assertStringNotContainsString('COLLATE=', $result[0]);
        self::assertStringNotContainsString('bigint(20)', $result[0]);
        self::assertStringNotContainsString('varchar(', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function createTableIfNotExists(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `wp_options` (
  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) NOT NULL DEFAULT "",
  `option_value` longtext NOT NULL,
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`)
) ENGINE=InnoDB
SQL;

        $result = $this->translator->translate($sql);

        self::assertGreaterThanOrEqual(1, \count($result));
        self::assertStringContainsString('IF NOT EXISTS', $result[0]);
        self::assertStringNotContainsString('ENGINE=', $result[0]);
    }

    #[Test]
    public function createAndDropIndex(): void
    {
        $create = $this->translator->translate('CREATE INDEX `idx_status` ON `wp_posts` (`post_status`)');
        $unique = $this->translator->translate('CREATE UNIQUE INDEX `idx_option` ON `wp_options` (`option_name`)');
        $drop = $this->translator->translate('DROP INDEX `idx_status` ON `wp_posts`');

        self::assertStringContainsString('CREATE INDEX', $create[0]);
        self::assertStringContainsString('CREATE UNIQUE INDEX', $unique[0]);
        self::assertStringContainsString('DROP INDEX', $drop[0]);
        self::assertStringNotContainsString('`', $create[0]);
    }

    #[Test]
    public function createTableWithSeparatePrimaryKey(): void
    {
        $sql = <<<'SQL'
CREATE TABLE `wp_posts` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_title` text NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

        $result = $this->translator->translate($sql);

        // AUTOINCREMENT must be on same line as INTEGER PRIMARY KEY
        self::assertStringContainsString('INTEGER', $result[0]);
        self::assertStringContainsString('PRIMARY KEY AUTOINCREMENT', $result[0]);
        // Separate PRIMARY KEY line must be removed
        self::assertSame(1, substr_count($result[0], 'PRIMARY KEY'));
    }

    #[Test]
    public function createTableWithSeparatePrimaryKeyExecutesOnSqlite(): void
    {
        $sql = <<<'SQL'
CREATE TABLE `test_pk` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $this->translator->translate($sql);
        $driver->executeStatement($result[0]);

        // Insert and verify auto-increment
        $driver->executeStatement('INSERT INTO "test_pk" ("name") VALUES (\'a\')');
        $driver->executeStatement('INSERT INTO "test_pk" ("name") VALUES (\'b\')');

        $rows = $driver->executeQuery('SELECT * FROM "test_pk" ORDER BY "ID"')->fetchAllAssociative();
        self::assertCount(2, $rows);
        self::assertSame(1, (int) $rows[0]['ID']);
        self::assertSame(2, (int) $rows[1]['ID']);

        $driver->close();
    }

    #[Test]
    public function dropTableIfExists(): void
    {
        $result = $this->translator->translate('DROP TABLE IF EXISTS `wp_posts`');

        self::assertStringContainsString('DROP TABLE IF EXISTS', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function multiLineInsertWithNewlines(): void
    {
        $sql = <<<'SQL'
INSERT INTO `wp_posts`
  (`post_author`, `post_date`, `post_content`, `post_title`, `post_status`)
VALUES
  (1, NOW(), "Hello
World", "Test", "publish")
SQL;

        $result = $this->translator->translate($sql);

        self::assertCount(1, $result);
        self::assertStringContainsString("datetime('now')", $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function multiLineUpdateWithFunctions(): void
    {
        $sql = <<<'SQL'
UPDATE `wp_posts`
SET
  `post_status` = "trash",
  `post_modified` = NOW()
WHERE
  `post_date` < DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND `post_status` = "draft"
SQL;

        $result = $this->translator->translate($sql);

        self::assertCount(1, $result);
        self::assertStringContainsString("datetime('now')", $result[0]);
        self::assertStringContainsString("-30 day", $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function multiLineSelectWithSubquery(): void
    {
        $sql = <<<'SQL'
SELECT
  p.ID,
  p.post_title,
  (SELECT COUNT(*)
   FROM `wp_comments` c
   WHERE c.comment_post_ID = p.ID
   AND c.comment_approved = "1") AS comment_count
FROM `wp_posts` p
WHERE p.post_status = "publish"
ORDER BY p.post_date DESC
LIMIT 10, 20
SQL;

        $result = $this->translator->translate($sql);

        self::assertCount(1, $result);
        self::assertStringContainsString('(SELECT COUNT', $result[0]);
        self::assertStringContainsString('LIMIT 20 OFFSET 10', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    // ── End-to-end with SQLite (full lifecycle) ──

    #[Test]
    public function endToEndFullLifecycle(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        // CREATE TABLE with MySQL types (multi-line)
        $createSql = $this->translator->translate(<<<'SQL'
CREATE TABLE `test_posts` (
  `ID` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext,
  `status` varchar(20) NOT NULL DEFAULT "draft",
  `created_at` datetime
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
        $driver->executeStatement($createSql[0]);

        // INSERT with NOW()
        $insertSql = $this->translator->translate(
            'INSERT INTO `test_posts` (`title`, `content`, `status`, `created_at`) VALUES ("Hello", "World", "publish", NOW())',
        );
        $driver->executeStatement($insertSql[0]);

        // SELECT with function
        $selectSql = $this->translator->translate(
            'SELECT `ID`, `title`, `status` FROM `test_posts` WHERE `status` = "publish"',
        );
        $result = $driver->executeQuery($selectSql[0]);
        $rows = $result->fetchAllAssociative();
        self::assertCount(1, $rows);
        self::assertSame('Hello', $rows[0]['title']);

        // UPDATE with function
        $updateSql = $this->translator->translate(
            'UPDATE `test_posts` SET `status` = "draft" WHERE `ID` = 1',
        );
        $driver->executeStatement($updateSql[0]);

        // Verify update
        $result = $driver->executeQuery('SELECT status FROM "test_posts" WHERE "ID" = 1');
        self::assertSame('draft', $result->fetchOne());

        // DELETE
        $deleteSql = $this->translator->translate(
            'DELETE FROM `test_posts` WHERE `status` = "draft"',
        );
        $driver->executeStatement($deleteSql[0]);

        // Verify empty
        $result = $driver->executeQuery('SELECT COUNT(*) AS cnt FROM "test_posts"');
        self::assertSame(0, (int) $result->fetchOne());

        // TRUNCATE
        $driver->executeStatement('INSERT INTO "test_posts" ("title", "status") VALUES (\'a\', \'pub\')');
        $truncateSql = $this->translator->translate('TRUNCATE TABLE `test_posts`');
        $driver->executeStatement($truncateSql[0]);

        $result = $driver->executeQuery('SELECT COUNT(*) AS cnt FROM "test_posts"');
        self::assertSame(0, (int) $result->fetchOne());

        $driver->close();
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

    // ── String literal protection ──

    #[Test]
    public function stringLiteralNotTransformed(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE name = 'NOW()' AND created = NOW()",
        );

        self::assertStringContainsString("'NOW()'", $result[0]);
        self::assertStringContainsString("datetime('now')", $result[0]);
    }

    #[Test]
    public function doubleQuotedStringLiteralNotTransformed(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM t WHERE val = "RAND()" AND r = RAND()',
        );

        self::assertStringContainsString('"RAND()"', $result[0]);
        self::assertStringContainsString('random()', $result[0]);
    }

    #[Test]
    public function stringLiteralWithEscapedQuote(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE name = 'it''s NOW()' AND created = NOW()",
        );

        self::assertStringContainsString("'it''s NOW()'", $result[0]);
        self::assertStringContainsString("datetime('now')", $result[0]);
    }

    #[Test]
    public function mixedFunctionsAndStringLiterals(): void
    {
        $result = $this->translator->translate(
            "SELECT DATE_FORMAT(created, '%Y-%m-%d'), 'DATE_FORMAT test' FROM t WHERE status = 'CURDATE()'",
        );

        self::assertStringContainsString('strftime', $result[0]);
        self::assertStringContainsString("'CURDATE()'", $result[0]);
        self::assertStringContainsString("'DATE_FORMAT test'", $result[0]);
    }

    #[Test]
    public function stringLiteralRegexpNotTransformed(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE name = 'REGEXP test' AND val REGEXP 'pattern'",
        );

        self::assertStringContainsString("'REGEXP test'", $result[0]);
        self::assertStringContainsString("REGEXP 'pattern'", $result[0]);
    }

    // ── Extended function translations ──

    #[Test]
    public function concatFunction(): void
    {
        $result = $this->translator->translate("SELECT CONCAT(first_name, ' ', last_name) FROM t");

        self::assertStringContainsString('||', $result[0]);
        self::assertStringNotContainsString('CONCAT', $result[0]);
    }

    #[Test]
    public function concatWsFunction(): void
    {
        $result = $this->translator->translate("SELECT CONCAT_WS('-', a, b, c) FROM t");

        self::assertStringContainsString('||', $result[0]);
    }

    #[Test]
    public function rightFunction(): void
    {
        $result = $this->translator->translate('SELECT RIGHT(name, 3) FROM t');

        self::assertStringContainsString('SUBSTR(name, -3)', $result[0]);
    }

    #[Test]
    public function datediffFunction(): void
    {
        $result = $this->translator->translate("SELECT DATEDIFF('2024-12-31', '2024-01-01')");

        self::assertStringContainsString('julianday', $result[0]);
        self::assertStringContainsString('CAST(', $result[0]);
    }

    #[Test]
    public function monthYearDayFunctions(): void
    {
        $result = $this->translator->translate('SELECT MONTH(d), YEAR(d), DAY(d) FROM t');

        self::assertStringContainsString("strftime('%m'", $result[0]);
        self::assertStringContainsString("strftime('%Y'", $result[0]);
        self::assertStringContainsString("strftime('%d'", $result[0]);
    }

    #[Test]
    public function hourMinuteSecondFunctions(): void
    {
        $result = $this->translator->translate('SELECT HOUR(d), MINUTE(d), SECOND(d) FROM t');

        self::assertStringContainsString("strftime('%H'", $result[0]);
        self::assertStringContainsString("strftime('%M'", $result[0]);
        self::assertStringContainsString("strftime('%S'", $result[0]);
    }

    #[Test]
    public function dayOfWeekFunction(): void
    {
        $result = $this->translator->translate('SELECT DAYOFWEEK(created) FROM t');

        self::assertStringContainsString("strftime('%w'", $result[0]);
        self::assertStringContainsString('+ 1)', $result[0]);
    }

    #[Test]
    public function greatestLeastFunctions(): void
    {
        $result = $this->translator->translate('SELECT GREATEST(a, b), LEAST(c, d) FROM t');

        self::assertStringContainsString('MAX(a, b)', $result[0]);
        self::assertStringContainsString('MIN(c, d)', $result[0]);
    }

    #[Test]
    public function lcaseUcaseFunctions(): void
    {
        $result = $this->translator->translate('SELECT LCASE(name), UCASE(title) FROM t');

        self::assertStringContainsString('lower(name)', $result[0]);
        self::assertStringContainsString('upper(title)', $result[0]);
    }

    #[Test]
    public function utcTimestampFunction(): void
    {
        $result = $this->translator->translate('SELECT UTC_TIMESTAMP()');

        self::assertStringContainsString("datetime('now')", $result[0]);
    }

    #[Test]
    public function nestedFunctionInDateAdd(): void
    {
        $result = $this->translator->translate("SELECT DATE_ADD(NOW(), INTERVAL 1 DAY)");

        self::assertStringContainsString("datetime(datetime('now')", $result[0]);
        self::assertStringContainsString("'+1 day'", $result[0]);
    }

    // ── End-to-end: extended functions on SQLite ──

    #[Test]
    public function endToEndConcatAndDateFunctions(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $driver->executeStatement('CREATE TABLE "t" ("id" INTEGER PRIMARY KEY, "first" TEXT, "last" TEXT, "created" TEXT)');
        $driver->executeStatement("INSERT INTO \"t\" VALUES (1, 'John', 'Doe', '2024-06-15 10:30:00')");

        // CONCAT
        $concatSql = $this->translator->translate("SELECT CONCAT(`first`, ' ', `last`) AS name FROM `t`");
        $result = $driver->executeQuery($concatSql[0]);
        self::assertSame('John Doe', $result->fetchOne());

        // MONTH/YEAR
        $monthSql = $this->translator->translate('SELECT MONTH(`created`) FROM `t`');
        $result = $driver->executeQuery($monthSql[0]);
        self::assertSame(6, (int) $result->fetchOne());

        $yearSql = $this->translator->translate('SELECT YEAR(`created`) FROM `t`');
        $result = $driver->executeQuery($yearSql[0]);
        self::assertSame(2024, (int) $result->fetchOne());

        // GREATEST/LEAST
        $greatestSql = $this->translator->translate('SELECT GREATEST(10, 20, 5)');
        $result = $driver->executeQuery($greatestSql[0]);
        self::assertSame(20, (int) $result->fetchOne());

        $driver->close();
    }

    // ── Phase 1-3 new feature tests ──

    #[Test]
    public function updateWithLimit(): void
    {
        $result = $this->translator->translate(
            'UPDATE `wp_posts` SET post_status = "trash" WHERE post_status = "draft" LIMIT 5',
        );

        self::assertStringContainsString('rowid IN (SELECT rowid FROM', $result[0]);
        self::assertStringContainsString('LIMIT 5', $result[0]);
    }

    #[Test]
    public function deleteWithLimit(): void
    {
        $result = $this->translator->translate(
            'DELETE FROM `wp_posts` WHERE post_status = "trash" LIMIT 10',
        );

        self::assertStringContainsString('rowid IN (SELECT rowid FROM', $result[0]);
        self::assertStringContainsString('LIMIT 10', $result[0]);
    }

    #[Test]
    public function deleteWithOrderByLimit(): void
    {
        $result = $this->translator->translate(
            'DELETE FROM `wp_posts` WHERE post_status = "trash" ORDER BY post_date ASC LIMIT 1',
        );

        self::assertStringContainsString('ORDER BY post_date ASC', $result[0]);
        self::assertStringContainsString('LIMIT 1', $result[0]);
    }

    #[Test]
    public function onUpdateCurrentTimestampTrigger(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`id` INT NOT NULL AUTO_INCREMENT, `modified` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`))',
        );

        self::assertGreaterThanOrEqual(2, \count($result));
        self::assertStringContainsString('CREATE TABLE', $result[0]);
        self::assertStringNotContainsString('ON UPDATE', $result[0]);
        // Find the trigger statement
        $triggerIdx = null;
        foreach ($result as $i => $sql) {
            if (str_contains($sql, 'CREATE TRIGGER')) {
                $triggerIdx = $i;
                break;
            }
        }
        self::assertNotNull($triggerIdx, 'Expected CREATE TRIGGER in result');
        self::assertStringContainsString('AFTER UPDATE', $result[$triggerIdx]);
        self::assertStringContainsString("datetime('now')", $result[$triggerIdx]);
    }

    #[Test]
    public function onUpdateCurrentTimestampEndToEnd(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $this->translator->translate(
            'CREATE TABLE `t` (`id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT, `name` VARCHAR(255), `modified` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)',
        );

        foreach ($result as $sql) {
            $driver->executeStatement($sql);
        }

        $driver->executeStatement("INSERT INTO \"t\" (\"name\") VALUES ('original')");

        // Get initial modified time
        $initial = $driver->executeQuery('SELECT "modified" FROM "t" WHERE "id" = 1')->fetchOne();
        self::assertNotNull($initial);

        // Update the row
        $driver->executeStatement("UPDATE \"t\" SET \"name\" = 'updated' WHERE \"id\" = 1");

        // modified should be updated by the trigger
        $after = $driver->executeQuery('SELECT "modified" FROM "t" WHERE "id" = 1')->fetchOne();
        self::assertNotNull($after);

        $driver->close();
    }

    #[Test]
    public function fromDualRemoval(): void
    {
        $result = $this->translator->translate('SELECT 1 FROM DUAL');

        self::assertStringNotContainsString('DUAL', $result[0]);
        self::assertStringContainsString('SELECT 1', $result[0]);
    }

    #[Test]
    public function fromDualInInsertSelect(): void
    {
        $result = $this->translator->translate(
            "INSERT INTO t (a, b) SELECT 'val', 1 FROM DUAL WHERE (SELECT NULL FROM DUAL) IS NULL",
        );

        self::assertStringNotContainsString('DUAL', $result[0]);
        self::assertStringContainsString('INSERT INTO', $result[0]);
    }

    #[Test]
    public function indexHintsRemoval(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` USE INDEX (`post_status`) WHERE post_status = "publish"',
        );

        self::assertStringNotContainsString('USE INDEX', $result[0]);
        self::assertStringNotContainsString('USE', $result[0]);
        self::assertStringContainsString('"wp_posts"', $result[0]);
    }

    #[Test]
    public function forceIndexHintsRemoval(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` FORCE INDEX (`primary`) WHERE id = 1',
        );

        self::assertStringNotContainsString('FORCE INDEX', $result[0]);
    }

    #[Test]
    public function castAsBinary(): void
    {
        $result = $this->translator->translate('SELECT CAST(val AS BINARY) FROM t');

        self::assertStringContainsString('CAST(val AS BLOB)', $result[0]);
    }

    #[Test]
    public function likeBinaryToGlob(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE name LIKE BINARY '%Test%'",
        );

        self::assertStringContainsString('GLOB', $result[0]);
        self::assertStringContainsString('*Test*', $result[0]);
        self::assertStringNotContainsString('LIKE', $result[0]);
    }

    #[Test]
    public function havingWithoutGroupBy(): void
    {
        $result = $this->translator->translate(
            'SELECT status, COUNT(*) as cnt FROM `wp_posts` HAVING cnt > 5',
        );

        self::assertStringContainsString('GROUP BY 1', $result[0]);
        self::assertStringContainsString('HAVING', $result[0]);
    }

    #[Test]
    public function dateFormatExtendedSpecifiers(): void
    {
        $result = $this->translator->translate("SELECT DATE_FORMAT(created, '%Y-%m-%d %H:%i:%s %p')");

        self::assertStringContainsString('strftime', $result[0]);
    }

    #[Test]
    public function md5UdfEndToEnd(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $driver->executeQuery("SELECT MD5('hello')");
        self::assertSame('5d41402abc4b2a76b9719d911017c592', $result->fetchOne());

        $driver->close();
    }

    #[Test]
    public function alterTableDropColumn(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` DROP COLUMN `post_password`');

        self::assertCount(1, $result);
        self::assertStringContainsString('DROP', $result[0]);
        self::assertStringContainsString('"post_password"', $result[0]);
    }

    #[Test]
    public function endToEndDeleteWithLimit(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $driver->executeStatement('CREATE TABLE "t" ("id" INTEGER PRIMARY KEY, "status" TEXT)');
        $driver->executeStatement("INSERT INTO \"t\" VALUES (1, 'trash')");
        $driver->executeStatement("INSERT INTO \"t\" VALUES (2, 'trash')");
        $driver->executeStatement("INSERT INTO \"t\" VALUES (3, 'publish')");

        $deleteSql = $this->translator->translate('DELETE FROM `t` WHERE status = "trash" LIMIT 1');
        $driver->executeStatement($deleteSql[0]);

        $result = $driver->executeQuery('SELECT COUNT(*) FROM "t" WHERE "status" = \'trash\'');
        self::assertSame(1, (int) $result->fetchOne());

        $driver->close();
    }

    #[Test]
    public function endToEndUpdateWithLimit(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $driver->executeStatement('CREATE TABLE "t" ("id" INTEGER PRIMARY KEY, "status" TEXT)');
        $driver->executeStatement("INSERT INTO \"t\" VALUES (1, 'draft')");
        $driver->executeStatement("INSERT INTO \"t\" VALUES (2, 'draft')");
        $driver->executeStatement("INSERT INTO \"t\" VALUES (3, 'publish')");

        $updateSql = $this->translator->translate('UPDATE `t` SET status = "trash" WHERE status = "draft" LIMIT 1');
        $driver->executeStatement($updateSql[0]);

        $result = $driver->executeQuery('SELECT COUNT(*) FROM "t" WHERE "status" = \'trash\'');
        self::assertSame(1, (int) $result->fetchOne());

        $driver->close();
    }

    // ── Additional gap closure tests ──

    #[Test]
    public function isnullFunction(): void
    {
        $result = $this->translator->translate('SELECT ISNULL(col) FROM t');

        self::assertStringContainsString('IS NULL', $result[0]);
    }

    #[Test]
    public function logFunction(): void
    {
        $result = $this->translator->translate('SELECT LOG(10) FROM t');

        self::assertStringContainsString('LOG(10)', $result[0]);
    }

    #[Test]
    public function logWithBaseFunction(): void
    {
        $result = $this->translator->translate('SELECT LOG(2, 8) FROM t');

        self::assertStringContainsString('LOG(8)', $result[0]);
        self::assertStringContainsString('LOG(2)', $result[0]);
    }

    #[Test]
    public function localtimeFunction(): void
    {
        $result = $this->translator->translate('SELECT LOCALTIME()');

        self::assertStringContainsString("datetime('now')", $result[0]);
    }

    #[Test]
    public function lowPrioritySkipped(): void
    {
        $result = $this->translator->translate('INSERT LOW_PRIORITY INTO `t` VALUES (1)');

        self::assertStringNotContainsString('LOW_PRIORITY', $result[0]);
        self::assertStringContainsString('INSERT', $result[0]);
    }

    #[Test]
    public function delayedSkipped(): void
    {
        $result = $this->translator->translate('INSERT DELAYED INTO `t` VALUES (1)');

        self::assertStringNotContainsString('DELAYED', $result[0]);
    }

    #[Test]
    public function showTablesLike(): void
    {
        $result = $this->translator->translate("SHOW TABLES LIKE 'wp_%'");

        self::assertStringContainsString('sqlite_master', $result[0]);
        self::assertStringContainsString("LIKE 'wp_%'", $result[0]);
    }

    #[Test]
    public function showFullTablesLike(): void
    {
        $result = $this->translator->translate("SHOW FULL TABLES LIKE 'wp_%'");

        self::assertStringContainsString("LIKE 'wp_%'", $result[0]);
        self::assertStringContainsString('Table_type', $result[0]);
    }

    // ── UDF end-to-end tests ──

    #[Test]
    public function endToEndLogUdf(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $driver->executeQuery('SELECT LOG(2, 8)');
        self::assertEqualsWithDelta(3.0, (float) $result->fetchOne(), 0.001);

        $driver->close();
    }

    #[Test]
    public function endToEndUnhexUdf(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $driver->executeQuery("SELECT UNHEX('48656C6C6F')");
        self::assertSame('Hello', $result->fetchOne());

        $driver->close();
    }

    #[Test]
    public function endToEndBase64Udfs(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $driver->executeQuery("SELECT TO_BASE64('Hello')");
        self::assertSame('SGVsbG8=', $result->fetchOne());

        $result = $driver->executeQuery("SELECT FROM_BASE64('SGVsbG8=')");
        self::assertSame('Hello', $result->fetchOne());

        $driver->close();
    }

    #[Test]
    public function endToEndInetUdfs(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $result = $driver->executeQuery("SELECT INET_NTOA(INET_ATON('10.0.0.1'))");
        self::assertSame('10.0.0.1', $result->fetchOne());

        $driver->close();
    }

    #[Test]
    public function endToEndLockUdfs(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        self::assertSame(1, (int) $driver->executeQuery("SELECT GET_LOCK('test', 10)")->fetchOne());
        self::assertSame(1, (int) $driver->executeQuery("SELECT RELEASE_LOCK('test')")->fetchOne());

        $driver->close();
    }

    // ── Final gap closure tests ──

    #[Test]
    public function createTableCachesDataTypes(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`id`))',
        );

        // Should contain cache INSERT statements
        $cacheInserts = array_filter($result, static fn(string $sql) => str_contains($sql, '_mysql_data_types_cache'));
        self::assertNotEmpty($cacheInserts);
    }

    #[Test]
    public function showCreateTableUsesCache(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        // Create table (with cache inserts)
        $ddl = $this->translator->translate(
            'CREATE TABLE `t` (`id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(255) NOT NULL, PRIMARY KEY (`id`))',
        );
        foreach ($ddl as $sql) {
            $driver->executeStatement($sql);
        }

        // SHOW CREATE TABLE should use cached MySQL types
        $showSql = $this->translator->translate('SHOW CREATE TABLE `t`');
        $result = $driver->executeQuery($showSql[0]);
        $row = $result->fetchAssociative();
        self::assertNotNull($row);
        self::assertStringContainsString('int', $row['Create Table']);
        self::assertStringContainsString('varchar(255)', $row['Create Table']);

        $driver->close();
    }

    #[Test]
    public function changeColumnSameNameIsNoOp(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` CHANGE `post_title` `post_title` TEXT NOT NULL');

        // Same name → type change only → no-op in SQLite (dynamic typing)
        self::assertSame([], $result);
    }

    #[Test]
    public function changeColumnRename(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` CHANGE `old_col` `new_col` TEXT NOT NULL');

        self::assertCount(1, $result);
        self::assertStringContainsString('RENAME COLUMN', $result[0]);
        self::assertStringContainsString('"old_col"', $result[0]);
        self::assertStringContainsString('"new_col"', $result[0]);
    }

    #[Test]
    public function modifyColumnIsNoOp(): void
    {
        $result = $this->translator->translate('ALTER TABLE `wp_posts` MODIFY `post_title` VARCHAR(255) NOT NULL');

        // MODIFY = type change only → no-op in SQLite
        self::assertSame([], $result);
    }

    #[Test]
    public function likeEscapeClause(): void
    {
        $result = $this->translator->translate("SELECT * FROM t WHERE name LIKE '%\\_test%'");

        self::assertStringContainsString('ESCAPE', $result[0]);
    }

    #[Test]
    public function likeWithoutEscapeAddsBackslashEscape(): void
    {
        $result = $this->translator->translate("SELECT * FROM t WHERE name LIKE '%test%'");

        // All LIKE clauses get ESCAPE '\' to match MySQL's default escape behaviour
        self::assertStringContainsString("ESCAPE '\\'", $result[0]);
    }

    #[Test]
    public function endToEndLikeEscape(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $driver->executeStatement('CREATE TABLE "t" ("id" INTEGER PRIMARY KEY, "name" TEXT)');
        $driver->executeStatement("INSERT INTO \"t\" VALUES (1, 'hello_world')");
        $driver->executeStatement("INSERT INTO \"t\" VALUES (2, 'helloXworld')");

        // LIKE with escaped underscore should match literal _
        $likeSql = $this->translator->translate("SELECT COUNT(*) FROM `t` WHERE name LIKE '%\\_world%'");
        $result = $driver->executeQuery($likeSql[0]);
        self::assertSame(1, (int) $result->fetchOne());

        $driver->close();
    }

    // ── Final gap closure tests ──

    #[Test]
    public function weekFunction(): void
    {
        $result = $this->translator->translate('SELECT WEEK(created) FROM t');

        self::assertStringContainsString("strftime('%W'", $result[0]);
    }

    #[Test]
    public function showTableStatusLike(): void
    {
        $result = $this->translator->translate("SHOW TABLE STATUS LIKE 'wp_%'");

        self::assertStringContainsString("LIKE 'wp_%'", $result[0]);
        self::assertStringContainsString('sqlite_master', $result[0]);
    }

    #[Test]
    public function checkTableDummy(): void
    {
        $result = $this->translator->translate('CHECK TABLE `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('OK', $result[0]);
        self::assertStringContainsString('check', $result[0]);
    }

    #[Test]
    public function analyzeTableDummy(): void
    {
        $result = $this->translator->translate('ANALYZE TABLE `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('OK', $result[0]);
    }

    #[Test]
    public function repairTableDummy(): void
    {
        $result = $this->translator->translate('REPAIR TABLE `wp_posts`');

        self::assertCount(1, $result);
        self::assertStringContainsString('OK', $result[0]);
    }

    #[Test]
    public function showGrantsDummy(): void
    {
        $result = $this->translator->translate('SHOW GRANTS FOR root@localhost');

        self::assertCount(1, $result);
        self::assertStringContainsString('GRANT', $result[0]);
    }

    #[Test]
    public function showCreateProcedureDummy(): void
    {
        $result = $this->translator->translate('SHOW CREATE PROCEDURE my_proc');

        self::assertCount(1, $result);
        self::assertStringContainsString('WHERE 0', $result[0]);
    }

    #[Test]
    public function showTablesExcludesCacheTable(): void
    {
        $result = $this->translator->translate('SHOW TABLES');

        // Underscore-prefixed tables (like _mysql_data_types_cache) should be excluded
        self::assertStringContainsString("NOT LIKE '\\_", $result[0]);
    }

    // ── PG4WP parity tests ──

    #[Test]
    public function convertFunction(): void
    {
        $result = $this->translator->translate('SELECT CONVERT(val, SIGNED) FROM t');

        self::assertStringContainsString('CAST(val AS INTEGER)', $result[0]);
    }

    #[Test]
    public function insertSetSyntax(): void
    {
        $result = $this->translator->translate('INSERT INTO `t` SET name = "test", status = "active"');

        self::assertStringContainsString('INSERT INTO', $result[0]);
        self::assertStringContainsString('VALUES', $result[0]);
        self::assertStringContainsString('"name"', $result[0]);
    }

    #[Test]
    public function castAsChar(): void
    {
        $result = $this->translator->translate('SELECT CAST(val AS CHAR) FROM t');

        self::assertStringContainsString('CAST(val AS TEXT)', $result[0]);
    }

    #[Test]
    public function emptyInClause(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE id IN ()');

        self::assertStringContainsString('IN (NULL)', $result[0]);
    }

    #[Test]
    public function regexpBinaryKeepsRegexp(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE name REGEXP BINARY "pattern"');

        self::assertStringContainsString('REGEXP', $result[0]);
        self::assertStringNotContainsString('BLOB', $result[0]);
    }

    #[Test]
    public function collateClauseRemoved(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE name COLLATE utf8mb4_unicode_ci = "test"');

        self::assertStringNotContainsString('COLLATE', $result[0]);
        self::assertStringNotContainsString('utf8mb4', $result[0]);
    }

    // ── WordPress compatibility tests ──

    #[Test]
    public function selectSystemVariablesDummy(): void
    {
        $result = $this->translator->translate('SELECT @@SESSION.sql_mode');

        self::assertStringContainsString("@@SESSION.sql_mode", $result[0]);
        self::assertStringContainsString("SELECT", $result[0]);
    }

    #[Test]
    public function informationSchemaTablesRewrite(): void
    {
        $result = $this->translator->translate("SELECT * FROM information_schema.tables WHERE table_schema = DATABASE()");

        self::assertStringContainsString('sqlite_master', $result[0]);
        self::assertStringNotContainsString('information_schema', $result[0]);
    }

    #[Test]
    public function deleteJoinToSubquery(): void
    {
        $result = $this->translator->translate(
            'DELETE a FROM `wp_options` a JOIN `wp_options` b ON a.option_name = b.option_name WHERE a.option_id < b.option_id',
        );

        self::assertStringContainsString('DELETE FROM', $result[0]);
        self::assertStringContainsString('rowid IN (SELECT', $result[0]);
        self::assertStringContainsString('JOIN', $result[0]);
    }

    #[Test]
    public function deleteJoinEndToEnd(): void
    {
        $driver = new \WpPack\Component\Database\Bridge\Sqlite\SqliteDriver(':memory:');
        $driver->connect();

        $driver->executeStatement('CREATE TABLE "wp_options" ("option_id" INTEGER PRIMARY KEY, "option_name" TEXT, "option_value" TEXT)');
        $driver->executeStatement("INSERT INTO \"wp_options\" VALUES (1, 'test', 'val1')");
        $driver->executeStatement("INSERT INTO \"wp_options\" VALUES (2, 'test', 'val2')");

        $deleteSql = $this->translator->translate(
            'DELETE a FROM `wp_options` a JOIN `wp_options` b ON a.option_name = b.option_name WHERE a.option_id < b.option_id',
        );
        $driver->executeStatement($deleteSql[0]);

        $result = $driver->executeQuery('SELECT COUNT(*) FROM "wp_options"');
        self::assertSame(1, (int) $result->fetchOne());

        $driver->close();
    }

    // ── Final gap closure tests ──

    #[Test]
    public function iso8601DateNormalization(): void
    {
        $result = $this->translator->translate("SELECT * FROM t WHERE created > '2024-01-15T10:30:45Z'");

        self::assertStringContainsString("'2024-01-15 10:30:45'", $result[0]);
        self::assertStringNotContainsString('T10', $result[0]);
    }

    #[Test]
    public function iso8601DateWithoutZ(): void
    {
        $result = $this->translator->translate("SELECT * FROM t WHERE created > '2024-01-15T10:30:45'");

        self::assertStringContainsString("'2024-01-15 10:30:45'", $result[0]);
    }

    #[Test]
    public function weekWithModeParameter(): void
    {
        $result = $this->translator->translate('SELECT WEEK(post_date, 1) FROM t');

        self::assertStringContainsString("strftime('%W'", $result[0]);
    }

    #[Test]
    public function dateFormatExtendedKSpecifier(): void
    {
        $result = $this->translator->translate("SELECT DATE_FORMAT(post_date, '%k:%i')");

        self::assertStringContainsString('strftime', $result[0]);
    }

    #[Test]
    public function dateFormatExtendedUSpecifier(): void
    {
        $result = $this->translator->translate("SELECT DATE_FORMAT(post_date, '%U')");

        self::assertStringContainsString('strftime', $result[0]);
    }

    // ── Coverage gap tests ──

    #[Test]
    public function curtimeFunction(): void
    {
        $result = $this->translator->translate('SELECT CURTIME()');

        self::assertStringContainsString("time('now')", $result[0]);
    }

    #[Test]
    public function currentTimestampKeyword(): void
    {
        $result = $this->translator->translate('SELECT CURRENT_TIMESTAMP');

        self::assertStringContainsString("datetime('now')", $result[0]);
    }

    #[Test]
    public function charLengthFunction(): void
    {
        $result = $this->translator->translate('SELECT CHAR_LENGTH(name) FROM t');

        self::assertStringContainsString('LENGTH(name)', $result[0]);
    }

    #[Test]
    public function characterLengthFunction(): void
    {
        $result = $this->translator->translate('SELECT CHARACTER_LENGTH(name) FROM t');

        self::assertStringContainsString('LENGTH(name)', $result[0]);
    }

    #[Test]
    public function midFunction(): void
    {
        $result = $this->translator->translate('SELECT MID(name, 2, 3) FROM t');

        self::assertStringContainsString('SUBSTR(name, 2, 3)', $result[0]);
    }

    #[Test]
    public function locateFunction(): void
    {
        $result = $this->translator->translate("SELECT LOCATE('abc', name) FROM t");

        self::assertStringContainsString('INSTR', $result[0]);
    }

    #[Test]
    public function dayOfYearFunction(): void
    {
        $result = $this->translator->translate('SELECT DAYOFYEAR(created) FROM t');

        self::assertStringContainsString("strftime('%j'", $result[0]);
    }

    #[Test]
    public function weekdayFunction(): void
    {
        $result = $this->translator->translate('SELECT WEEKDAY(created) FROM t');

        self::assertStringContainsString("strftime('%w'", $result[0]);
        self::assertStringContainsString('+ 6)', $result[0]);
    }

    #[Test]
    public function fieldFunctionSqlite(): void
    {
        $result = $this->translator->translate("SELECT FIELD(status, 'publish', 'draft', 'trash') FROM t");

        self::assertStringContainsString('CASE', $result[0]);
        self::assertStringContainsString('WHEN', $result[0]);
        self::assertStringContainsString('THEN 1', $result[0]);
        self::assertStringContainsString('THEN 3', $result[0]);
    }

    // ── FIND_IN_SET / SUBSTRING_INDEX ──

    #[Test]
    public function findInSetRewritesToInstrBasedCase(): void
    {
        $result = $this->translator->translate("SELECT FIND_IN_SET(role, role_list) FROM t");

        self::assertStringContainsString('instr(', $result[0]);
        self::assertStringContainsString('CASE', $result[0]);
    }

    #[Test]
    public function substringIndexPositiveOneUsesInstrPrefix(): void
    {
        $result = $this->translator->translate("SELECT SUBSTRING_INDEX(path, '/', 1) FROM t");

        self::assertStringContainsString("instr(path, '/')", $result[0]);
        self::assertStringContainsString('substr(path', $result[0]);
    }

    #[Test]
    public function substringIndexNegativeCountRaisesTranslationException(): void
    {
        $this->expectException(\WpPack\Component\Database\Exception\TranslationException::class);
        $this->expectExceptionMessageMatches('/SUBSTRING_INDEX.*negative/');

        $this->translator->translate("SELECT SUBSTRING_INDEX(path, '/', -1) FROM t");
    }

    // ── FULLTEXT explicit rejection ──

    // ── Spatial / LPAD / RPAD / MAKEDATE ──

    #[Test]
    public function spatialFunctionRaisesTranslationException(): void
    {
        $this->expectException(\WpPack\Component\Database\Exception\TranslationException::class);
        $this->expectExceptionMessageMatches('/spatial/i');

        $this->translator->translate("SELECT ST_Distance(pt, ST_GeomFromText('POINT(1 2)')) FROM t");
    }

    #[Test]
    public function lpadEmulatesViaZeroblob(): void
    {
        $result = $this->translator->translate("SELECT LPAD(code, 5, '0') FROM t");

        self::assertStringContainsString("substr(replace(hex(zeroblob(5)), '00', '0') || code, -(5))", $result[0]);
    }

    #[Test]
    public function rpadEmulatesViaZeroblob(): void
    {
        $result = $this->translator->translate("SELECT RPAD(code, 10, ' ') FROM t");

        self::assertStringContainsString("substr(code || replace(hex(zeroblob(10)), '00', ' '), 1, 10)", $result[0]);
    }

    #[Test]
    public function convertUsingCharsetIsStrippedForSqlite(): void
    {
        // SQLite stores everything UTF-8 by default, so CONVERT USING is
        // a semantic no-op — emit the inner expression unchanged.
        $result = $this->translator->translate('SELECT CONVERT(col USING utf8mb4) FROM t');

        self::assertMatchesRegularExpression('/SELECT\s+col\s+FROM\s+t/i', $result[0]);
        self::assertStringNotContainsString('CONVERT', $result[0]);
        self::assertStringNotContainsString('USING', $result[0]);
    }

    #[Test]
    public function makedateConvertsToDateArithmetic(): void
    {
        $result = $this->translator->translate('SELECT MAKEDATE(2024, 60) FROM t');

        self::assertStringContainsString("date((2024) || '-01-01', ((60) - 1) || ' days')", $result[0]);
    }

    #[Test]
    public function fulltextMatchAgainstRaisesTranslationException(): void
    {
        $this->expectException(\WpPack\Component\Database\Exception\TranslationException::class);
        $this->expectExceptionMessageMatches('/FULLTEXT/');

        $this->translator->translate("SELECT * FROM posts WHERE MATCH(content) AGAINST('wordpress')");
    }

    #[Test]
    public function fulltextMatchAgainstBooleanModeAlsoRejected(): void
    {
        $this->expectException(\WpPack\Component\Database\Exception\TranslationException::class);

        $this->translator->translate("SELECT * FROM t WHERE MATCH(col) AGAINST('term' IN BOOLEAN MODE)");
    }

    // ── STR_TO_DATE ──

    #[Test]
    public function strToDateIsoDateOnlyUsesDateFunction(): void
    {
        $result = $this->translator->translate("SELECT STR_TO_DATE(col, '%Y-%m-%d') FROM t");

        self::assertStringContainsString('date(col)', $result[0]);
    }

    #[Test]
    public function strToDateIsoDateTimeUsesDatetimeFunction(): void
    {
        $result = $this->translator->translate("SELECT STR_TO_DATE(col, '%Y-%m-%d %H:%i:%s') FROM t");

        self::assertStringContainsString('datetime(col)', $result[0]);
    }

    #[Test]
    public function strToDateUnknownFormatFallsBackToDatetime(): void
    {
        // Non-ISO formats cannot be safely parsed by SQLite. The translator
        // falls through to datetime() which at least reaches a function that
        // exists, rather than emitting a strftime inverse that does not.
        $result = $this->translator->translate("SELECT STR_TO_DATE(col, '%d/%m/%Y') FROM t");

        self::assertStringContainsString('datetime(col)', $result[0]);
    }

    // ── Extra MySQL function mappings ──

    #[Test]
    public function lastDayUsesDateModifiers(): void
    {
        $result = $this->translator->translate('SELECT LAST_DAY(d) FROM t');

        self::assertStringContainsString("date(d, 'start of month', '+1 month', '-1 day')", $result[0]);
    }

    #[Test]
    public function dayNameReturnsCaseMap(): void
    {
        $result = $this->translator->translate('SELECT DAYNAME(d) FROM t');

        self::assertStringContainsString("strftime('%w', d)", $result[0]);
        self::assertStringContainsString("'Sunday'", $result[0]);
        self::assertStringContainsString("'Saturday'", $result[0]);
    }

    #[Test]
    public function monthNameReturnsCaseMap(): void
    {
        $result = $this->translator->translate('SELECT MONTHNAME(d) FROM t');

        self::assertStringContainsString("'January'", $result[0]);
        self::assertStringContainsString("'December'", $result[0]);
    }

    #[Test]
    public function quarterUsesStrftimeMonthMath(): void
    {
        $result = $this->translator->translate('SELECT QUARTER(d) FROM t');

        self::assertStringContainsString("(CAST(strftime('%m', d) AS INTEGER) - 1) / 3 + 1", $result[0]);
    }

    #[Test]
    public function spaceFunctionUsesZeroblobReplaceTrick(): void
    {
        $result = $this->translator->translate('SELECT SPACE(5) FROM t');

        self::assertStringContainsString("replace(hex(zeroblob(5)), '00', ' ')", $result[0]);
    }

    #[Test]
    public function timeToSecDecomposesBySubstring(): void
    {
        $result = $this->translator->translate('SELECT TIME_TO_SEC(t) FROM t');

        self::assertStringContainsString("instr(t, ':')", $result[0]);
        self::assertStringContainsString('3600', $result[0]);
    }

    #[Test]
    public function timeToSecHandlesNegativeSign(): void
    {
        // MySQL TIME accepts negative values ('-01:00:05'). The previous
        // rewrite computed `-1 * 3600 + 0 * 60 + 5` and got -3595 instead
        // of -3605 because only the hours substring carried the sign.
        // The fix applies an explicit sign multiplier.
        $result = $this->translator->translate('SELECT TIME_TO_SEC(t) FROM t');

        self::assertStringContainsString("substr(t, 1, 1) = '-'", $result[0]);
        self::assertStringContainsString('ABS(CAST(substr(t', $result[0]);
    }

    #[Test]
    public function secToTimeUsesPrintfFormat(): void
    {
        $result = $this->translator->translate('SELECT SEC_TO_TIME(3605) FROM t');

        self::assertStringContainsString("printf('%02d:%02d:%02d'", $result[0]);
    }

    // ── WP_Query meta_query / tax_query shapes ──

    #[Test]
    public function metaQueryNestedOrAndPreservesBooleanStructure(): void
    {
        // Shape that WP_Query emits for a meta_query relation=AND of two
        // OR groups. The translator must preserve every AND / OR / paren
        // boundary or the filter semantics silently change.
        $sql = <<<'SQL'
SELECT wptests_posts.*
FROM wptests_posts
INNER JOIN wptests_postmeta ON wptests_posts.ID = wptests_postmeta.post_id
INNER JOIN wptests_postmeta AS mt1 ON wptests_posts.ID = mt1.post_id
WHERE wptests_posts.post_status = 'publish'
  AND (
    (wptests_postmeta.meta_key = 'color' AND wptests_postmeta.meta_value IN ('red', 'blue'))
    OR
    (mt1.meta_key = 'size' AND mt1.meta_value IN ('L', 'XL'))
  )
GROUP BY wptests_posts.ID
ORDER BY wptests_posts.post_date DESC
LIMIT 10
SQL;

        $result = $this->translator->translate($sql);
        self::assertNotEmpty($result);

        $out = $result[0];

        // Both OR branches must survive.
        self::assertStringContainsString('wptests_postmeta.meta_key', $out);
        self::assertStringContainsString('mt1.meta_key', $out);

        // The outer AND joining the two OR groups must survive.
        self::assertMatchesRegularExpression('/\bAND\b/i', $out);
        self::assertMatchesRegularExpression('/\bOR\b/i', $out);
    }

    #[Test]
    public function taxQueryWithRelationOrPreservesInClauses(): void
    {
        $sql = <<<'SQL'
SELECT wptests_posts.*
FROM wptests_posts
WHERE wptests_posts.ID IN (
  SELECT object_id FROM wptests_term_relationships WHERE term_taxonomy_id IN (1, 2, 3)
)
OR wptests_posts.ID IN (
  SELECT object_id FROM wptests_term_relationships WHERE term_taxonomy_id IN (4, 5)
)
SQL;

        $result = $this->translator->translate($sql);
        self::assertStringContainsString('term_taxonomy_id IN (1, 2, 3)', $result[0]);
        self::assertStringContainsString('term_taxonomy_id IN (4, 5)', $result[0]);
    }

    // ── CTE / UNION preservation ──

    #[Test]
    public function withCteIsPreservedVerbatim(): void
    {
        $sql = 'WITH latest AS (SELECT id FROM wptests_posts ORDER BY post_date DESC LIMIT 5) SELECT * FROM wptests_posts WHERE id IN (SELECT id FROM latest)';
        $result = $this->translator->translate($sql);

        self::assertNotEmpty($result);
        self::assertStringContainsString('WITH latest', $result[0]);
        // Inner LIMIT must not be stripped by translator quirks.
        self::assertStringContainsString('LIMIT 5', $result[0]);
    }

    #[Test]
    public function unionPreservesBothBranches(): void
    {
        $sql = "SELECT id FROM wptests_posts WHERE post_status = 'publish' UNION SELECT id FROM wptests_posts WHERE post_status = 'draft'";
        $result = $this->translator->translate($sql);

        self::assertStringContainsString('UNION', $result[0]);
        self::assertStringContainsString("post_status = 'publish'", $result[0]);
        self::assertStringContainsString("post_status = 'draft'", $result[0]);
    }

    // ── DELETE JOIN ──

    #[Test]
    public function deleteJoinPreservesLeftJoinKeyword(): void
    {
        $result = $this->translator->translate('DELETE a FROM t1 a LEFT JOIN t2 b ON a.id = b.id WHERE b.id IS NULL');

        self::assertStringContainsString('LEFT JOIN', $result[0]);
        self::assertStringContainsString('ON a.id = b.id', $result[0]);
    }

    #[Test]
    public function deleteJoinPreservesInnerJoinKeyword(): void
    {
        $result = $this->translator->translate('DELETE a FROM t1 a INNER JOIN t2 b ON a.id = b.id');

        self::assertStringContainsString('INNER JOIN', $result[0]);
    }

    #[Test]
    public function deleteJoinPreservesUsingClause(): void
    {
        // USING clause was dropped entirely by the previous implementation,
        // turning the cross-row filter into an unconditional delete.
        $result = $this->translator->translate('DELETE a FROM t1 a JOIN t2 b USING (id)');

        self::assertStringContainsString('USING (id)', $result[0]);
    }

    #[Test]
    public function deleteJoinPreservesMultiColumnUsing(): void
    {
        $result = $this->translator->translate('DELETE a FROM t1 a JOIN t2 b USING (id, name)');

        self::assertStringContainsString('USING (id, name)', $result[0]);
    }

    #[Test]
    public function deleteJoinPreservesNestedAndOr(): void
    {
        $result = $this->translator->translate('DELETE a FROM t1 a JOIN t2 b ON a.x = b.x AND (a.y = 1 OR a.z = 2)');

        self::assertStringContainsString('a.x = b.x AND (a.y = 1 OR a.z = 2)', $result[0]);
    }

    #[Test]
    public function groupConcatWithSeparator(): void
    {
        $result = $this->translator->translate("SELECT GROUP_CONCAT(name SEPARATOR '|') FROM t GROUP BY id");

        self::assertStringContainsString("group_concat(name, '|')", $result[0]);
    }

    #[Test]
    public function groupConcatWithoutSeparator(): void
    {
        $result = $this->translator->translate('SELECT GROUP_CONCAT(name) FROM t GROUP BY id');

        self::assertStringContainsString("group_concat(name, ',')", $result[0]);
    }

    #[Test]
    public function groupConcatWithDistinct(): void
    {
        // SQLite's group_concat supports DISTINCT as a modifier; we must
        // preserve the space between DISTINCT and the column so it remains
        // a syntactic keyword (rather than collapsing to `DISTINCTname`).
        $result = $this->translator->translate("SELECT GROUP_CONCAT(DISTINCT name SEPARATOR '|') FROM t");

        self::assertStringContainsString("group_concat(DISTINCT name, '|')", $result[0]);
    }

    #[Test]
    public function groupConcatWithOrderByPreservesKeywordSpacing(): void
    {
        // SQLite native group_concat does not accept ORDER BY inside the
        // call; the best we can do is preserve the MySQL-shaped argument so
        // the engine reports a clear syntax error instead of a malformed
        // identifier like `nameORDER BYid`. This test pins the spacing
        // contract regardless of whether SQLite accepts the statement.
        $result = $this->translator->translate("SELECT GROUP_CONCAT(name ORDER BY id SEPARATOR '|') FROM t");

        self::assertStringContainsString('name ORDER BY id', $result[0]);
        self::assertStringNotContainsString('nameORDER BYid', $result[0]);
    }

    #[Test]
    public function groupConcatWithDistinctAndOrderByAndDescSpacing(): void
    {
        $result = $this->translator->translate("SELECT GROUP_CONCAT(DISTINCT name ORDER BY id DESC SEPARATOR '|') FROM t");

        self::assertStringContainsString('DISTINCT name ORDER BY id DESC', $result[0]);
        self::assertStringNotContainsString('DISTINCTname', $result[0]);
    }

    #[Test]
    public function highPrioritySkipped(): void
    {
        $result = $this->translator->translate('INSERT HIGH_PRIORITY INTO `t` VALUES (1)');

        self::assertStringNotContainsString('HIGH_PRIORITY', $result[0]);
        self::assertStringContainsString('INSERT', $result[0]);
    }

    // ── Parser failure surface ──

    #[Test]
    public function unparseableSqlRaisesTranslationException(): void
    {
        $this->expectException(\WpPack\Component\Database\Exception\TranslationException::class);

        // A stream of punctuation that phpmyadmin/sql-parser can't produce
        // any statement for. The translator must refuse to silently hand the
        // raw bytes to SQLite.
        $this->translator->translate('!!! @@@ ### $$$ %%%');
    }

    #[Test]
    public function standaloneRollbackIsNotFatal(): void
    {
        // phpmyadmin/sql-parser flags bare ROLLBACK with a context warning
        // ("No transaction was previously started") while still producing a
        // statement. The translator must log-and-continue, not throw —
        // callers rely on being able to emit ROLLBACK after START TRANSACTION.
        $result = $this->translator->translate('ROLLBACK');

        self::assertNotEmpty($result);
        self::assertStringContainsString('ROLLBACK', $result[0]);
    }

    #[Test]
    public function parserWarningsAreLoggedWhenLoggerProvided(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('warning');

        $translator = new SqliteQueryTranslator($logger);

        $translator->translate('ROLLBACK');
    }
}
