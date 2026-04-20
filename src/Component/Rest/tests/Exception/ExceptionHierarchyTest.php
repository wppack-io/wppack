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

namespace WPPack\Component\Rest\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Rest\Exception\MissingParametersException;
use WPPack\Component\Rest\Exception\RouteNotFoundException;

#[CoversClass(MissingParametersException::class)]
#[CoversClass(RouteNotFoundException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function exceptionsExtendCoreInvalidArgument(): void
    {
        self::assertInstanceOf(\InvalidArgumentException::class, new MissingParametersException('missing'));
        self::assertInstanceOf(\InvalidArgumentException::class, new RouteNotFoundException('no route'));
    }

    #[Test]
    public function messagePreserved(): void
    {
        self::assertSame('missing foo', (new MissingParametersException('missing foo'))->getMessage());
        self::assertSame('no route bar', (new RouteNotFoundException('no route bar'))->getMessage());
    }
}
