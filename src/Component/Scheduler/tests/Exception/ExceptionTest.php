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

namespace WpPack\Component\Scheduler\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Exception\ExceptionInterface;
use WpPack\Component\Scheduler\Exception\InvalidArgumentException;
use WpPack\Component\Scheduler\Exception\LogicException;
use WpPack\Component\Scheduler\Exception\SchedulerException;

#[CoversClass(SchedulerException::class)]
#[CoversClass(LogicException::class)]
#[CoversClass(InvalidArgumentException::class)]
final class ExceptionTest extends TestCase
{
    #[Test]
    public function schedulerExceptionImplementsInterface(): void
    {
        $e = new SchedulerException('test error');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('test error', $e->getMessage());
    }

    #[Test]
    public function schedulerExceptionPreservesPrevious(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new SchedulerException('wrapped', 0, $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function logicExceptionImplementsInterface(): void
    {
        $e = new LogicException('logic error');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\LogicException::class, $e);
        self::assertSame('logic error', $e->getMessage());
    }

    #[Test]
    public function invalidArgumentExceptionImplementsInterface(): void
    {
        $e = new InvalidArgumentException('bad argument');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertSame('bad argument', $e->getMessage());
    }
}
