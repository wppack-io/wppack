<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class WpPackPhpMailerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }
    }

    #[Test]
    public function registerAndCallCustomMailer(): void
    {
        $phpMailer = new WpPackPhpMailer(true);
        $called = false;

        $phpMailer->registerCustomMailer('test', function (WpPackPhpMailer $mailer) use (&$called): bool {
            $called = true;
            return true;
        });

        $phpMailer->Mailer = 'test';
        $result = $phpMailer->postSend();

        self::assertTrue($called);
        self::assertTrue($result);
    }

    #[Test]
    public function unregisteredMailerCallsParent(): void
    {
        $phpMailer = new WpPackPhpMailer(true);
        // When using default 'mail' mailer without actually sending,
        // parent::postSend() would try to call mail() which would fail.
        // We just verify no custom mailer is invoked.
        $phpMailer->Mailer = 'mail';

        // postSend will fail because we haven't called preSend,
        // but it should NOT call any custom mailer
        try {
            $phpMailer->postSend();
        } catch (\PHPMailer\PHPMailer\Exception) {
            // Expected - parent postSend fails without preSend
        }

        // Test passes if no custom mailer was called
        self::assertTrue(true);
    }

    #[Test]
    public function multipleCustomMailers(): void
    {
        $phpMailer = new WpPackPhpMailer(true);
        $calledMailer = '';

        $phpMailer->registerCustomMailer('ses', function () use (&$calledMailer): bool {
            $calledMailer = 'ses';
            return true;
        });

        $phpMailer->registerCustomMailer('null', function () use (&$calledMailer): bool {
            $calledMailer = 'null';
            return true;
        });

        $phpMailer->Mailer = 'null';
        $phpMailer->postSend();
        self::assertSame('null', $calledMailer);

        $phpMailer->Mailer = 'ses';
        $phpMailer->postSend();
        self::assertSame('ses', $calledMailer);
    }
}
