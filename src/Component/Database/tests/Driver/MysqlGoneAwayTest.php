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

namespace WPPack\Component\Database\Tests\Driver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Driver\MysqlDriver;
use WPPack\Component\Database\Exception\DriverException;

/**
 * Regression tests for MysqlDriver::throwQueryError() — the gone-away
 * detection path that drops the stale mysqli handle and lets the next
 * ensureConnected() reopen transparently.
 *
 * Integration-style: requires a real MySQL server (DATABASE_DSN=mysql:…)
 * because faking errno 2006/2013 on a mock mysqli would lose the actual
 * behaviour we're pinning.
 */
final class MysqlGoneAwayTest extends TestCase
{
    private MysqlDriver $driver;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';
        if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
            self::markTestSkipped('Requires mysql DSN');
        }

        $parsed = \WPPack\Component\Dsn\Dsn::fromString($dsn);
        $this->driver = new MysqlDriver(
            host: $parsed->getHost() ?? '127.0.0.1',
            username: $parsed->getUser() ?? 'root',
            password: $parsed->getPassword() ?? '',
            database: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            port: $parsed->getPort() ?? 3306,
        );
        $this->driver->connect();
    }

    protected function tearDown(): void
    {
        if (isset($this->driver) && $this->driver->isConnected()) {
            $this->driver->close();
        }
    }

    #[Test]
    public function killingSessionDropsHandleAndNextQueryReconnects(): void
    {
        // Grab our own connection id so we can kill it via a second
        // connection. The driver should then see errno 2006 / 2013 on the
        // next query, drop the connection, and reconnect transparently.
        $result = $this->driver->executeQuery('SELECT CONNECTION_ID() AS id');
        $myConnId = (int) $result->fetchOne();
        self::assertGreaterThan(0, $myConnId);

        // Administer KILL via a fresh mysqli. This terminates our own
        // session without going through our driver (so we can observe
        // the gone-away path in isolation).
        $parsed = \WPPack\Component\Dsn\Dsn::fromString((string) ($_SERVER['DATABASE_DSN'] ?? ''));
        $admin = new \mysqli(
            $parsed->getHost() ?? '127.0.0.1',
            $parsed->getUser() ?? 'root',
            $parsed->getPassword() ?? '',
            ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            $parsed->getPort() ?? 3306,
        );
        $admin->query("KILL {$myConnId}");
        $admin->close();

        // Wait briefly for the kill to propagate.
        usleep(100_000);

        // The next query should throw (server gone away) — but the
        // query AFTER that should succeed because the driver reconnected.
        $caught = false;
        try {
            $this->driver->executeQuery('SELECT 1');
        } catch (DriverException) {
            $caught = true;
        }
        self::assertTrue($caught, 'First post-kill query must throw a DriverException.');

        $again = $this->driver->executeQuery('SELECT 2 AS n');
        self::assertSame(2, (int) $again->fetchOne(), 'Second post-kill query must succeed after transparent reconnect.');
    }
}
