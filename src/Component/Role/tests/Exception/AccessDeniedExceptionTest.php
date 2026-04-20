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

namespace WPPack\Component\Role\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\Exception\AccessDeniedException;
use WPPack\Component\Role\Exception\ExceptionInterface;

#[CoversClass(AccessDeniedException::class)]
final class AccessDeniedExceptionTest extends TestCase
{
    #[Test]
    public function defaultsToAccessDeniedWith403(): void
    {
        $e = new AccessDeniedException();

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('Access Denied.', $e->getMessage());
        self::assertSame(403, $e->getCode());
    }

    #[Test]
    public function customMessageAndCodeArePreserved(): void
    {
        $previous = new \RuntimeException('cause');
        $e = new AccessDeniedException('Forbidden.', 401, $previous);

        self::assertSame('Forbidden.', $e->getMessage());
        self::assertSame(401, $e->getCode());
        self::assertSame($previous, $e->getPrevious());
    }
}
