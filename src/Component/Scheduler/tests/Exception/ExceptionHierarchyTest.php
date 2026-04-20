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

namespace WPPack\Component\Scheduler\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Exception\ExceptionInterface;
use WPPack\Component\Scheduler\Exception\InvalidArgumentException;
use WPPack\Component\Scheduler\Exception\LogicException;
use WPPack\Component\Scheduler\Exception\SchedulerException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(LogicException::class)]
#[CoversClass(SchedulerException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function invalidArgumentExtendsCore(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('bad', $e->getMessage());
    }

    #[Test]
    public function logicExceptionExtendsCore(): void
    {
        $e = new LogicException('oops');

        self::assertInstanceOf(\LogicException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }

    #[Test]
    public function schedulerExceptionCarriesMessage(): void
    {
        $e = new SchedulerException('crashed');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('crashed', $e->getMessage());
    }
}
