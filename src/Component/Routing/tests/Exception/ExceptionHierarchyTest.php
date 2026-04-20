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

namespace WPPack\Component\Routing\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Routing\Exception\MissingParametersException;
use WPPack\Component\Routing\Exception\RouteNotFoundException;

#[CoversClass(MissingParametersException::class)]
#[CoversClass(RouteNotFoundException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function bothExceptionsExtendCoreInvalidArgument(): void
    {
        self::assertInstanceOf(\InvalidArgumentException::class, new MissingParametersException('missing'));
        self::assertInstanceOf(\InvalidArgumentException::class, new RouteNotFoundException('missing'));
    }

    #[Test]
    public function messagePreservation(): void
    {
        self::assertSame('missing param name', (new MissingParametersException('missing param name'))->getMessage());
        self::assertSame('route not found', (new RouteNotFoundException('route not found'))->getMessage());
    }
}
