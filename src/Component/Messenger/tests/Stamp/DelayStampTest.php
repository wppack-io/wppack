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
use WPPack\Component\Messenger\Stamp\DelayStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(DelayStamp::class)]
final class DelayStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new DelayStamp(5000);

        self::assertSame(5000, $stamp->delayInMilliseconds);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function zeroDelay(): void
    {
        $stamp = new DelayStamp(0);

        self::assertSame(0, $stamp->delayInMilliseconds);
    }
}
