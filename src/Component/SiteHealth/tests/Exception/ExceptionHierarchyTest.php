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

namespace WPPack\Component\SiteHealth\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\SiteHealth\Exception\ExceptionInterface;
use WPPack\Component\SiteHealth\Exception\InvalidArgumentException;
use WPPack\Component\SiteHealth\Exception\LogicException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(LogicException::class)]
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
}
