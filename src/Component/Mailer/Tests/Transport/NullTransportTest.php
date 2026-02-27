<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPMailer\PHPMailer\PHPMailer as BasePhpMailer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\NullTransport;

final class NullTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(BasePhpMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }
    }

    #[Test]
    public function configureRegistersNullMailer(): void
    {
        $transport = new NullTransport();
        $phpMailer = new PhpMailer(true);
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
        $phpMailer = new PhpMailer(true);
        $transport->configure($phpMailer);

        // postSend should succeed (no-op via custom mailer)
        $result = $phpMailer->postSend();
        self::assertTrue($result);
    }
}
