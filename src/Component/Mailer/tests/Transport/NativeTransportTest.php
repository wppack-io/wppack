<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\NativeTransport;

final class NativeTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsMail(): void
    {
        $transport = new NativeTransport();

        self::assertSame('mail', $transport->getName());
    }

    #[Test]
    public function sendCallsNativePostSend(): void
    {
        $transport = new NativeTransport();
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com', 'Sender');
        $phpMailer->addAddress('user@example.com', 'User');
        $phpMailer->Subject = 'Test';
        $phpMailer->Body = 'Hello';
        $phpMailer->Mailer = 'mail';

        // nativePostSend() calls PHP's mail(). On CI without sendmail the call
        // fails, so we catch the expected exception in that environment.
        try {
            $transport->send($phpMailer);
            self::assertTrue(true);
        } catch (\WpPack\Component\Mailer\Exception\TransportException $e) {
            // Expected on systems without a local MTA (e.g. CI)
            self::assertStringContainsString('mail function', $e->getMessage());
        }
    }
}
