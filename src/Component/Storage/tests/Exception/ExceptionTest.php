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

namespace WpPack\Component\Storage\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Exception\ExceptionInterface;
use WpPack\Component\Storage\Exception\InvalidArgumentException;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\StorageException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\Exception\UnsupportedSchemeException;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function storageExceptionImplementsInterface(): void
    {
        $e = new StorageException('test');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function objectNotFoundExceptionIncludesKey(): void
    {
        $e = new ObjectNotFoundException('path/to/file.txt');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertStringContainsString('path/to/file.txt', $e->getMessage());
    }

    #[Test]
    public function objectNotFoundExceptionPreservesPrevious(): void
    {
        $previous = new \RuntimeException('s3 error');
        $e = new ObjectNotFoundException('file.txt', $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function unsupportedOperationExceptionIncludesDetails(): void
    {
        $e = new UnsupportedOperationException('temporaryUrl', 'local');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\LogicException::class, $e);
        self::assertStringContainsString('temporaryUrl', $e->getMessage());
        self::assertStringContainsString('local', $e->getMessage());
    }

    #[Test]
    public function invalidArgumentExceptionImplementsInterface(): void
    {
        $e = new InvalidArgumentException('bad input');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\InvalidArgumentException::class, $e);
    }

    #[Test]
    public function unsupportedSchemeExceptionBasicMessage(): void
    {
        $dsn = Dsn::fromString('ftp://default');
        $e = new UnsupportedSchemeException($dsn);

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\LogicException::class, $e);
        self::assertStringContainsString('ftp', $e->getMessage());
    }

    #[Test]
    public function unsupportedSchemeExceptionWithSupportedSchemes(): void
    {
        $dsn = Dsn::fromString('ftp://default');
        $e = new UnsupportedSchemeException($dsn, 'Storage', ['s3', 'gcs']);

        self::assertStringContainsString('ftp', $e->getMessage());
        self::assertStringContainsString('Storage', $e->getMessage());
        self::assertStringContainsString('s3, gcs', $e->getMessage());
    }
}
