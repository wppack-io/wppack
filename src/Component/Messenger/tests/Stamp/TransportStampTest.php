<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Stamp\StampInterface;
use WpPack\Component\Messenger\Stamp\TransportStamp;

#[CoversClass(TransportStamp::class)]
final class TransportStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new TransportStamp('async');

        self::assertSame('async', $stamp->transportName);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function sqsTransportName(): void
    {
        $stamp = new TransportStamp('sqs');

        self::assertSame('sqs', $stamp->transportName);
    }
}
