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

        self::assertCount(1, $result);
        self::assertStringContainsString('BIGSERIAL', $result[0]);
        self::assertStringContainsString('TEXT', $result[0]);
        self::assertStringNotContainsString('ENGINE=', $result[0]);
        self::assertStringNotContainsString('CHARSET', $result[0]);
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
}
