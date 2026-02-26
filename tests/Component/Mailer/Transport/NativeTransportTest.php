<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Transport\NativeTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class NativeTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }
    }

    #[Test]
    public function configureDoesNotModifyMailer(): void
    {
        $transport = new NativeTransport();
        $phpMailer = new WpPackPhpMailer(true);

        $originalMailer = $phpMailer->Mailer;
        $transport->configure($phpMailer);

        self::assertSame($originalMailer, $phpMailer->Mailer);
    }

    #[Test]
    public function toStringReturnsExpected(): void
    {
        $transport = new NativeTransport();

        self::assertSame('native://default', (string) $transport);
    }
}
