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

namespace WPPack\Component\Mailer\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Exception\ExceptionInterface;
use WPPack\Component\Mailer\Exception\InvalidArgumentException;
use WPPack\Component\Mailer\Exception\TransportException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(TransportException::class)]
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
    public function transportExceptionExtendsRuntime(): void
    {
        $e = new TransportException('boom');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }
}
