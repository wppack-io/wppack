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

namespace WPPack\Component\Cache\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Adapter\Dsn;
use WPPack\Component\Cache\Exception\UnsupportedSchemeException;

#[CoversClass(UnsupportedSchemeException::class)]
final class UnsupportedSchemeExceptionTest extends TestCase
{
    #[Test]
    public function messageContainsScheme(): void
    {
        $dsn = Dsn::fromString('foo://localhost');
        $exception = new UnsupportedSchemeException($dsn);

        self::assertStringContainsString('"foo"', $exception->getMessage());
        self::assertStringContainsString('not supported', $exception->getMessage());
    }

    #[Test]
    public function messageContainsNameAndSupportedSchemes(): void
    {
        $dsn = Dsn::fromString('bar://localhost');
        $exception = new UnsupportedSchemeException($dsn, 'BarAdapter', ['redis', 'valkey']);

        self::assertStringContainsString('"bar"', $exception->getMessage());
        self::assertStringContainsString('"BarAdapter"', $exception->getMessage());
        self::assertStringContainsString('redis, valkey', $exception->getMessage());
    }

    #[Test]
    public function messageOmitsSupportedWhenNameIsNull(): void
    {
        $dsn = Dsn::fromString('baz://localhost');
        $exception = new UnsupportedSchemeException($dsn, null, ['redis']);

        self::assertStringContainsString('"baz"', $exception->getMessage());
        self::assertStringNotContainsString('Supported schemes', $exception->getMessage());
    }
}
