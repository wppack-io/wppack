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

namespace WPPack\Component\Messenger\Tests\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Stamp\SentStamp;
use WPPack\Component\Messenger\Transport\SyncTransport;
use WPPack\Component\Messenger\Transport\TransportInterface;

#[CoversClass(SyncTransport::class)]
final class SyncTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsSync(): void
    {
        $transport = new SyncTransport();

        self::assertSame('sync', $transport->getName());
    }

    #[Test]
    public function implementsTransportInterface(): void
    {
        $transport = new SyncTransport();

        self::assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function sendAddsSentStamp(): void
    {
        $transport = new SyncTransport();
        $envelope = Envelope::wrap(new \stdClass());

        $result = $transport->send($envelope);

        self::assertNotNull($result->last(SentStamp::class));
        self::assertSame('sync', $result->last(SentStamp::class)->transportName);
    }
}
