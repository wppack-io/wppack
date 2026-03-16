<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Stamp\StampInterface;

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
