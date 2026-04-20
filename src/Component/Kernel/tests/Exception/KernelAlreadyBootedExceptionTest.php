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

namespace WPPack\Component\Kernel\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Kernel\Exception\ExceptionInterface;
use WPPack\Component\Kernel\Exception\KernelAlreadyBootedException;

#[CoversClass(KernelAlreadyBootedException::class)]
final class KernelAlreadyBootedExceptionTest extends TestCase
{
    #[Test]
    public function messageIsFixedAndHierarchyIsCorrect(): void
    {
        $e = new KernelAlreadyBootedException();

        self::assertInstanceOf(\LogicException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('Kernel has already been booted.', $e->getMessage());
    }
}
