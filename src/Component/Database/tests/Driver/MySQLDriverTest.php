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
use WPPack\Component\Database\Driver\MySQLDriver;
use WPPack\Component\Dsn\Dsn;

/**
 * Integration tests for MySQLDriver.
 *
 * Activates only when DATABASE_DSN points to a MySQL backend. The driver
 * is constructed from the DSN and then exercised against a scratch table.
 *
 * The quoteStringLiteral* cases assume MySQL default sql_mode — specifically
 * that NO_BACKSLASH_ESCAPES is *not* set. MySQLDriver::setCompatibleSqlMode()
 * does not flip that mode either way, so on a server configured with
 * NO_BACKSLASH_ESCAPES on, the expected 'O\\'Brien' output becomes
 * 'O''Brien' and these asserts will fail. That is an acceptable limitation:
 * real-world WP installs keep the default, and switching the mode at test
 * boot would divert from the rest of the suite.
 */
final class MySQLDriverTest extends TestCase
{
    private MySQLDriver $driver;

    protected function setUp(): void
    {
        $dsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';

        if (!is_string($dsn) || !(str_starts_with($dsn, 'mysql:') || str_starts_with($dsn, 'mariadb:'))) {
            self::markTestSkipped('Requires DATABASE_DSN=mysql:... (got: ' . ($dsn === '' ? '(unset)' : $dsn) . ')');
        }

        $parsed = Dsn::fromString($dsn);

        $this->driver = new MySQLDriver(
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
    public function quoteStringLiteralWrapsAndEscapes(): void
    {
        self::assertSame("'hello'", $this->driver->quoteStringLiteral('hello'));
        // MySQL's mysqli_real_escape_string uses backslash escapes by default.
        self::assertSame("'O\\'Brien'", $this->driver->quoteStringLiteral("O'Brien"));
    }

    #[Test]
    public function quoteStringLiteralHandlesEmptyString(): void
    {
        self::assertSame("''", $this->driver->quoteStringLiteral(''));
    }

    #[Test]
    public function quoteStringLiteralEscapesBackslashAndNewline(): void
    {
        self::assertSame("'a\\\\b'", $this->driver->quoteStringLiteral('a\\b'));
        self::assertSame("'line1\\nline2'", $this->driver->quoteStringLiteral("line1\nline2"));
    }
}
