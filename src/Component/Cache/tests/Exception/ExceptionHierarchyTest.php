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
use WPPack\Component\Cache\Exception\AdapterException;
use WPPack\Component\Cache\Exception\ExceptionInterface;
use WPPack\Component\Cache\Exception\InvalidArgumentException;
use WPPack\Component\Cache\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

#[CoversClass(AdapterException::class)]
#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(UnsupportedSchemeException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function adapterExceptionIsRuntimeExceptionImplementingMarker(): void
    {
        $e = new AdapterException('boom');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('boom', $e->getMessage());
    }

    #[Test]
    public function invalidArgumentExceptionIsCoreInvalidArgument(): void
    {
        $e = new InvalidArgumentException('nope');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }

    #[Test]
    public function unsupportedSchemeExceptionImplementsMarker(): void
    {
        $dsn = Dsn::fromString('foo://host');
        $e = new UnsupportedSchemeException($dsn);

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\LogicException::class, $e);
        self::assertStringContainsString('foo', $e->getMessage());
        self::assertStringContainsString('not supported', $e->getMessage());
    }

    #[Test]
    public function unsupportedSchemeExceptionAppendsSupportedList(): void
    {
        $dsn = Dsn::fromString('foo://host');
        $e = new UnsupportedSchemeException($dsn, name: 'cache', supported: ['redis', 'memcached']);

        self::assertStringContainsString('Supported schemes for "cache": redis, memcached', $e->getMessage());
    }

    #[Test]
    public function exceptionInterfaceIsShared(): void
    {
        foreach ([AdapterException::class, InvalidArgumentException::class, UnsupportedSchemeException::class] as $class) {
            self::assertContains(ExceptionInterface::class, class_implements($class), "{$class} must implement ExceptionInterface");
        }
    }
}
