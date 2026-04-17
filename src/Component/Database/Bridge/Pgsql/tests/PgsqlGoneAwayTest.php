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
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Dsn\Dsn;

/**
 * Regression test for PgsqlDriver::throwQueryError() — the gone-away
 * detection path that drops the stale pg handle and lets the next
 * ensureConnected() reopen transparently after a pg_terminate_backend()
 * or TCP RST.
 */
final class PgsqlGoneAwayTest extends TestCase
{
    private PgsqlDriver $driver;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';
        if (!is_string($dsn) || !str_starts_with($dsn, 'pgsql:')) {
            self::markTestSkipped('Requires pgsql DSN');
        }

        $parsed = Dsn::fromString($dsn);
        $this->driver = new PgsqlDriver(
            host: $parsed->getHost() ?? '127.0.0.1',
            username: $parsed->getUser() ?? 'wppack',
            password: $parsed->getPassword() ?? 'wppack',
            database: ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
            port: $parsed->getPort() ?? 5432,
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
    public function terminateBackendDropsHandleAndNextQueryReconnects(): void
    {
        // Grab our own backend pid and terminate it via a second
        // connection. The next query should fail with a gone-away-style
        // error, the driver should drop the connection, and the query
        // AFTER that should succeed transparently.
        $result = $this->driver->executeQuery('SELECT pg_backend_pid() AS pid');
        $pid = (int) $result->fetchOne();
        self::assertGreaterThan(0, $pid);

        $parsed = Dsn::fromString((string) ($_SERVER['DATABASE_DSN'] ?? ''));
        $admin = pg_connect(\sprintf(
            'host=%s port=%d user=%s password=%s dbname=%s',
            $parsed->getHost() ?? '127.0.0.1',
            $parsed->getPort() ?? 5432,
            $parsed->getUser() ?? 'wppack',
            $parsed->getPassword() ?? 'wppack',
            ltrim($parsed->getPath() ?? '', '/') ?: 'wppack_test',
        ));
        self::assertNotFalse($admin);

        pg_query($admin, \sprintf('SELECT pg_terminate_backend(%d)', $pid));
        pg_close($admin);

        usleep(100_000);

        $caught = false;
        try {
            $this->driver->executeQuery('SELECT 1');
        } catch (DriverException) {
            $caught = true;
        }
        self::assertTrue($caught, 'First post-terminate query must raise a DriverException.');

        $again = $this->driver->executeQuery('SELECT 2 AS n');
        self::assertSame(2, (int) $again->fetchOne(), 'Second post-terminate query must succeed after transparent reconnect.');
    }
}
