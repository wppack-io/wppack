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

use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Sqlite\SqliteDriver;
use WpPack\Component\Database\Tests\WpdbIntegrationTestTrait;
use WpPack\Component\Database\WpPackWpdb;

/**
 * WpPackWpdb integration tests with SQLite driver and SqliteQueryTranslator.
 *
 * Activates only when DATABASE_DSN selects the SQLite engine. The DSN path
 * is ignored — every test runs against a fresh ':memory:' database because
 * `createWordPressTables()` uses `CREATE TABLE IF NOT EXISTS` (idempotent)
 * and shared file-backed DBs let data leak across tests.
 */
final class SqliteWpdbIntegrationTest extends TestCase
{
    use WpdbIntegrationTestTrait;

    private WpPackWpdb $testWpdb;
    private SqliteDriver $driver;
    private ?\wpdb $originalWpdb = null;
    private ?string $originalTablePrefix = null;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';

        if (!is_string($dsn) || !str_starts_with($dsn, 'sqlite:')) {
            self::markTestSkipped('Requires DATABASE_DSN=sqlite:... (got: ' . ($dsn === '' ? '(unset)' : $dsn) . ')');
        }

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        $this->driver = new SqliteDriver(':memory:');
        $this->driver->connect();

        $this->testWpdb = new WpPackWpdb(
            writer: $this->driver,
            translator: $this->driver->getQueryTranslator(),
            dbname: 'test',
        );

        $this->createWordPressTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->driver) && $this->driver->isConnected()) {
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
}
