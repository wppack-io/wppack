<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridSmtpTransport;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class SendGridSmtpTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsSendgridSmtp(): void
    {
        $transport = new SendGridSmtpTransport('SG.test-key');

        self::assertSame('sendgrid+smtp', $transport->getName());
    }

    #[Test]
    public function sendConfiguresHost(): void
    {
        $transport = new SendGridSmtpTransport('SG.test-key');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame('smtp.sendgrid.net', $phpMailer->Host);
    }

    #[Test]
    public function sendSetsUsernameToApikey(): void
    {
        $transport = new SendGridSmtpTransport('SG.test-key');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertTrue($phpMailer->SMTPAuth);
        self::assertSame('apikey', $phpMailer->Username);
        self::assertSame('SG.test-key', $phpMailer->Password);
    }

    #[Test]
    public function sendSetsDefaultPortAndEncryption(): void
    {
        $transport = new SendGridSmtpTransport('SG.test-key');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame(587, $phpMailer->Port);
        self::assertSame('tls', $phpMailer->SMTPSecure);
    }

    #[Test]
    public function sendSetsCustomPortAndEncryption(): void
    {
        $transport = new SendGridSmtpTransport('SG.test-key', 'ssl', 465);
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame(465, $phpMailer->Port);
        self::assertSame('ssl', $phpMailer->SMTPSecure);
    }
}
