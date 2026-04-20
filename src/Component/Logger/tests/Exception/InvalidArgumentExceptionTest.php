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

namespace WPPack\Component\Logger\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Logger\Exception\ExceptionInterface;
use WPPack\Component\Logger\Exception\InvalidArgumentException;

#[CoversClass(InvalidArgumentException::class)]
final class InvalidArgumentExceptionTest extends TestCase
{
    #[Test]
    public function extendsCoreAndImplementsMarker(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('bad', $e->getMessage());
    }
}
