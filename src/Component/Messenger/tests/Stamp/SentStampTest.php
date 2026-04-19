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
use WPPack\Component\Messenger\Stamp\SentStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(SentStamp::class)]
final class SentStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new SentStamp('sqs');

        self::assertSame('sqs', $stamp->transportName);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function syncTransportName(): void
    {
        $stamp = new SentStamp('sync');

        self::assertSame('sync', $stamp->transportName);
    }
}
