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

namespace WpPack\Component\Mailer\Bridge\Amazon\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesSmtpTransport;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class SesSmtpTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsSesSmtp(): void
    {
        $transport = new SesSmtpTransport('user', 'pass');

        self::assertSame('ses+smtp', $transport->getName());
    }

    #[Test]
    public function sendConfiguresDefaultRegionHost(): void
    {
        $transport = new SesSmtpTransport('user', 'pass');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame('email-smtp.us-east-1.amazonaws.com', $phpMailer->Host);
    }

    #[Test]
    public function sendConfiguresCustomRegionHost(): void
    {
        $transport = new SesSmtpTransport('user', 'pass', 'eu-west-1');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame('email-smtp.eu-west-1.amazonaws.com', $phpMailer->Host);
    }

    #[Test]
    public function sendSetsDefaultPortAndEncryption(): void
    {
        $transport = new SesSmtpTransport('user', 'pass');
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
        $transport = new SesSmtpTransport('user', 'pass', 'us-east-1', 'ssl', 465);
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertSame(465, $phpMailer->Port);
        self::assertSame('ssl', $phpMailer->SMTPSecure);
    }

    #[Test]
    public function sendSetsAuthCredentials(): void
    {
        $transport = new SesSmtpTransport('ses-user', 'ses-secret');
        $phpMailer = new PhpMailer(true);

        try {
            $transport->send($phpMailer);
        } catch (TransportException) {
        }

        self::assertTrue($phpMailer->SMTPAuth);
        self::assertSame('ses-user', $phpMailer->Username);
        self::assertSame('ses-secret', $phpMailer->Password);
    }
}
