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

namespace WPPack\Component\Database\Bridge\PostgreSQL\Tests;

use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLDriver;
use WPPack\Component\Database\Tests\WpdbIntegrationTestTrait;
use WPPack\Component\Database\WPPackWpdb;
use WPPack\Component\Dsn\Dsn;

/**
 * WPPackWpdb integration tests with PostgreSQL driver and PostgreSQLQueryTranslator.
 *
 * Activates only when DATABASE_DSN selects the PostgreSQL engine. Connection
 * details (host, port, user, password, database) are parsed from the DSN.
 */
final class PostgreSQLWpdbIntegrationTest extends TestCase
{
    use WpdbIntegrationTestTrait;

    private WPPackWpdb $testWpdb;
    private PostgreSQLDriver $driver;
    private ?\wpdb $originalWpdb = null;
    private ?string $originalTablePrefix = null;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';

        if (!is_string($dsn) || !str_starts_with($dsn, 'pgsql:')) {
            self::markTestSkipped('Requires DATABASE_DSN=pgsql:... (got: ' . ($dsn === '' ? '(unset)' : $dsn) . ')');
        }

        $parsed = Dsn::fromString($dsn);

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        $this->driver = new PostgreSQLDriver(
            host: $parsed->getHost() ?? '127.0.0.1',
            username: $parsed->getUser() ?? 'wppack',
            password: $parsed->getPassword() ?? '',
            database: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            port: $parsed->getPort() ?? 5432,
        );
        $this->driver->connect();

        $this->dropTestTables();

        $this->testWpdb = new WPPackWpdb(
            writer: $this->driver,
            translator: $this->driver->getQueryTranslator(),
            dbname: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
        );

        $this->createWordPressTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->driver) && $this->driver->isConnected()) {
            $this->dropTestTables();
            $this->driver->close();
        }

        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        }

        if ($this->originalTablePrefix !== null) {
            $GLOBALS['table_prefix'] = $this->originalTablePrefix;
        }

        // The swapped-in wpdb seeded cache entries (users, options,
        // user_meta, cron) keyed to the temporary 'wpt_' prefix. Dropping
        // WP's in-memory caches keeps those stale rows from shadowing the
        // real 'wptests_' tables in subsequent tests.
        \wp_cache_flush();
    }

    protected function getTestWpdb(): \wpdb
    {
        return $this->testWpdb;
    }

    private function dropTestTables(): void
    {
        foreach (['wpt_term_relationships', 'wpt_postmeta', 'wpt_usermeta', 'wpt_posts', 'wpt_users', 'wpt_options'] as $table) {
            $this->driver->executeStatement("DROP TABLE IF EXISTS {$table}");
        }
    }
}
