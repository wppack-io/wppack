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
use WpPack\Component\Dsn\Dsn;

/**
 * Verifies that the search_path constructor option actually emits
 * `SET search_path TO ...` on connect. Integration-style — requires a
 * live PostgreSQL test server via DATABASE_DSN=pgsql:...
 *
 * Doctrine DBAL deliberately doesn't expose this knob (connections are
 * expected to inherit the role / database default). WpPack does, so WP
 * multisite / multi-tenant installations can isolate schemas per blog
 * without ALTER ROLE gymnastics. This test pins that behaviour.
 */
final class PgsqlSearchPathTest extends TestCase
{
    private string $dsnString = '';

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';
        if (!is_string($dsn) || !str_starts_with($dsn, 'pgsql:')) {
            self::markTestSkipped('Requires pgsql DSN');
        }

        $this->dsnString = $dsn;
    }

    #[Test]
    public function searchPathIsAppliedAfterConnect(): void
    {
        $parsed = Dsn::fromString($this->dsnString);

        // Create a temp schema we can pin to, separate from public. Use
        // PGSQL_CONNECT_FORCE_NEW-style isolation by running admin setup
        // and tear-down from a single driver kept alive across the try.
        $admin = $this->makeDriver($parsed, null);
        $admin->connect();
        $schemaName = 'wppack_searchpath_test_' . substr(md5((string) mt_rand()), 0, 8);
        $admin->executeStatement(\sprintf('CREATE SCHEMA IF NOT EXISTS %s', $this->quoteId($schemaName)));

        try {
            // The same $admin driver is reused for introspection after
            // SETting search_path — pg_connect() with identical args
            // returns the same underlying connection, which makes this
            // behave the way any single-client app would at runtime.
            $driver = $this->makeDriver($parsed, [$schemaName, 'public']);
            $driver->connect();

            $firstSchema = (string) $driver->executeQuery('SELECT current_schema() AS s')->fetchOne();
            self::assertSame($schemaName, $firstSchema);

            $paths = (string) $driver->executeQuery('SELECT current_schemas(false)::text AS paths')->fetchOne();
            self::assertStringContainsString($schemaName, $paths);
            self::assertStringContainsString('public', $paths);
        } finally {
            $admin->executeStatement(\sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $this->quoteId($schemaName)));
            $admin->close();
        }
    }

    #[Test]
    public function nullSearchPathLeavesServerDefault(): void
    {
        $parsed = Dsn::fromString($this->dsnString);
        $driver = $this->makeDriver($parsed, null);
        $driver->connect();

        try {
            // Without an explicit search_path the server default wins —
            // for the default `wppack` role on a fresh pg container that
            // resolves to `public` (the first effective entry).
            $firstSchema = (string) $driver->executeQuery('SELECT current_schema() AS s')->fetchOne();
            self::assertNotSame('', $firstSchema);
        } finally {
            $driver->close();
        }
    }

    /**
     * @param list<string>|null $searchPath
     */
    private function makeDriver(Dsn $parsed, ?array $searchPath): PgsqlDriver
    {
        return new PgsqlDriver(
            host: $parsed->getHost() ?? '127.0.0.1',
            username: $parsed->getUser() ?? 'wppack',
            password: $parsed->getPassword() ?? 'wppack',
            database: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            port: $parsed->getPort() ?? 5432,
            searchPath: $searchPath,
        );
    }

    private function quoteId(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
