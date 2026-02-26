<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Transport\SmtpTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class SmtpTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }
    }

    #[Test]
    public function configureSetsSMTPMode(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertSame('smtp', $phpMailer->Mailer);
    }

    #[Test]
    public function configureSetsHostAndPort(): void
    {
        $transport = new SmtpTransport('mail.example.com', 465);
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertSame('mail.example.com', $phpMailer->Host);
        self::assertSame(465, $phpMailer->Port);
    }

    #[Test]
    public function configureSetsEncryption(): void
    {
        $transport = new SmtpTransport('smtp.example.com', 465, encryption: 'ssl');
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertSame('ssl', $phpMailer->SMTPSecure);
    }

    #[Test]
    public function configureDefaultEncryptionIsTls(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertSame('tls', $phpMailer->SMTPSecure);
    }

    #[Test]
    public function configureDefaultPortIs587(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertSame(587, $phpMailer->Port);
    }

    #[Test]
    public function configureWithAuthSetsCredentials(): void
    {
        $transport = new SmtpTransport(
            'smtp.example.com',
            587,
            'user@example.com',
            'secret-password',
        );
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertTrue($phpMailer->SMTPAuth);
        self::assertSame('user@example.com', $phpMailer->Username);
        self::assertSame('secret-password', $phpMailer->Password);
    }

    #[Test]
    public function configureWithUsernameOnlySetsEmptyPassword(): void
    {
        $transport = new SmtpTransport(
            'smtp.example.com',
            587,
            'user@example.com',
        );
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertTrue($phpMailer->SMTPAuth);
        self::assertSame('user@example.com', $phpMailer->Username);
        self::assertSame('', $phpMailer->Password);
    }

    #[Test]
    public function configureWithoutAuthDoesNotSetSMTPAuth(): void
    {
        $transport = new SmtpTransport('smtp.example.com');
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertFalse($phpMailer->SMTPAuth);
    }

    #[Test]
    public function toStringReturnsSmtpUri(): void
    {
        $transport = new SmtpTransport('smtp.example.com', 587);

        self::assertSame('smtp://smtp.example.com:587', (string) $transport);
    }

    #[Test]
    public function toStringWithCustomPort(): void
    {
        $transport = new SmtpTransport('mail.example.com', 465);

        self::assertSame('smtp://mail.example.com:465', (string) $transport);
    }

    #[Test]
    public function toStringWithDefaultPort(): void
    {
        $transport = new SmtpTransport('smtp.example.com');

        self::assertSame('smtp://smtp.example.com:587', (string) $transport);
    }
}
