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

namespace WPPack\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Stamp\PriorityStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(PriorityStamp::class)]
final class PriorityStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new PriorityStamp(10);

        self::assertSame(10, $stamp->priority);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function defaultPriority(): void
    {
        $stamp = new PriorityStamp();

        self::assertSame(0, $stamp->priority);
    }

    #[Test]
    public function negativePriority(): void
    {
        $stamp = new PriorityStamp(-5);

        self::assertSame(-5, $stamp->priority);
    }

    #[Test]
    public function zeroPriority(): void
    {
        $stamp = new PriorityStamp(0);

        self::assertSame(0, $stamp->priority);
    }
}
