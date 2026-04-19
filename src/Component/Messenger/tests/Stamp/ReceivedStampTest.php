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
use WPPack\Component\Messenger\Stamp\ReceivedStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

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
