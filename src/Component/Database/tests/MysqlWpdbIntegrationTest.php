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

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Driver\MysqlDriver;
use WpPack\Component\Database\Translator\NullQueryTranslator;
use WpPack\Component\Database\WpPackWpdb;

/**
 * WpPackWpdb integration tests with a native MySQL driver.
 *
 * Runs against the MySQL server configured by the test environment (the same
 * connection used by wp-phpunit). The query translator is a no-op because the
 * source and target dialects are both MySQL; this exercises the WpPackWpdb +
 * MysqlDriver + prepared-statement path end-to-end.
 */
final class MysqlWpdbIntegrationTest extends TestCase
{
    use WpdbIntegrationTestTrait;

    private WpPackWpdb $testWpdb;
    private MysqlDriver $driver;
    private ?\wpdb $originalWpdb = null;
    private ?string $originalTablePrefix = null;

    protected function setUp(): void
    {
        if (!\extension_loaded('mysqli')) {
            self::markTestSkipped('mysqli extension not loaded.');
        }

        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        $this->originalTablePrefix = $GLOBALS['table_prefix'] ?? null;
        $GLOBALS['table_prefix'] = 'wpt_';

        // Reuse the existing wp-phpunit mysqli connection where possible to
        // avoid opening a second socket to the same MySQL instance.
        if ($this->originalWpdb !== null && $this->originalWpdb->dbh instanceof \mysqli) {
            $this->driver = MysqlDriver::fromMysqli($this->originalWpdb->dbh);
        } else {
            $host = \DB_HOST;
            $port = 3306;

            if (str_contains($host, ':')) {
                [$host, $portStr] = explode(':', $host, 2);
                $port = (int) $portStr;
            }

            $this->driver = new MysqlDriver(
                host: $host,
                username: \DB_USER,
                password: \DB_PASSWORD,
                database: \DB_NAME,
                port: $port,
            );
            $this->driver->connect();
        }

        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_term_relationships');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_postmeta');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_usermeta');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_posts');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_users');
        $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_options');

        $this->testWpdb = new WpPackWpdb(
            writer: $this->driver,
            translator: new NullQueryTranslator(),
            dbname: \DB_NAME,
        );

        $this->createWordPressTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->driver) && $this->driver->isConnected()) {
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_term_relationships');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_postmeta');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_usermeta');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_posts');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_users');
            $this->driver->executeStatement('DROP TABLE IF EXISTS wpt_options');
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
