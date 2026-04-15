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

use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\Tests\WpdbIntegrationTestTrait;
use WpPack\Component\Database\WpPackWpdb;

/**
 * WpPackWpdb integration tests with PostgreSQL driver and PostgresqlQueryTranslator.
 *
 * Verifies that WordPress-style MySQL queries are correctly translated to
 * PostgreSQL dialect and executed end-to-end. Requires a running PostgreSQL
 * instance — skipped when WPPACK_TEST_PGSQL_HOST is not set.
 */
final class PgsqlWpdbIntegrationTest extends TestCase
{
    use WpdbIntegrationTestTrait;

    private WpPackWpdb $testWpdb;
    private PgsqlDriver $driver;
    private ?\wpdb $originalWpdb = null;
    private ?string $originalTablePrefix = null;

    protected function setUp(): void
    {
        $host = $_SERVER['WPPACK_TEST_PGSQL_HOST'] ?? $_ENV['WPPACK_TEST_PGSQL_HOST'] ?? '';

        if ($host === '') {
            self::markTestSkipped('PostgreSQL not available (set WPPACK_TEST_PGSQL_HOST).');
        }

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        $this->driver = new PgsqlDriver(
            host: $host,
            username: $_SERVER['WPPACK_TEST_PGSQL_USER'] ?? $_ENV['WPPACK_TEST_PGSQL_USER'] ?? 'wppack',
            password: $_SERVER['WPPACK_TEST_PGSQL_PASSWORD'] ?? $_ENV['WPPACK_TEST_PGSQL_PASSWORD'] ?? 'wppack',
            database: $_SERVER['WPPACK_TEST_PGSQL_DATABASE'] ?? $_ENV['WPPACK_TEST_PGSQL_DATABASE'] ?? 'wppack_test',
            port: (int) ($_SERVER['WPPACK_TEST_PGSQL_PORT'] ?? $_ENV['WPPACK_TEST_PGSQL_PORT'] ?? '5432'),
        );
        $this->driver->connect();

        // Drop tables from previous runs
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_postmeta');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_usermeta');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_posts');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_users');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_options');

        $this->testWpdb = new WpPackWpdb(
            writer: $this->driver,
            translator: $this->driver->getQueryTranslator(),
            dbname: 'wppack_test',
        );

        $this->createWordPressTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->driver) && $this->driver->isConnected()) {
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_postmeta');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_usermeta');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_posts');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_users');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_options');
            $this->driver->close();
        }

        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        }

        if ($this->originalTablePrefix !== null) {
            $GLOBALS['table_prefix'] = $this->originalTablePrefix;
        }
    }

    protected function getTestWpdb(): WpPackWpdb
    {
        return $this->testWpdb;
    }
}
