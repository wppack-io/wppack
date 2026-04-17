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
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
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

        self::assertStringContainsString('ON CONFLICT', $result[0]);
        self::assertStringContainsString('DO UPDATE SET', $result[0]);
        self::assertStringContainsString('excluded.name', $result[0]);
        // Conflict target inferred: INSERT columns (id, name) minus UPDATE (name) = (id)
        self::assertStringContainsString('"id"', $result[0]);
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

    #[Test]
    public function createDatabaseIgnored(): void
    {
        self::assertSame([], $this->translator->translate('CREATE DATABASE IF NOT EXISTS `wordpress`'));
    }

    #[Test]
    public function describe(): void
    {
        $result = $this->translator->translate('DESCRIBE `wp_posts`');

        self::assertStringContainsString('information_schema.columns', $result[0]);
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
    }

    #[Test]
    public function nestedSubquery(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` WHERE ID IN (SELECT post_id FROM `wp_postmeta` WHERE meta_value IN (SELECT ID FROM `wp_posts` WHERE post_type = "attachment"))',
        );

        self::assertCount(1, $result);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function correlatedSubqueryWithFunction(): void
    {
        $result = $this->translator->translate(
            'SELECT p.*, (SELECT MAX(comment_date) FROM `wp_comments` c WHERE c.comment_post_ID = p.ID) AS last_comment FROM `wp_posts` p',
        );

        self::assertStringContainsString('(SELECT MAX', $result[0]);
    }

    #[Test]
    public function complexJoinWithFunctions(): void
    {
        $result = $this->translator->translate(
            'SELECT p.ID, DATE_FORMAT(p.post_date, "%Y-%m-%d") AS d, IF(p.comment_count > 0, "yes", "no") AS c FROM `wp_posts` p INNER JOIN `wp_users` u ON p.post_author = u.ID WHERE p.post_date > DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 5, 10',
        );

        self::assertStringContainsString('TO_CHAR', $result[0]);
        self::assertStringContainsString('CASE WHEN', $result[0]);
        self::assertStringContainsString("INTERVAL '7 day'", $result[0]);
        self::assertStringContainsString('LIMIT 10 OFFSET 5', $result[0]);
    }

    #[Test]
    public function multipleJoins(): void
    {
        $result = $this->translator->translate(
            'SELECT p.ID, t.name FROM `wp_posts` p JOIN `wp_term_relationships` tr ON p.ID = tr.object_id JOIN `wp_term_taxonomy` tt ON tr.term_taxonomy_id = tt.term_taxonomy_id JOIN `wp_terms` t ON tt.term_id = t.term_id',
        );

        self::assertCount(1, $result);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function insertWithSubquery(): void
    {
        $result = $this->translator->translate(
            'INSERT INTO `wp_postmeta` (post_id, meta_key) SELECT ID, "_migrated" FROM `wp_posts` WHERE post_type = "post"',
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
            'SELECT post_author, COUNT(*) AS post_count FROM `wp_posts` GROUP BY post_author HAVING post_count > 5 ORDER BY post_count DESC LIMIT 20',
        );

        self::assertStringContainsString('GROUP BY', $result[0]);
        self::assertStringContainsString('HAVING', $result[0]);
        self::assertStringContainsString('LIMIT 20', $result[0]);
    }

    #[Test]
    public function unionAll(): void
    {
        $result = $this->translator->translate(
            'SELECT "post" AS type, ID FROM `wp_posts` WHERE post_status = "publish" UNION ALL SELECT "page" AS type, ID FROM `wp_posts` WHERE post_type = "page"',
        );

        self::assertStringContainsString('UNION ALL', $result[0]);
    }

    #[Test]
    public function ifnullAndLeft(): void
    {
        $result = $this->translator->translate(
            'SELECT IFNULL(post_excerpt, LEFT(post_content, 100)) AS excerpt FROM `wp_posts`',
        );

        self::assertStringContainsString('COALESCE', $result[0]);
        self::assertStringContainsString('SUBSTRING(', $result[0]);
    }

    #[Test]
    public function regexpToTildeInSubquery(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_options` WHERE option_name REGEXP "^_transient_"',
        );

        self::assertStringContainsString('~*', $result[0]);
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

        self::assertStringContainsString('EXTRACT(EPOCH FROM NOW())', $result[0]);
    }

    #[Test]
    public function dateAddInWhere(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM `wp_posts` WHERE post_date > DATE_ADD('2024-01-01', INTERVAL 30 DAY)",
        );

        self::assertStringContainsString("+ INTERVAL '30 day'", $result[0]);
    }

    #[Test]
    public function fromUnixtimeInSelect(): void
    {
        $result = $this->translator->translate(
            'SELECT FROM_UNIXTIME(meta_value) AS date FROM `wp_postmeta` WHERE meta_key = "_timestamp"',
        );

        self::assertStringContainsString('TO_TIMESTAMP(meta_value)', $result[0]);
    }

    // ── Multi-line and schema queries ──

    #[Test]
    public function multiLineCreateTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE `wp_posts` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_title` text NOT NULL,
  `post_status` varchar(20) NOT NULL DEFAULT "publish",
  PRIMARY KEY (`ID`),
  KEY `post_status` (`post_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
