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
use WPPack\Component\Messenger\Stamp\BusNameStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(BusNameStamp::class)]
final class BusNameStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new BusNameStamp('command.bus');

        self::assertSame('command.bus', $stamp->busName);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function defaultBusName(): void
    {
        $stamp = new BusNameStamp('default');

        self::assertSame('default', $stamp->busName);
    }

    #[Test]
    public function emptyBusName(): void
    {
        $stamp = new BusNameStamp('');

        self::assertSame('', $stamp->busName);
    }
}
