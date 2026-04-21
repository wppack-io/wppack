<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Database\Bridge\AuroraDSQL\Tests\Translator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\AuroraDSQL\Translator\AuroraDSQLQueryTranslator;
use WPPack\Component\Database\Exception\UnsupportedFeatureException;

final class AuroraDSQLQueryTranslatorTest extends TestCase
{
    // ── TRUNCATE ──

    #[Test]
    public function truncateIsTranslatedToDeleteAndSequenceReset(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate('TRUNCATE TABLE wp_posts');

        self::assertCount(2, $result);
        self::assertSame('DELETE FROM "wp_posts"', $result[0]);
        self::assertStringContainsString("pg_get_serial_sequence('wp_posts', column_name)", $result[1]);
        self::assertStringContainsString("table_name = 'wp_posts'", $result[1]);
    }

    #[Test]
    public function truncateWithoutTableKeywordIsAccepted(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate('TRUNCATE wp_users');

        self::assertCount(2, $result);
        self::assertSame('DELETE FROM "wp_users"', $result[0]);
    }

    #[Test]
    public function truncateWithBacktickAndDoubleQuoteQuotingIsAccepted(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        foreach (['TRUNCATE `wp_posts`', 'TRUNCATE "wp_posts"', 'TRUNCATE TABLE `wp_posts`'] as $sql) {
            $result = $translator->translate($sql);

            self::assertCount(2, $result, "Input: {$sql}");
            self::assertSame('DELETE FROM "wp_posts"', $result[0], "Input: {$sql}");
        }
    }

    /**
     * Defense-in-depth: the TRUNCATE fast-path accepts identifiers matching
     * a strict `[A-Za-z_][A-Za-z0-9_]{0,62}` shape. Anything outside that
     * shape must NOT be spliced into the DSQL-specific `DELETE FROM "..."`
     * + `pg_get_serial_sequence('...')` template. It must fall through to
     * the PostgreSQL translator instead; that path may surface the original
     * (malformed) SQL, but won't mint a fresh dangerous statement from our
     * own template.
     */
    #[Test]
    public function maliciousTableNamesDoNotReachDSQLTemplate(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $hostile = [
            "TRUNCATE TABLE users'; DROP TABLE admin;--",
            'TRUNCATE TABLE "users\"; DROP TABLE admin"',
            'TRUNCATE TABLE users,admin',
            'TRUNCATE TABLE users admin',
            'TRUNCATE TABLE (SELECT 1)',
            'TRUNCATE TABLE --users',
            'TRUNCATE TABLE "; rm -rf /; --"',
            // name exceeding NAMEDATALEN (64 chars)
            'TRUNCATE TABLE ' . str_repeat('a', 120),
        ];

        foreach ($hostile as $sql) {
            try {
                $out = $translator->translate($sql);
            } catch (\Throwable) {
                // Fallback surfaced an error — that is the safe outcome.
                continue;
            }

            // The DSQL template emits exactly two statements: a DELETE FROM
            // and a setval() call. If the translator fell through to pgsql
            // the result shape differs. Assert we did NOT produce the DSQL
            // template for hostile inputs.
            $looksLikeDSQLTemplate = \count($out) === 2
                && str_starts_with($out[0], 'DELETE FROM "')
                && str_contains($out[1], 'pg_get_serial_sequence(');

            self::assertFalse(
                $looksLikeDSQLTemplate,
                "Hostile input reached the DSQL TRUNCATE template: {$sql}",
            );
        }
    }

    #[Test]
    public function nonTruncateQueriesDelegateToPostgreSQLTranslator(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate('SELECT 1');

        self::assertNotEmpty($result);
        self::assertStringContainsString('SELECT', $result[0]);
    }

    #[Test]
    public function trailingSemicolonAndWhitespaceAreTolerated(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate("  TRUNCATE TABLE wp_posts ;  \n");

        self::assertCount(2, $result);
        self::assertSame('DELETE FROM "wp_posts"', $result[0]);
    }

    // ── CREATE INDEX ASYNC ──

    #[Test]
    public function createIndexIsRewrittenToAsync(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate('CREATE INDEX idx_slug ON wp_posts (post_name)');

        self::assertCount(1, $result);
        self::assertStringStartsWith('CREATE INDEX ASYNC ', $result[0]);
        self::assertStringContainsString('idx_slug', $result[0]);
    }

