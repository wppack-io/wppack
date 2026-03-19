<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\Exception\EventBridgeException;
use WpPack\Component\Scheduler\Exception\ExceptionInterface;

#[CoversClass(EventBridgeException::class)]
final class EventBridgeExceptionTest extends TestCase
{
    #[Test]
    public function implementsExceptionInterface(): void
    {
        $exception = new EventBridgeException('test');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = new EventBridgeException('test');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function messageIsPreserved(): void
    {
        $exception = new EventBridgeException('Failed to create schedule');

        self::assertSame('Failed to create schedule', $exception->getMessage());
    }

    #[Test]
    public function previousExceptionIsPreserved(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new EventBridgeException('Wrapped', previous: $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function codeDefaultsToZero(): void
    {
        $exception = new EventBridgeException('test');

        self::assertSame(0, $exception->getCode());
    }
}
