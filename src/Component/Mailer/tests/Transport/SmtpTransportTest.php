<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\SmtpTransport;

final class SmtpTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsSmtp(): void
    {
        $transport = new SmtpTransport('smtp.example.com');

        self::assertSame('smtp', $transport->getName());
    }

    #[Test]
    public function sendConfiguresSMTPAndThrowsOnFailure(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new PhpMailer(true);

        // send() will configure SMTP settings and then call nativePostSend(),
        // which will fail because we haven't called preSend()
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('SMTP send failed');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendSetsHostAndPort(): void
    {
        $transport = new SmtpTransport('mail.example.com', 465);
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
            // Expected - nativePostSend fails without preSend
        }

        self::assertSame('mail.example.com', $phpMailer->Host);
        self::assertSame(465, $phpMailer->Port);
    }

    #[Test]
    public function sendSetsEncryption(): void
    {
        $transport = new SmtpTransport('smtp.example.com', 465, encryption: 'ssl');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame('ssl', $phpMailer->SMTPSecure);
    }

    #[Test]
    public function sendDefaultEncryptionIsTls(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame('tls', $phpMailer->SMTPSecure);
    }

    #[Test]
    public function sendDefaultPortIs587(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame(587, $phpMailer->Port);
    }

    #[Test]
    public function sendWithAuthSetsCredentials(): void
    {
        $transport = new SmtpTransport(
            'smtp.example.com',
            587,
            'user@example.com',
            'secret-password',
        );
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertTrue($phpMailer->SMTPAuth);
        self::assertSame('user@example.com', $phpMailer->Username);
        self::assertSame('secret-password', $phpMailer->Password);
    }

    #[Test]
    public function sendWithUsernameOnlySetsEmptyPassword(): void
    {
        $transport = new SmtpTransport(
            'smtp.example.com',
            587,
            'user@example.com',
        );
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertTrue($phpMailer->SMTPAuth);
        self::assertSame('user@example.com', $phpMailer->Username);
        self::assertSame('', $phpMailer->Password);
    }

    #[Test]
    public function sendWithoutAuthDoesNotSetSMTPAuth(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertFalse($phpMailer->SMTPAuth);
    }

}