    #[Test]
    public function createUniqueIndexIsRewrittenToAsync(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate('CREATE UNIQUE INDEX idx_u ON wp_users (user_email)');

        self::assertCount(1, $result);
        self::assertStringStartsWith('CREATE UNIQUE INDEX ASYNC ', $result[0]);
    }

    #[Test]
    public function createIndexIfNotExistsIsRewrittenToAsync(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate('CREATE INDEX IF NOT EXISTS idx_slug ON wp_posts (post_name)');

        self::assertCount(1, $result);
        self::assertStringContainsString('CREATE INDEX ASYNC IF NOT EXISTS ', $result[0]);
    }

    #[Test]
    public function alreadyAsyncIndexIsNotDoubleRewritten(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        // Passthrough behaviour: if the caller already wrote ASYNC we don't
        // add a second one.
        $result = $translator->translate('CREATE INDEX ASYNC idx_slug ON wp_posts (post_name)');

        self::assertCount(1, $result);
        self::assertStringNotContainsString('ASYNC ASYNC', $result[0]);
    }

    #[Test]
    public function createTableRegularKeyEmitsAsyncCreateIndex(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        // Regular KEY inside CREATE TABLE → PG translator hoists it to a
        // separate CREATE INDEX statement; DSQL translator must inject ASYNC.
        $result = $translator->translate(
            'CREATE TABLE wp_posts (id BIGINT, post_name VARCHAR(200), KEY idx_slug (post_name))',
        );

        self::assertGreaterThanOrEqual(2, \count($result));
        $indexStatements = array_values(array_filter(
            $result,
            static fn(string $s): bool => stripos($s, 'CREATE INDEX') === 0
                || stripos($s, 'CREATE UNIQUE INDEX') === 0,
        ));
        self::assertNotSame([], $indexStatements, 'Expected at least one CREATE INDEX statement.');
        foreach ($indexStatements as $stmt) {
            self::assertMatchesRegularExpression(
                '/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+ASYNC\b/i',
                $stmt,
                "Expected ASYNC keyword: {$stmt}",
            );
        }
    }

    // ── SERIAL → GENERATED BY DEFAULT AS IDENTITY ──

    #[Test]
    public function bigserialColumnIsRewrittenToIdentity(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate(
            'CREATE TABLE wp_posts (id BIGINT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200))',
        );

        $createTable = $result[0];
        self::assertStringNotContainsString('BIGSERIAL', $createTable);
        self::assertStringContainsString('BIGINT GENERATED BY DEFAULT AS IDENTITY', $createTable);
    }

    #[Test]
    public function serialColumnIsRewrittenToIdentity(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate(
            'CREATE TABLE wp_counters (id INT AUTO_INCREMENT PRIMARY KEY)',
        );

        $createTable = $result[0];
        self::assertStringNotContainsString(' SERIAL', $createTable);
        self::assertStringContainsString('INTEGER GENERATED BY DEFAULT AS IDENTITY', $createTable);
    }

    #[Test]
    public function smallserialColumnIsRewrittenToIdentity(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate(
            'CREATE TABLE wp_tiny (id SMALLINT AUTO_INCREMENT PRIMARY KEY)',
        );

        $createTable = $result[0];
        self::assertStringNotContainsString('SMALLSERIAL', $createTable);
        self::assertStringContainsString('SMALLINT GENERATED BY DEFAULT AS IDENTITY', $createTable);
    }

    // ── Reject unsupported CREATE ──

    #[Test]
    public function temporaryTableIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessageMatches('/temporary table/i');

