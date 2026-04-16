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
use WpPack\Component\Dsn\Dsn;

/**
 * WpPackWpdb integration tests with PostgreSQL driver and PostgresqlQueryTranslator.
 *
 * Activates only when DATABASE_DSN selects the PostgreSQL engine. Connection
 * details (host, port, user, password, database) are parsed from the DSN.
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
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';

        if (!is_string($dsn) || !str_starts_with($dsn, 'pgsql:')) {
            self::markTestSkipped('Requires DATABASE_DSN=pgsql:... (got: ' . ($dsn === '' ? '(unset)' : $dsn) . ')');
        }

        $parsed = Dsn::fromString($dsn);

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        $this->driver = new PgsqlDriver(
            host: $parsed->getHost() ?? '127.0.0.1',
            username: $parsed->getUser() ?? 'wppack',
            password: $parsed->getPassword() ?? '',
            database: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            port: $parsed->getPort() ?? 5432,
        );
        $this->driver->connect();

        $this->dropTestTables();

        $this->testWpdb = new WpPackWpdb(
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