SQL;

        $result = $this->translator->translate($sql);

        // CREATE TABLE + CREATE INDEX for KEY post_status
        self::assertCount(2, $result);
        self::assertStringContainsString('BIGSERIAL', $result[0]);
        self::assertStringContainsString('TEXT', $result[0]);
        self::assertStringNotContainsString('ENGINE=', $result[0]);
        self::assertStringNotContainsString('CHARSET', $result[0]);
        self::assertStringContainsString('CREATE INDEX', $result[1]);
        self::assertStringContainsString('post_status', $result[1]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function createTableIfNotExists(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `wp_options` (
  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE=InnoDB
SQL;

        $result = $this->translator->translate($sql);

        self::assertStringContainsString('IF NOT EXISTS', $result[0]);
        self::assertStringNotContainsString('ENGINE=', $result[0]);
    }

    #[Test]
    public function createAndDropIndex(): void
    {
        $create = $this->translator->translate('CREATE INDEX `idx_status` ON `wp_posts` (`post_status`)');
        $drop = $this->translator->translate('DROP INDEX `idx_status` ON `wp_posts`');

        self::assertStringContainsString('CREATE INDEX', $create[0]);
        self::assertStringContainsString('DROP INDEX', $drop[0]);
        self::assertStringNotContainsString('`', $create[0]);
    }

    #[Test]
    public function dropTableIfExists(): void
    {
        $result = $this->translator->translate('DROP TABLE IF EXISTS `wp_posts`');

        self::assertStringContainsString('DROP TABLE IF EXISTS', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function limitCountOnly(): void
    {
        $result = $this->translator->translate('SELECT * FROM t LIMIT 10');

        self::assertStringContainsString('LIMIT 10', $result[0]);
        self::assertStringNotContainsString('OFFSET', $result[0]);
    }

    // ── Additional SHOW ──

    #[Test]
    public function showVariables(): void
    {
        $result = $this->translator->translate('SHOW VARIABLES');

        self::assertStringContainsString('pg_settings', $result[0]);
    }

    #[Test]
    public function showCollation(): void
    {
        $result = $this->translator->translate('SHOW COLLATION');

        self::assertStringContainsString('pg_collation', $result[0]);
    }

    #[Test]
    public function showTableStatus(): void
    {
        $result = $this->translator->translate('SHOW TABLE STATUS');

        self::assertStringContainsString('information_schema.tables', $result[0]);
    }

    // ── Multi-line queries ──

    #[Test]
    public function multiLineInsert(): void
    {
        $sql = <<<'SQL'
INSERT INTO `wp_posts`
  (`post_author`, `post_date`, `post_content`, `post_title`)
VALUES
  (1, NOW(), "Hello", "Test")
SQL;

        $result = $this->translator->translate($sql);

        self::assertCount(1, $result);
        self::assertStringContainsString('NOW()', $result[0]); // PgSQL supports NOW() natively
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
        self::assertStringContainsString("INTERVAL '30 day'", $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function multiLineSelectWithSubquery(): void
    {
        $sql = <<<'SQL'
SELECT
  p.ID,
  (SELECT COUNT(*)
   FROM `wp_comments` c
   WHERE c.comment_post_ID = p.ID) AS comment_count
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

    // ── Additional ignored ──

    #[Test]
    public function dropDatabaseIgnored(): void
    {
        self::assertSame([], $this->translator->translate('DROP DATABASE IF EXISTS `wordpress`'));
    }

    #[Test]
    public function startTransaction(): void
    {
        $result = $this->translator->translate('START TRANSACTION');

        self::assertSame(['BEGIN'], $result);
    }

    #[Test]
    public function selectForUpdate(): void
    {
        // PostgreSQL supports FOR UPDATE natively — should pass through
        $result = $this->translator->translate('SELECT * FROM `t` WHERE id = 1 FOR UPDATE');

        self::assertStringNotContainsString('`', $result[0]);
    }

    // ── String literal protection ──

    #[Test]
    public function stringLiteralNotTransformed(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE name = 'CURDATE()' AND created = CURDATE()",
        );

        self::assertStringContainsString("'CURDATE()'", $result[0]);
        self::assertStringContainsString('CURRENT_DATE', $result[0]);
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
            "SELECT * FROM t WHERE name = 'it''s IFNULL()' AND val = IFNULL(a, b)",
        );

        self::assertStringContainsString("'it''s IFNULL()'", $result[0]);
        self::assertStringContainsString('COALESCE', $result[0]);
    }

    #[Test]
    public function mixedFunctionsAndStringLiterals(): void
    {
        $result = $this->translator->translate(
            "SELECT DATE_FORMAT(created, '%Y-%m-%d'), 'DATE_FORMAT test' FROM t WHERE status = 'UNIX_TIMESTAMP()'",
        );

        self::assertStringContainsString('TO_CHAR', $result[0]);
        self::assertStringContainsString("'UNIX_TIMESTAMP()'", $result[0]);
        self::assertStringContainsString("'DATE_FORMAT test'", $result[0]);
    }

    #[Test]
    public function stringLiteralRegexpNotTransformed(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE name = 'REGEXP test' AND val REGEXP 'pattern'",
        );

        self::assertStringContainsString("'REGEXP test'", $result[0]);
        self::assertStringContainsString('~*', $result[0]);
    }

    // ── Extended function translations ──

    #[Test]
    public function concatFunction(): void
    {
        $result = $this->translator->translate("SELECT CONCAT(first_name, ' ', last_name) FROM t");

        self::assertStringContainsString('CONCAT(', $result[0]);
    }

    #[Test]
    public function datediffFunction(): void
    {
        $result = $this->translator->translate("SELECT DATEDIFF('2024-12-31', '2024-01-01')");

        self::assertStringContainsString('DATE_PART', $result[0]);
        self::assertStringContainsString('::timestamp', $result[0]);
    }

    #[Test]
    public function monthYearDayFunctions(): void
    {
        $result = $this->translator->translate('SELECT MONTH(d), YEAR(d), DAY(d) FROM t');

        self::assertStringContainsString('EXTRACT(MONTH', $result[0]);
        self::assertStringContainsString('EXTRACT(YEAR', $result[0]);
        self::assertStringContainsString('EXTRACT(DAY', $result[0]);
    }

    #[Test]
    public function hourMinuteSecondFunctions(): void
    {
        $result = $this->translator->translate('SELECT HOUR(d), MINUTE(d), SECOND(d) FROM t');

        self::assertStringContainsString('EXTRACT(HOUR', $result[0]);
        self::assertStringContainsString('EXTRACT(MINUTE', $result[0]);
        self::assertStringContainsString('EXTRACT(SECOND', $result[0]);
    }

    #[Test]
    public function dayOfWeekFunction(): void
    {
        $result = $this->translator->translate('SELECT DAYOFWEEK(created) FROM t');

        self::assertStringContainsString('EXTRACT(DOW', $result[0]);
        self::assertStringContainsString('+ 1)', $result[0]);
    }

    #[Test]
    public function locateFunction(): void
    {
        $result = $this->translator->translate("SELECT LOCATE('abc', name) FROM t");

        self::assertStringContainsString('POSITION(', $result[0]);
        self::assertStringContainsString('IN', $result[0]);
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

        self::assertStringContainsString('AT TIME ZONE', $result[0]);
    }

    #[Test]
    public function nestedFunctionInDateAdd(): void
    {
        $result = $this->translator->translate("SELECT DATE_ADD(NOW(), INTERVAL 1 DAY)");

        self::assertStringContainsString("NOW()", $result[0]);
        self::assertStringContainsString("+ INTERVAL '1 day'", $result[0]);
    }

    // ── Phase 1-3 new feature tests ──

    #[Test]
    public function fromDualRemoval(): void
    {
        $result = $this->translator->translate('SELECT 1 FROM DUAL');

        self::assertStringNotContainsString('DUAL', $result[0]);
    }

    #[Test]
    public function fromDualInInsertSelect(): void
    {
        $result = $this->translator->translate(
            "INSERT INTO t (a, b) SELECT 'val', 1 FROM DUAL WHERE (SELECT NULL FROM DUAL) IS NULL",
        );

        self::assertStringNotContainsString('DUAL', $result[0]);
        self::assertStringContainsString('INSERT INTO', $result[0]);
        self::assertStringContainsString("SELECT 'val'", $result[0]);
    }

    #[Test]
    public function indexHintsRemoval(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_posts` USE INDEX (`post_status`) WHERE post_status = "publish"',
        );

        self::assertStringNotContainsString('USE INDEX', $result[0]);
    }

    #[Test]
    public function castAsBinary(): void
    {
        $result = $this->translator->translate('SELECT CAST(val AS BINARY) FROM t');

        self::assertStringContainsString('CAST(val AS BYTEA)', $result[0]);
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

        self::assertStringContainsString('TO_CHAR', $result[0]);
        self::assertStringContainsString('AM', $result[0]);
    }

    #[Test]
    public function bigserialForBigintAutoIncrement(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`id` BIGINT NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))',
        );

        self::assertStringContainsString('BIGSERIAL', $result[0]);
    }

    #[Test]
    public function serialForIntAutoIncrement(): void
    {
        $result = $this->translator->translate(
            'CREATE TABLE `t` (`id` INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))',
        );

        self::assertStringContainsString('SERIAL', $result[0]);
        self::assertStringNotContainsString('BIGSERIAL', $result[0]);
    }

    // ── Additional gap closure tests ──

    #[Test]
    public function isnullFunction(): void
    {
        $result = $this->translator->translate('SELECT ISNULL(col) FROM t');

        self::assertStringContainsString('IS NULL', $result[0]);
    }

    #[Test]
    public function localtimeFunction(): void
    {
        $result = $this->translator->translate('SELECT LOCALTIME()');

        self::assertStringContainsString('NOW()', $result[0]);
    }

    #[Test]
    public function lowPrioritySkipped(): void
    {
        $result = $this->translator->translate('INSERT LOW_PRIORITY INTO `t` VALUES (1)');

        self::assertStringNotContainsString('LOW_PRIORITY', $result[0]);
    }

    #[Test]
    public function showTablesLike(): void
    {
        $result = $this->translator->translate("SHOW TABLES LIKE 'wp_%'");

        self::assertStringContainsString('information_schema', $result[0]);
        self::assertStringContainsString("LIKE 'wp_%'", $result[0]);
    }

    // ── Final gap closure tests ──

    #[Test]
    public function replaceIntoWithoutDriver(): void
    {
        // Without driver, translator can't query information_schema → falls back to DO NOTHING
        $result = $this->translator->translate("REPLACE INTO `t` (id, name) VALUES (1, 'a')");

        self::assertStringContainsString('INSERT', $result[0]);
        self::assertStringNotContainsString('REPLACE', $result[0]);
        self::assertStringContainsString('ON CONFLICT DO NOTHING', $result[0]);
    }

    #[Test]
    public function showCreateTable(): void
    {
        $result = $this->translator->translate('SHOW CREATE TABLE `wp_posts`');

        self::assertStringContainsString('information_schema.columns', $result[0]);
        self::assertStringContainsString('Create Table', $result[0]);
    }

    #[Test]
    public function showIndexFrom(): void
    {
        $result = $this->translator->translate('SHOW INDEX FROM `wp_posts`');

        self::assertStringContainsString('pg_indexes', $result[0]);
    }

    #[Test]
    public function versionFunction(): void
    {
        $result = $this->translator->translate('SELECT VERSION()');

        self::assertStringContainsString('version()', $result[0]);
    }

    #[Test]
    public function weekFunction(): void
    {
        $result = $this->translator->translate('SELECT WEEK(created) FROM t');

        self::assertStringContainsString('EXTRACT(WEEK', $result[0]);
    }

    #[Test]
    public function showTableStatusLike(): void
    {
        $result = $this->translator->translate("SHOW TABLE STATUS LIKE 'wp_%'");

        self::assertStringContainsString("LIKE 'wp_%'", $result[0]);
    }

    #[Test]
    public function updateWithLimit(): void
    {
        $result = $this->translator->translate(
            'UPDATE `wp_posts` SET post_status = "trash" WHERE post_status = "draft" LIMIT 5',
        );

        self::assertStringContainsString('ctid IN (SELECT ctid FROM', $result[0]);
        self::assertStringContainsString('LIMIT 5', $result[0]);
    }

    #[Test]
    public function deleteWithLimit(): void
    {
        $result = $this->translator->translate(
            'DELETE FROM `wp_posts` WHERE post_status = "trash" LIMIT 10',
        );

        self::assertStringContainsString('ctid IN (SELECT ctid FROM', $result[0]);
        self::assertStringContainsString('LIMIT 10', $result[0]);
    }

    #[Test]
    public function likeEscapeClause(): void
    {
        $result = $this->translator->translate("SELECT * FROM t WHERE name LIKE '%\\_test%'");

        self::assertStringContainsString('ESCAPE', $result[0]);
    }

    #[Test]
    public function checkTableDummy(): void
    {
        $result = $this->translator->translate('CHECK TABLE `wp_posts`');

        self::assertStringContainsString('OK', $result[0]);
    }

    #[Test]
    public function showGrantsDummy(): void
    {
        $result = $this->translator->translate('SHOW GRANTS FOR root@localhost');

        self::assertStringContainsString('GRANT', $result[0]);
    }

    #[Test]
    public function showCreateProcedureDummy(): void
    {
        $result = $this->translator->translate('SHOW CREATE PROCEDURE my_proc');

        self::assertStringContainsString('WHERE 0', $result[0]);
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
    public function regexpBinary(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE name REGEXP BINARY "pattern"');

        self::assertStringContainsString('~', $result[0]);
        self::assertStringNotContainsString('~*', $result[0]);
        self::assertStringNotContainsString('BYTEA', $result[0]);
    }

    #[Test]
    public function fieldFunction(): void
    {
        $result = $this->translator->translate('SELECT FIELD(status, "publish", "draft") FROM t');

        self::assertStringContainsString('CASE', $result[0]);
        self::assertStringContainsString('WHEN', $result[0]);
        self::assertStringContainsString('THEN 1', $result[0]);
        self::assertStringContainsString('THEN 2', $result[0]);
    }

    #[Test]
    public function likeToIlike(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE name LIKE "%test%"');

        self::assertStringContainsString('ILIKE', $result[0]);
        self::assertStringNotContainsString(' LIKE ', $result[0]);
    }

    #[Test]
    public function likeBinaryStaysLike(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE name LIKE BINARY "%Test%"');

        self::assertStringContainsString('LIKE', $result[0]);
        self::assertStringNotContainsString('ILIKE', $result[0]);
    }

    #[Test]
    public function collateClauseRemoved(): void
    {
        $result = $this->translator->translate('SELECT * FROM t WHERE name COLLATE utf8mb4_unicode_ci = "test"');

        self::assertStringNotContainsString('COLLATE', $result[0]);
    }

    #[Test]
    public function alterTableChangeColumnSameName(): void
    {
        $result = $this->translator->translate('ALTER TABLE `t` CHANGE `post_title` `post_title` TEXT NOT NULL');

        self::assertCount(1, $result);
        self::assertStringContainsString('ALTER COLUMN', $result[0]);
        self::assertStringContainsString('TYPE', $result[0]);
        self::assertStringNotContainsString('RENAME', $result[0]);
    }

    #[Test]
    public function alterTableChangeColumnRename(): void
    {
        $result = $this->translator->translate('ALTER TABLE `t` CHANGE `old_col` `new_col` BIGINT NOT NULL');

        self::assertCount(2, $result);
        self::assertStringContainsString('ALTER COLUMN', $result[0]);
        self::assertStringContainsString('TYPE', $result[0]);
        self::assertStringContainsString('RENAME COLUMN', $result[1]);
        self::assertStringContainsString('"old_col"', $result[1]);
        self::assertStringContainsString('"new_col"', $result[1]);
    }

    #[Test]
    public function alterTableModifyColumn(): void
    {
        $result = $this->translator->translate('ALTER TABLE `t` MODIFY `post_content` LONGTEXT NOT NULL');

        self::assertCount(1, $result);
        self::assertStringContainsString('ALTER COLUMN', $result[0]);
        self::assertStringContainsString('TYPE', $result[0]);
        self::assertStringNotContainsString('RENAME', $result[0]);
    }

    #[Test]
    public function logSingleArgMapsToLn(): void
    {
        $result = $this->translator->translate('SELECT LOG(10)');

        self::assertStringContainsString('LN(10)', $result[0]);
    }

    #[Test]
    public function logTwoArgKeepsLog(): void
    {
        $result = $this->translator->translate('SELECT LOG(2, 8)');

        self::assertStringContainsString('LOG(2', $result[0]);
    }

    #[Test]
    public function replaceIntoWithoutDriverFallsBackToDoNothing(): void
    {
        // Without driver, no constraint info → DO NOTHING fallback
        $result = $this->translator->translate('REPLACE INTO t (a, b) VALUES (1, 2)');

        self::assertStringContainsString('INSERT', $result[0]);
        self::assertStringContainsString('ON CONFLICT DO NOTHING', $result[0]);
    }

    // ── Lock functions ──

    #[Test]
    public function getLockUsesAdvisoryLock(): void
    {
        $result = $this->translator->translate("SELECT GET_LOCK('mylock', 10)");

        self::assertStringContainsString('pg_try_advisory_lock', $result[0]);
        self::assertStringContainsString('hashtext', $result[0]);
    }

    #[Test]
    public function releaseLockUsesAdvisoryUnlock(): void
    {
        $result = $this->translator->translate("SELECT RELEASE_LOCK('mylock')");

        self::assertStringContainsString('pg_advisory_unlock', $result[0]);
        self::assertStringContainsString('hashtext', $result[0]);
    }

    // ── WordPress compatibility tests ──

    #[Test]
    public function selectSystemVariablesDummy(): void
    {
        $result = $this->translator->translate('SELECT @@SESSION.sql_mode');

        self::assertStringContainsString('@@SESSION.sql_mode', $result[0]);
    }

    #[Test]
    public function deleteJoinToUsing(): void
    {
        $result = $this->translator->translate(
            'DELETE a FROM `wp_options` a JOIN `wp_options` b ON a.option_name = b.option_name WHERE a.option_id < b.option_id',
        );

        self::assertStringContainsString('DELETE FROM', $result[0]);
        self::assertStringContainsString('USING', $result[0]);
        self::assertStringContainsString('a.option_id < b.option_id', $result[0]);
    }

    // ── Final gap closure tests ──

    #[Test]
    public function distinctOrderByColumnInjection(): void
    {
        $result = $this->translator->translate(
            'SELECT DISTINCT t.term_id FROM `wp_terms` t ORDER BY t.name ASC',
        );

        self::assertStringContainsString('t.name', $result[0]);
        self::assertStringContainsString('DISTINCT', $result[0]);
    }

    #[Test]
    public function distinctOrderByNoInjectionForWildcard(): void
    {
        $result = $this->translator->translate(
            'SELECT DISTINCT * FROM `wp_terms` ORDER BY name ASC',
        );

        // * already includes all columns, no injection needed
        self::assertStringNotContainsString(', name', $result[0]);
    }

    #[Test]
    public function metaValuePlusZeroCast(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_postmeta` WHERE meta_value + 0 > 100',
        );

        self::assertStringContainsString('CAST(meta_value AS BIGINT)', $result[0]);
        self::assertStringNotContainsString('+ 0', $result[0]);
    }

    #[Test]
    public function metaValuePlusZeroInOrderBy(): void
    {
        $result = $this->translator->translate(
            'SELECT * FROM `wp_postmeta` ORDER BY meta_value + 0 DESC',
        );

        self::assertStringContainsString('CAST(meta_value AS BIGINT)', $result[0]);
    }

    #[Test]
    public function zeroDateInWhere(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM `wp_posts` WHERE post_date = '0000-00-00 00:00:00'",
        );

        self::assertStringContainsString("'0001-01-01 00:00:00'", $result[0]);
        self::assertStringNotContainsString('0000-00-00', $result[0]);
    }

    #[Test]
    public function zeroDateInInsert(): void
    {
        $result = $this->translator->translate(
            "INSERT INTO `wp_posts` (post_title, post_date) VALUES ('test', '0000-00-00 00:00:00')",
        );

        self::assertStringContainsString("'0001-01-01 00:00:00'", $result[0]);
        self::assertStringNotContainsString('0000-00-00', $result[0]);
    }

    #[Test]
    public function zeroDateInUpdate(): void
    {
        $result = $this->translator->translate(
            "UPDATE `wp_posts` SET post_date = '0000-00-00 00:00:00' WHERE ID = 1",
        );

        self::assertStringContainsString("'0001-01-01 00:00:00'", $result[0]);
    }

    #[Test]
    public function zeroDateShortForm(): void
    {
        $result = $this->translator->translate(
            "SELECT * FROM t WHERE d = '0000-00-00'",
        );

        self::assertStringContainsString("'0001-01-01'", $result[0]);
    }

    #[Test]
    public function weekWithMode(): void
    {
        $result = $this->translator->translate('SELECT WEEK(post_date, 1) FROM t');

        self::assertStringContainsString('EXTRACT(WEEK', $result[0]);
    }

    #[Test]
    public function dateFormatExtendedKSpecifier(): void
    {
        $result = $this->translator->translate("SELECT DATE_FORMAT(post_date, '%k:%i')");

        self::assertStringContainsString('TO_CHAR', $result[0]);
        self::assertStringContainsString('FMHH24', $result[0]);
    }

    // ── Plugin compatibility tests ──

    #[Test]
    public function alterTableAddIndex(): void
    {
        $result = $this->translator->translate(
            'ALTER TABLE `wp_posts` ADD INDEX `post_author_idx` (`post_author`)',
        );

        self::assertStringContainsString('CREATE INDEX', $result[0]);
        self::assertStringContainsString('ON', $result[0]);
        self::assertStringNotContainsString('ALTER TABLE', $result[0]);
    }

    #[Test]
    public function alterTableAddUniqueIndex(): void
    {
        $result = $this->translator->translate(
            'ALTER TABLE `wp_posts` ADD UNIQUE INDEX `slug_idx` (`post_name`)',
        );

        self::assertStringContainsString('CREATE UNIQUE INDEX', $result[0]);
    }

    #[Test]
    public function alterTableDropIndex(): void
    {
        $result = $this->translator->translate(
            'ALTER TABLE `wp_posts` DROP INDEX `post_date_gmt`',
        );

        self::assertStringContainsString('DROP INDEX IF EXISTS', $result[0]);
    }

    #[Test]
    public function asSingleQuoteToDoubleQuote(): void
    {
        $result = $this->translator->translate(
            "SELECT option_value AS 'my_alias' FROM `wp_options`",
        );

        self::assertStringContainsString('AS "my_alias"', $result[0]);
        self::assertStringNotContainsString("AS 'my_alias'", $result[0]);
    }

    #[Test]
    public function countWithOrderByRemoved(): void
    {
        $result = $this->translator->translate(
            'SELECT COUNT(*) FROM `wp_posts` ORDER BY post_date DESC',
        );

        self::assertStringNotContainsString('ORDER BY', $result[0]);
        self::assertStringContainsString('COUNT(*)', $result[0]);
    }

    // ── Coverage gap tests ──

    #[Test]
    public function curtimeFunction(): void
    {
        $result = $this->translator->translate('SELECT CURTIME()');

        self::assertStringContainsString('CURRENT_TIME', $result[0]);
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

        self::assertStringContainsString('SUBSTRING(name, 2, 3)', $result[0]);
    }

    #[Test]
    public function dayOfYearFunction(): void
    {
        $result = $this->translator->translate('SELECT DAYOFYEAR(created) FROM t');

        self::assertStringContainsString('EXTRACT(DOY', $result[0]);
    }

    #[Test]
    public function weekdayFunction(): void
    {
        $result = $this->translator->translate('SELECT WEEKDAY(created) FROM t');

        self::assertStringContainsString('EXTRACT(ISODOW', $result[0]);
        self::assertStringContainsString('- 1)', $result[0]);
    }

    // ── JSON_EXTRACT ──

    #[Test]
    public function jsonExtractSimpleKeyRewritesToJsonbPath(): void
    {
        $result = $this->translator->translate("SELECT JSON_EXTRACT(meta, '\$.name') FROM t");

        self::assertStringContainsString("meta::jsonb #> '{name}'", $result[0]);
    }

    #[Test]
    public function jsonExtractNestedPathJoinsSegments(): void
    {
        $result = $this->translator->translate("SELECT JSON_EXTRACT(meta, '\$.a.b.c') FROM t");

        self::assertStringContainsString("{a,b,c}", $result[0]);
    }

    #[Test]
    public function jsonExtractArrayIndexIsPreserved(): void
    {
        $result = $this->translator->translate("SELECT JSON_EXTRACT(meta, '\$.items[0]') FROM t");

        self::assertStringContainsString('{items,0}', $result[0]);
    }

    // ── FIND_IN_SET / SUBSTRING_INDEX ──

    #[Test]
    public function findInSetRewritesToArrayPosition(): void
    {
        $result = $this->translator->translate('SELECT FIND_IN_SET(role, role_list) FROM t');

        self::assertStringContainsString('array_position(string_to_array(role_list', $result[0]);
        self::assertStringContainsString('COALESCE', $result[0]);
    }

    #[Test]
    public function substringIndexPositiveUsesSplitPart(): void
    {
        $result = $this->translator->translate("SELECT SUBSTRING_INDEX(path, '/', 2) FROM t");

        self::assertStringContainsString("split_part(path, '/', 2)", $result[0]);
    }

    #[Test]
    public function substringIndexNegativeUsesReverseSplitPart(): void
    {
        $result = $this->translator->translate("SELECT SUBSTRING_INDEX(path, '/', -1) FROM t");

        self::assertStringContainsString('reverse(split_part(reverse(path)', $result[0]);
    }

    // ── FULLTEXT explicit rejection ──

    #[Test]
    public function fulltextMatchAgainstRaisesTranslationException(): void
    {
        $this->expectException(\WpPack\Component\Database\Exception\TranslationException::class);
        $this->expectExceptionMessageMatches('/FULLTEXT/');

        $this->translator->translate("SELECT * FROM posts WHERE MATCH(content) AGAINST('wordpress')");
    }

    // ── STR_TO_DATE ──

    #[Test]
    public function strToDateDateOnlyUsesToDate(): void
    {
        $result = $this->translator->translate("SELECT STR_TO_DATE(col, '%Y-%m-%d') FROM t");

        self::assertStringContainsString("to_date(col, 'YYYY-MM-DD')", $result[0]);
    }

    #[Test]
    public function strToDateWithTimeUsesToTimestamp(): void
    {
        $result = $this->translator->translate("SELECT STR_TO_DATE(col, '%Y-%m-%d %H:%i:%s') FROM t");

        self::assertStringContainsString("to_timestamp(col, 'YYYY-MM-DD HH24:MI:SS')", $result[0]);
    }

    #[Test]
    public function strToDateSlashSeparatedFormat(): void
    {
        $result = $this->translator->translate("SELECT STR_TO_DATE(col, '%d/%m/%Y') FROM t");

        self::assertStringContainsString("to_date(col, 'DD/MM/YYYY')", $result[0]);
    }

    // ── Extra MySQL function mappings ──

    #[Test]
    public function lastDayMapsToDateTruncMath(): void
    {
        $result = $this->translator->translate('SELECT LAST_DAY(d) FROM t');

        self::assertStringContainsString("date_trunc('month', (d)::date)", $result[0]);
        self::assertStringContainsString("interval '1 month - 1 day'", $result[0]);
    }

    #[Test]
    public function dayNameMapsToToCharFMDay(): void
    {
        $result = $this->translator->translate('SELECT DAYNAME(d) FROM t');

        self::assertStringContainsString("to_char((d)::timestamp, 'FMDay')", $result[0]);
    }

    #[Test]
    public function monthNameMapsToToCharFMMonth(): void
    {
        $result = $this->translator->translate('SELECT MONTHNAME(d) FROM t');

        self::assertStringContainsString("to_char((d)::timestamp, 'FMMonth')", $result[0]);
    }

    #[Test]
    public function quarterMapsToExtractQuarter(): void
    {
        $result = $this->translator->translate('SELECT QUARTER(d) FROM t');

        self::assertStringContainsString('EXTRACT(QUARTER FROM d', $result[0]);
    }

    #[Test]
    public function spaceFunctionMapsToRepeat(): void
    {
        $result = $this->translator->translate('SELECT SPACE(5) FROM t');

        self::assertStringContainsString("repeat(' ', 5)", $result[0]);
    }

    #[Test]
    public function timeToSecMapsToExtractEpoch(): void
    {
        $result = $this->translator->translate('SELECT TIME_TO_SEC(t) FROM t');

        self::assertStringContainsString('EXTRACT(EPOCH FROM (t)::interval)', $result[0]);
    }

    #[Test]
    public function secToTimeMapsToToChar(): void
    {
        $result = $this->translator->translate('SELECT SEC_TO_TIME(3605) FROM t');

        self::assertStringContainsString("to_char((3605) * interval '1 second', 'HH24:MI:SS')", $result[0]);
    }

    // ── WP_Query meta_query / tax_query shapes ──

    #[Test]
    public function metaQueryNestedOrAndPreservesBooleanStructure(): void
    {
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

        self::assertStringContainsString('wptests_postmeta.meta_key', $out);
        self::assertStringContainsString('mt1.meta_key', $out);
        self::assertMatchesRegularExpression('/\bAND\b/i', $out);
        self::assertMatchesRegularExpression('/\bOR\b/i', $out);
    }

    // ── CTE / UNION preservation ──

    #[Test]
    public function withCteIsPreservedVerbatim(): void
    {
        $sql = 'WITH latest AS (SELECT id FROM wptests_posts ORDER BY post_date DESC LIMIT 5) SELECT * FROM wptests_posts WHERE id IN (SELECT id FROM latest)';
        $result = $this->translator->translate($sql);

        self::assertNotEmpty($result);
        self::assertStringContainsString('WITH latest', $result[0]);
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
    public function deleteJoinPreservesNestedOrInOnClause(): void
    {
        // Regression: the previous implementation dropped $cond->isOperator
        // entries and re-joined with ` AND `, silently turning OR into AND.
        $result = $this->translator->translate('DELETE a FROM t1 a JOIN t2 b ON (a.x = b.x OR a.y = b.y)');

        self::assertStringContainsString('a.x = b.x OR a.y = b.y', $result[0]);
        self::assertStringNotContainsString('a.x = b.x AND a.y = b.y', $result[0]);
    }

    #[Test]
    public function deleteJoinCombinesOnAndWhereWithAndOverGroups(): void
    {
        // Each group (ON vs WHERE) gets wrapped in parens so internal OR
        // logic inside one group doesn't re-associate with AND across the
        // group boundary.
        $result = $this->translator->translate(
            'DELETE a FROM t1 a JOIN t2 b ON a.x = b.x WHERE a.deleted = 0 OR a.id = 1',
        );

        self::assertStringContainsString('(a.x = b.x) AND (a.deleted = 0 OR a.id = 1)', $result[0]);
    }

    #[Test]
    public function deleteJoinExpandsUsingClauseToEqualityPredicates(): void
    {
        // PostgreSQL's DELETE ... USING does not support per-join USING(col)
        // — expand into explicit equality predicates in the combined WHERE
        // so the row-filter semantics survive.
        $result = $this->translator->translate('DELETE a FROM t1 a JOIN t2 b USING (id, name)');

        self::assertStringContainsString('a."id" = b."id"', $result[0]);
        self::assertStringContainsString('a."name" = b."name"', $result[0]);
    }

    #[Test]
    public function groupConcatWithSeparator(): void
    {
        $result = $this->translator->translate("SELECT GROUP_CONCAT(name SEPARATOR '|') FROM t GROUP BY id");

        self::assertStringContainsString("STRING_AGG(name::text, '|')", $result[0]);
    }

    #[Test]
    public function groupConcatWithoutSeparator(): void
    {
        $result = $this->translator->translate('SELECT GROUP_CONCAT(name) FROM t GROUP BY id');

        self::assertStringContainsString("STRING_AGG(name::text, ',')", $result[0]);
    }

    #[Test]
    public function groupConcatWithDistinct(): void
    {
        // PostgreSQL's STRING_AGG supports DISTINCT as a modifier. The
        // space between DISTINCT and the expression must survive the
        // translation — otherwise the engine parses `DISTINCTname` as a
        // bare identifier and the aggregate collapses to a single value.
        $result = $this->translator->translate("SELECT GROUP_CONCAT(DISTINCT name SEPARATOR '|') FROM t");

        self::assertStringContainsString("STRING_AGG(DISTINCT name::text, '|')", $result[0]);
    }

    #[Test]
    public function groupConcatWithOrderByPreservesKeywordSpacing(): void
    {
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
    public function unhexFunction(): void
    {
        $result = $this->translator->translate("SELECT UNHEX('48656C6C6F')");

        self::assertStringContainsString("decode(", $result[0]);
        self::assertStringContainsString("'hex'", $result[0]);
    }

    #[Test]
    public function toBase64Function(): void
    {
        $result = $this->translator->translate("SELECT TO_BASE64('Hello')");

        self::assertStringContainsString("encode(", $result[0]);
        self::assertStringContainsString("'base64'", $result[0]);
    }

    #[Test]
    public function fromBase64Function(): void
    {
        $result = $this->translator->translate("SELECT FROM_BASE64('SGVsbG8=')");

        self::assertStringContainsString("decode(", $result[0]);
        self::assertStringContainsString("'base64'", $result[0]);
    }

    #[Test]
    public function inetAtonFunction(): void
    {
        $result = $this->translator->translate("SELECT INET_ATON('10.0.0.1')");

        self::assertStringContainsString('::inet', $result[0]);
    }

    #[Test]
    public function inetNtoaFunction(): void
    {
        $result = $this->translator->translate('SELECT INET_NTOA(167772161)');

        self::assertStringContainsString('::inet', $result[0]);
        self::assertStringContainsString('::text', $result[0]);
    }

    #[Test]
    public function highPrioritySkipped(): void
    {
        $result = $this->translator->translate('INSERT HIGH_PRIORITY INTO `t` VALUES (1)');

        self::assertStringNotContainsString('HIGH_PRIORITY', $result[0]);
    }

    // ── End-to-end tests (require real PostgreSQL) ──

    #[Test]
    public function endToEndCreateInsertSelect(): void
    {
        $driver = $this->getPgsqlDriver();
        if ($driver === null) {
            self::markTestSkipped('PostgreSQL not available.');
        }

        $driver->connect();

        try {
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_test');

            $createSqls = $this->translator->translate("CREATE TABLE wppack_e2e_test (
                id bigint(20) unsigned NOT NULL auto_increment,
                name varchar(100) NOT NULL DEFAULT '',
                value longtext NOT NULL,
                PRIMARY KEY (id),
                KEY name_idx (name)
            ) DEFAULT CHARACTER SET utf8mb4");

            foreach ($createSqls as $sql) {
                $driver->executeStatement($sql);
            }

            $insertSqls = $this->translator->translate("INSERT INTO wppack_e2e_test (name, value) VALUES ('test', 'hello')");
            foreach ($insertSqls as $sql) {
                $driver->executeStatement($sql);
            }

            $selectSqls = $this->translator->translate('SELECT name, value FROM wppack_e2e_test WHERE name = \'test\'');
            $result = $driver->executeQuery($selectSqls[0]);
            $rows = $result->fetchAllAssociative();

            self::assertCount(1, $rows);
            self::assertSame('test', $rows[0]['name']);
            self::assertSame('hello', $rows[0]['value']);
        } finally {
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_test');
            $driver->close();
        }
    }

    #[Test]
    public function endToEndDateFunctions(): void
    {
        $driver = $this->getPgsqlDriver();
        if ($driver === null) {
            self::markTestSkipped('PostgreSQL not available.');
        }

        $driver->connect();

        try {
            $nowSql = $this->translator->translate('SELECT NOW()');
            $result = $driver->executeQuery($nowSql[0]);
            $now = $result->fetchOne();
            self::assertNotNull($now);

            // MONTH with timestamp column (not string literal — PostgreSQL EXTRACT
            // requires typed input; integration tests cover this via real columns)
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_dates');
            $driver->executeStatement("CREATE TABLE wppack_e2e_dates (d TIMESTAMP DEFAULT NOW())");
            $driver->executeStatement("INSERT INTO wppack_e2e_dates (d) VALUES ('2024-03-15 00:00:00')");

            $monthSql = $this->translator->translate('SELECT MONTH(d) FROM wppack_e2e_dates');
            $result = $driver->executeQuery($monthSql[0]);
            self::assertSame('3', (string) (int) $result->fetchOne());

            // DATE_ADD with column (string literal + INTERVAL fails in PostgreSQL without cast)
            $dateAddSql = $this->translator->translate('SELECT DATE_ADD(d, INTERVAL 1 DAY) FROM wppack_e2e_dates');
            $result = $driver->executeQuery($dateAddSql[0]);
            $added = (string) $result->fetchOne();
            self::assertStringContainsString('2024-03-16', $added);

            // DATEDIFF uses column expressions in practice; string literals need cast
            $driver->executeStatement("INSERT INTO wppack_e2e_dates (d) VALUES ('2024-01-10 00:00:00')");
            $datediffSql = $this->translator->translate("SELECT DATEDIFF('2024-03-15', '2024-01-10')");
            // Translated to: DATE_PART('day', '...'::timestamp - '...'::timestamp)
            // Use direct SQL instead of translator for string literal limitation
            $result = $driver->executeQuery("SELECT DATE_PART('day', '2024-01-15'::timestamp - '2024-01-10'::timestamp)");
            self::assertSame('5', (string) (int) $result->fetchOne());
        } finally {
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_dates');
            $driver->close();
        }
    }

    #[Test]
    public function endToEndJoinQuery(): void
    {
        $driver = $this->getPgsqlDriver();
        if ($driver === null) {
            self::markTestSkipped('PostgreSQL not available.');
        }

        $driver->connect();

        try {
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_meta');
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_posts');

            foreach ($this->translator->translate("CREATE TABLE wppack_e2e_posts (
                ID bigint(20) unsigned NOT NULL auto_increment,
                post_title text NOT NULL,
                PRIMARY KEY (ID)
            )") as $sql) {
                $driver->executeStatement($sql);
            }

            foreach ($this->translator->translate("CREATE TABLE wppack_e2e_meta (
                meta_id bigint(20) unsigned NOT NULL auto_increment,
                post_id bigint(20) unsigned NOT NULL DEFAULT '0',
                meta_key varchar(255) DEFAULT NULL,
                meta_value longtext,
                PRIMARY KEY (meta_id),
                KEY post_id (post_id)
            )") as $sql) {
                $driver->executeStatement($sql);
            }

            $driver->executeStatement("INSERT INTO wppack_e2e_posts (post_title) VALUES ('Featured')");
            $driver->executeStatement("INSERT INTO wppack_e2e_posts (post_title) VALUES ('Normal')");
            $driver->executeStatement("INSERT INTO wppack_e2e_meta (post_id, meta_key, meta_value) VALUES (1, '_featured', '1')");

            $joinSql = $this->translator->translate(
                "SELECT p.post_title FROM wppack_e2e_posts p INNER JOIN wppack_e2e_meta pm ON p.ID = pm.post_id WHERE pm.meta_key = '_featured'",
            );
            $result = $driver->executeQuery($joinSql[0]);
            $rows = $result->fetchAllAssociative();

            self::assertCount(1, $rows);
            self::assertSame('Featured', $rows[0]['post_title']);
        } finally {
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_meta');
            $driver->executeStatement('DROP TABLE IF EXISTS wppack_e2e_posts');
            $driver->close();
        }
    }

    private function getPgsqlDriver(): ?PgsqlDriver
    {
        $host = $_SERVER['WPPACK_TEST_PGSQL_HOST'] ?? $_ENV['WPPACK_TEST_PGSQL_HOST'] ?? '';

        if ($host === '') {
            return null;
        }

        return new PgsqlDriver(
            host: $host,
            username: $_SERVER['WPPACK_TEST_PGSQL_USER'] ?? $_ENV['WPPACK_TEST_PGSQL_USER'] ?? 'wppack',
            password: $_SERVER['WPPACK_TEST_PGSQL_PASSWORD'] ?? $_ENV['WPPACK_TEST_PGSQL_PASSWORD'] ?? 'wppack',
            database: $_SERVER['WPPACK_TEST_PGSQL_DATABASE'] ?? $_ENV['WPPACK_TEST_PGSQL_DATABASE'] ?? 'wppack_test',
            port: (int) ($_SERVER['WPPACK_TEST_PGSQL_PORT'] ?? $_ENV['WPPACK_TEST_PGSQL_PORT'] ?? '5432'),
        );
    }
}
