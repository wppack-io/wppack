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
use WpPack\Component\Dsn\Dsn;

/**
 * WpPackWpdb integration tests with SQLite driver and SqliteQueryTranslator.
 *
 * Activates only when DATABASE_DSN selects the SQLite engine. The DSN path
 * determines the SQLite database file (':memory:' is supported).
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

        $parsed = Dsn::fromString($dsn);
        $path = $parsed->getPath() ?? '';
        if ($path === '' || $path === ':memory:') {
            $path = ':memory:';
        }

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        $this->driver = new SqliteDriver($path);
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
