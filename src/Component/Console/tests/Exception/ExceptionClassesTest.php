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

namespace WPPack\Component\Console\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\Exception\ExceptionInterface;
use WPPack\Component\Console\Exception\InvalidArgumentException;
use WPPack\Component\Console\Exception\LogicException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(LogicException::class)]
final class ExceptionClassesTest extends TestCase
{
    #[Test]
    public function invalidArgumentExceptionImplementsInterface(): void
    {
        $exception = new InvalidArgumentException('test');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
        self::assertSame('test', $exception->getMessage());
    }

    #[Test]
    public function logicExceptionImplementsInterface(): void
    {
        $exception = new LogicException('logic error');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\LogicException::class, $exception);
        self::assertSame('logic error', $exception->getMessage());
    }

    #[Test]
    public function invalidArgumentExceptionWithCode(): void
    {
        $exception = new InvalidArgumentException('test', 42);

        self::assertSame(42, $exception->getCode());
    }

    #[Test]
    public function logicExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('inner');
        $exception = new LogicException('outer', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
