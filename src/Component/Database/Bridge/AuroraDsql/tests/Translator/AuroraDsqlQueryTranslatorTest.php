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

namespace WPPack\Component\Database\Bridge\AuroraDsql\Tests\Translator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator;

final class AuroraDsqlQueryTranslatorTest extends TestCase
{
    #[Test]
    public function truncateIsTranslatedToDeleteAndSequenceReset(): void
    {
        $translator = new AuroraDsqlQueryTranslator();

        $result = $translator->translate('TRUNCATE TABLE wp_posts');

        self::assertCount(2, $result);
        self::assertSame('DELETE FROM "wp_posts"', $result[0]);
        self::assertStringContainsString("pg_get_serial_sequence('wp_posts', column_name)", $result[1]);
        self::assertStringContainsString("table_name = 'wp_posts'", $result[1]);
    }

    #[Test]
    public function truncateWithoutTableKeywordIsAccepted(): void
    {
        $translator = new AuroraDsqlQueryTranslator();

        $result = $translator->translate('TRUNCATE wp_users');

        self::assertCount(2, $result);
        self::assertSame('DELETE FROM "wp_users"', $result[0]);
    }

    #[Test]
    public function truncateWithBacktickAndDoubleQuoteQuotingIsAccepted(): void
    {
        $translator = new AuroraDsqlQueryTranslator();

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
    public function maliciousTableNamesDoNotReachDsqlTemplate(): void
    {
        $translator = new AuroraDsqlQueryTranslator();

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
            $looksLikeDsqlTemplate = \count($out) === 2
                && str_starts_with($out[0], 'DELETE FROM "')
                && str_contains($out[1], 'pg_get_serial_sequence(');

            self::assertFalse(
                $looksLikeDsqlTemplate,
                "Hostile input reached the DSQL TRUNCATE template: {$sql}",
            );
        }
    }

    #[Test]
    public function nonTruncateQueriesDelegateToPostgresqlTranslator(): void
    {
        $translator = new AuroraDsqlQueryTranslator();

        $result = $translator->translate('SELECT 1');

        self::assertNotEmpty($result);
        self::assertStringContainsString('SELECT', $result[0]);
    }

    #[Test]
    public function trailingSemicolonAndWhitespaceAreTolerated(): void
    {
        $translator = new AuroraDsqlQueryTranslator();

        $result = $translator->translate("  TRUNCATE TABLE wp_posts ;  \n");

        self::assertCount(2, $result);
        self::assertSame('DELETE FROM "wp_posts"', $result[0]);
    }
}
