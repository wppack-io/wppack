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
}
