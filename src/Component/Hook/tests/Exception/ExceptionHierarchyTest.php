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

namespace WPPack\Component\Hook\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Exception\ExceptionInterface;
use WPPack\Component\Hook\Exception\InvalidArgumentException;
use WPPack\Component\Hook\Exception\LogicException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(LogicException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function invalidArgumentExtendsCoreAndImplementsMarker(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('bad', $e->getMessage());
    }

    #[Test]
    public function logicExceptionExtendsCoreAndImplementsMarker(): void
    {
        $e = new LogicException('oops');

        self::assertInstanceOf(\LogicException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('oops', $e->getMessage());
    }
}
