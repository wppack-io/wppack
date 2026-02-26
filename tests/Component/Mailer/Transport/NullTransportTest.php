<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Transport\NullTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class NullTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }
    }

    #[Test]
    public function configureRegistersNullMailer(): void
    {
        $transport = new NullTransport();
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        self::assertSame('null', $phpMailer->Mailer);
    }

    #[Test]
    public function toStringReturnsExpected(): void
    {
        $transport = new NullTransport();

        self::assertSame('null://default', (string) $transport);
    }

    #[Test]
    public function postSendSucceedsWithNoOp(): void
    {
        $transport = new NullTransport();
        $phpMailer = new WpPackPhpMailer(true);
        $transport->configure($phpMailer);

        // postSend should succeed (no-op via custom mailer)
        $result = $phpMailer->postSend();
        self::assertTrue($result);
    }
}