        $translator->translate('CREATE TEMPORARY TABLE tmp (id INT)');
    }

    #[Test]
    public function tempTableAbbreviationIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('CREATE TEMP TABLE tmp (id INT)');
    }

    #[Test]
    public function createTriggerIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessageMatches('/trigger/i');

        $translator->translate('CREATE TRIGGER trg_update BEFORE UPDATE ON t FOR EACH ROW EXECUTE FUNCTION fn()');
    }

    #[Test]
    public function createFunctionIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessageMatches('/function/i');

        $translator->translate("CREATE OR REPLACE FUNCTION f() RETURNS INT AS $$ BEGIN RETURN 1; END; $$ LANGUAGE plpgsql");
    }

    #[Test]
    public function createProcedureIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('CREATE PROCEDURE p() BEGIN END');
    }

    #[Test]
    public function foreignKeyInCreateTableIsAccepted(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        // MySQL schemas routinely declare FOREIGN KEY constraints; MyISAM
        // silently ignores them, InnoDB enforces them. DSQL has no FK
        // enforcement, so we silently drop the constraint — translation
        // must not throw on an ordinary WordPress-shaped schema.
        $result = $translator->translate(
            'CREATE TABLE wp_comments (id BIGINT, post_id BIGINT, FOREIGN KEY (post_id) REFERENCES wp_posts(id))',
        );

        self::assertNotEmpty($result);
        foreach ($result as $stmt) {
            self::assertStringNotContainsStringIgnoringCase('REFERENCES wp_posts', $stmt);
            self::assertStringNotContainsStringIgnoringCase('FOREIGN KEY', $stmt);
        }
    }

    #[Test]
    public function columnLevelReferencesInCreateTableIsAccepted(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $result = $translator->translate(
            'CREATE TABLE wp_comments (id BIGINT, post_id BIGINT REFERENCES wp_posts(id))',
        );

        self::assertNotEmpty($result);
        foreach ($result as $stmt) {
            self::assertStringNotContainsStringIgnoringCase('REFERENCES', $stmt);
        }
    }

    #[Test]
    public function onUpdateCurrentTimestampTriggerArtifactIsDropped(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        // The PostgreSQL translator emits CREATE FUNCTION + CREATE TRIGGER
        // for MySQL "ON UPDATE CURRENT_TIMESTAMP". DSQL can't run either,
        // but the CREATE TABLE itself is still valid (with the DEFAULT
        // CURRENT_TIMESTAMP handled by PG). We silently drop the trigger
        // artifact so existing WordPress DDL keeps flowing; updated_at
        // maintenance on UPDATE becomes the application's responsibility.
        $result = $translator->translate(
            'CREATE TABLE wp_posts (id BIGINT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)',
        );

        self::assertNotEmpty($result);
        foreach ($result as $stmt) {
            self::assertDoesNotMatchRegularExpression(
                '/^\s*CREATE\s+(?:OR\s+REPLACE\s+)?(?:FUNCTION|TRIGGER)\b/i',
                $stmt,
                "Trigger / function artifact should be filtered: {$stmt}",
            );
        }
        self::assertStringStartsWith('CREATE TABLE', $result[0]);
    }

    // ── Reject unsupported ALTER TABLE ──

    #[Test]
    public function alterTableDropColumnIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);
        // Message guides the caller toward the rename + CREATE TABLE +
        // full-copy pattern (no in-place column mutation on DSQL).
        $this->expectExceptionMessageMatches('/renam.*new table.*copy/i');

        $translator->translate('ALTER TABLE wp_posts DROP COLUMN stale_col');
    }

    #[Test]
    public function alterTableAlterColumnTypeIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts ALTER COLUMN title TYPE TEXT');
    }

    #[Test]
    public function alterTableAlterColumnSetDataTypeIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts ALTER COLUMN title SET DATA TYPE TEXT');
    }

    #[Test]
    public function alterTableAlterColumnSetDefaultIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts ALTER COLUMN post_status SET DEFAULT \'draft\'');
    }

    #[Test]
    public function alterTableAlterColumnDropDefaultIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts ALTER COLUMN post_status DROP DEFAULT');
    }

    #[Test]
    public function alterTableAlterColumnSetNotNullIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts ALTER COLUMN post_status SET NOT NULL');
    }

    #[Test]
    public function alterTableAlterColumnDropNotNullIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts ALTER COLUMN post_status DROP NOT NULL');
    }

    #[Test]
    public function alterTableModifyColumnIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts MODIFY COLUMN title TEXT');
    }

    #[Test]
    public function alterTableChangeColumnIsRejected(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        $this->expectException(UnsupportedFeatureException::class);

        $translator->translate('ALTER TABLE wp_posts CHANGE COLUMN title post_title TEXT');
    }

    #[Test]
    public function alterTableAddColumnStillWorks(): void
    {
        $translator = new AuroraDSQLQueryTranslator();

        // ADD COLUMN is supported on DSQL — must not raise and must still
        // be rewritten for PostgreSQL semantics.
        $result = $translator->translate('ALTER TABLE wp_posts ADD COLUMN extra TEXT');

        self::assertNotEmpty($result);
        self::assertStringContainsString('ALTER TABLE', $result[0]);
    }
}
