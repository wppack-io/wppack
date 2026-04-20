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

namespace WPPack\Component\Storage\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Storage\Exception\ExceptionInterface;
use WPPack\Component\Storage\Exception\InvalidArgumentException;
use WPPack\Component\Storage\Exception\ObjectNotFoundException;
use WPPack\Component\Storage\Exception\StorageException;
use WPPack\Component\Storage\Exception\UnsupportedOperationException;
use WPPack\Component\Storage\Exception\UnsupportedSchemeException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(ObjectNotFoundException::class)]
#[CoversClass(StorageException::class)]
#[CoversClass(UnsupportedOperationException::class)]
#[CoversClass(UnsupportedSchemeException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function storageExceptionCarriesMessage(): void
    {
        $e = new StorageException('storage error');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('storage error', $e->getMessage());
    }

    #[Test]
    public function objectNotFoundExceptionFormatsPathIntoMessage(): void
    {
        $previous = new \RuntimeException('inner');
        $e = new ObjectNotFoundException('foo/bar.txt', $previous);

        self::assertInstanceOf(StorageException::class, $e);
        self::assertSame('Object not found: "foo/bar.txt".', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function invalidArgumentExtendsCore(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }

    #[Test]
    public function unsupportedOperationExceptionFormatsOperationAndAdapter(): void
    {
        $e = new UnsupportedOperationException('download', 'local');

        self::assertInstanceOf(\LogicException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('The "download" operation is not supported by the "local" adapter.', $e->getMessage());
    }

    #[Test]
    public function unsupportedSchemeExceptionIsLogicExceptionImplementingMarker(): void
    {
        $dsn = Dsn::fromString('foo://host');
        $e = new UnsupportedSchemeException($dsn);

        self::assertInstanceOf(\LogicException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertStringContainsString('foo', $e->getMessage());
    }

    #[Test]
    public function unsupportedSchemeExceptionAppendsSupportedList(): void
    {
        $dsn = Dsn::fromString('foo://host');
        $e = new UnsupportedSchemeException($dsn, name: 'storage', supported: ['s3', 'file']);

        self::assertStringContainsString('Supported schemes for "storage": s3, file', $e->getMessage());
    }
}
