<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\NullTransport;

final class NullTransportTest extends TestCase
{

    #[Test]
    public function getNameReturnsNull(): void
    {
        $transport = new NullTransport();

        self::assertSame('null', $transport->getName());
    }

    #[Test]
    public function sendIsNoOp(): void
    {
        $transport = new NullTransport();
        $phpMailer = new PhpMailer(true);

        // send should succeed without side effects (no-op)
        $transport->send($phpMailer);

        self::assertTrue(true);
    }

    #[Test]
    public function setTransportAndPostSendSucceeds(): void
    {
        $transport = new NullTransport();
        $phpMailer = new PhpMailer(true);
        $phpMailer->setTransport($transport);

        self::assertSame('null', $phpMailer->Mailer);

        $result = $phpMailer->postSend();
        self::assertTrue($result);
    }
}
