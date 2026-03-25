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

namespace WpPack\Component\HttpFoundation\Tests\File\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\File\Exception\FileException;
use WpPack\Component\HttpFoundation\File\Exception\FileNotFoundException;

final class FileExceptionTest extends TestCase
{
    #[Test]
    public function fileExceptionExtendsRuntimeException(): void
    {
        $exception = new FileException('test');

        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('test', $exception->getMessage());
    }

    #[Test]
    public function fileNotFoundExceptionExtendsFileException(): void
    {
        $exception = new FileNotFoundException('not found');

        self::assertInstanceOf(FileException::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('not found', $exception->getMessage());
    }
}
