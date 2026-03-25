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

namespace WpPack\Component\Messenger\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Exception\ExceptionInterface;
use WpPack\Component\Messenger\Exception\InvalidArgumentException;
use WpPack\Component\Messenger\Exception\MessageDecodingFailedException;
use WpPack\Component\Messenger\Exception\MessageEncodingFailedException;
use WpPack\Component\Messenger\Exception\NoHandlerForMessageException;
use WpPack\Component\Messenger\Exception\TransportException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(NoHandlerForMessageException::class)]
#[CoversClass(TransportException::class)]
#[CoversClass(MessageDecodingFailedException::class)]
#[CoversClass(MessageEncodingFailedException::class)]
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
    public function noHandlerForMessageExceptionImplementsInterface(): void
    {
        $exception = new NoHandlerForMessageException('No handler found');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\LogicException::class, $exception);
        self::assertSame('No handler found', $exception->getMessage());
    }

    #[Test]
    public function transportExceptionImplementsInterface(): void
    {
        $exception = new TransportException('Transport error');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Transport error', $exception->getMessage());
    }

    #[Test]
    public function messageDecodingFailedExceptionImplementsInterface(): void
    {
        $exception = new MessageDecodingFailedException('Decoding error');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Decoding error', $exception->getMessage());
    }

    #[Test]
    public function messageEncodingFailedExceptionImplementsInterface(): void
    {
        $exception = new MessageEncodingFailedException('Encoding error');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('Encoding error', $exception->getMessage());
    }

    #[Test]
    public function transportExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Original');
        $exception = new TransportException('Transport error', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function messageDecodingFailedExceptionWithCode(): void
    {
        $exception = new MessageDecodingFailedException('error', 42);

        self::assertSame(42, $exception->getCode());
    }

    #[Test]
    public function messageEncodingFailedExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('inner');
        $exception = new MessageEncodingFailedException('outer', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
