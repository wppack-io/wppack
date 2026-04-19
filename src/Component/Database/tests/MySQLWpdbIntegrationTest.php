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

namespace WPPack\Component\Database\Tests;

use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Database\WPPackWpdb;

/**
 * WPPackWpdb integration tests against a MySQL backend.
 *
 * Activates only when DATABASE_DSN selects the MySQL engine. The db.php
 * drop-in must have already configured the global $wpdb as a WPPackWpdb
 * backed by a MySQLDriver — this test reuses that driver so it shares
 * the connection (and therefore LAST_INSERT_ID semantics) with the
 * bootstrap.
 */
final class MySQLWpdbIntegrationTest extends TestCase
{
    use WpdbIntegrationTestTrait;

    private WPPackWpdb $testWpdb;
    private DriverInterface $driver;
    private ?\wpdb $originalWpdb = null;
    private ?string $originalTablePrefix = null;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';

        if (!is_string($dsn) || !(str_starts_with($dsn, 'mysql:') || str_starts_with($dsn, 'mariadb:'))) {
            self::markTestSkipped('Requires DATABASE_DSN=mysql:... (got: ' . ($dsn === '' ? '(unset)' : $dsn) . ')');
        }

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;

        if (!$this->originalWpdb instanceof WPPackWpdb) {
            self::markTestSkipped('Requires the db.php drop-in to have activated WPPackWpdb.');
        }

        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        $this->driver = $this->originalWpdb->getWriter();
        $this->dropTestTables();

        $this->testWpdb = new WPPackWpdb(
            writer: $this->driver,
            translator: $this->originalWpdb->getTranslator(),
            dbname: $this->originalWpdb->dbname,
        );

        $this->createWordPressTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->driver)) {
            $this->dropTestTables();
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
            $this->driver->executeStatement("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}
