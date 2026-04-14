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

        self::assertCount(1, $result);
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

        self::assertCount(1, $result);
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
}
