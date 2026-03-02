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
        $sendmailPath = ini_get('sendmail_path');
        if (!$sendmailPath || !is_executable(explode(' ', $sendmailPath)[0])) {
            self::markTestSkipped('sendmail is not available.');
        }

        $transport = new NativeTransport();
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com', 'Sender');
        $phpMailer->addAddress('user@example.com', 'User');
        $phpMailer->Subject = 'Test';
        $phpMailer->Body = 'Hello';
        $phpMailer->Mailer = 'mail';

        $transport->send($phpMailer);
        self::assertTrue(true);
    }
}
