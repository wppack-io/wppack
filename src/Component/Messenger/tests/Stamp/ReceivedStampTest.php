<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Stamp\ReceivedStamp;
use WpPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(ReceivedStamp::class)]
final class ReceivedStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new ReceivedStamp('sqs');

        self::assertSame('sqs', $stamp->transportName);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function differentTransportName(): void
    {
        $stamp = new ReceivedStamp('lambda');

        self::assertSame('lambda', $stamp->transportName);
    }
}
