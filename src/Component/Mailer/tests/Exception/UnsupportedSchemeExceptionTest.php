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

namespace WPPack\Component\Mailer\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Mailer\Exception\UnsupportedSchemeException;

final class UnsupportedSchemeExceptionTest extends TestCase
{
    #[Test]
    public function messageContainsScheme(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringContainsString('not supported', $exception->getMessage());
    }

    #[Test]
    public function messageContainsNameAndSupportedSchemes(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn, 'TestTransport', ['smtp', 'smtps']);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringContainsString('"TestTransport"', $exception->getMessage());
        self::assertStringContainsString('smtp, smtps', $exception->getMessage());
    }

    #[Test]
    public function messageWithoutNameDoesNotAppendSupported(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn, null, ['smtp']);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringNotContainsString('smtp', $exception->getMessage());
    }

    #[Test]
    public function messageWithNameButEmptySupportedDoesNotAppend(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn, 'TestTransport', []);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringNotContainsString('TestTransport', $exception->getMessage());
    }
}
