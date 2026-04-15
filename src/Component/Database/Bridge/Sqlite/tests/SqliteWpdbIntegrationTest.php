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
 * Verifies that WordPress-style MySQL queries are correctly translated to
 * SQLite dialect and executed end-to-end. Uses :memory: database for each test.
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
        $this->driver->close();

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
