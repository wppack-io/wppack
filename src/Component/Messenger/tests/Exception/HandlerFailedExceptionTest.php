<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\ExceptionInterface;
use WpPack\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(HandlerFailedException::class)]
final class HandlerFailedExceptionTest extends TestCase
{
    #[Test]
    public function constructWithSingleException(): void
    {
        $envelope = Envelope::wrap(new \stdClass());
        $inner = new \RuntimeException('Handler error');
        $exception = new HandlerFailedException($envelope, [$inner]);

        self::assertStringContainsString('stdClass', $exception->getMessage());
        self::assertStringContainsString('1 handler(s) threw exceptions', $exception->getMessage());
        self::assertSame($inner, $exception->getPrevious());
        self::assertSame($envelope, $exception->getEnvelope());
        self::assertCount(1, $exception->getExceptions());
        self::assertSame($inner, $exception->getExceptions()[0]);
    }

    #[Test]
    public function constructWithMultipleExceptions(): void
    {
        $envelope = Envelope::wrap(new \stdClass());
        $e1 = new \RuntimeException('Error 1');
        $e2 = new \LogicException('Error 2');
        $exception = new HandlerFailedException($envelope, [$e1, $e2]);

        self::assertStringContainsString('2 handler(s) threw exceptions', $exception->getMessage());
        self::assertSame($e1, $exception->getPrevious());
        self::assertCount(2, $exception->getExceptions());
    }

    #[Test]
    public function constructWithEmptyExceptions(): void
    {
        $envelope = Envelope::wrap(new \stdClass());
        $exception = new HandlerFailedException($envelope, []);

        self::assertStringContainsString('0 handler(s) threw exceptions', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->getExceptions());
    }

    #[Test]
    public function implementsExceptionInterface(): void
    {
        $envelope = Envelope::wrap(new \stdClass());
        $exception = new HandlerFailedException($envelope, []);

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function getEnvelopeReturnsOriginalEnvelope(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);
        $exception = new HandlerFailedException($envelope, [new \RuntimeException()]);

        self::assertSame($message, $exception->getEnvelope()->getMessage());
    }
}
