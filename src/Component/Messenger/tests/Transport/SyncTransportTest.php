<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\SentStamp;
use WpPack\Component\Messenger\Transport\SyncTransport;
use WpPack\Component\Messenger\Transport\TransportInterface;

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
