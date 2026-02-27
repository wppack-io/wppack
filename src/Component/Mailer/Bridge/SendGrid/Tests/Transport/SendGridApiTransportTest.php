<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridApiTransport;
use WpPack\Component\Mailer\PhpMailer;

final class SendGridApiTransportTest extends TestCase
{

    #[Test]
    public function getNameReturnsSendgridApi(): void
    {
        $transport = new SendGridApiTransport('SG.test-key');

        self::assertSame('sendgridapi', $transport->getName());
    }

    #[Test]
    public function constructorAcceptsOptionalHttpClient(): void
    {
        $transport = new SendGridApiTransport(
            apiKey: 'SG.test-key',
            httpClient: null,
        );

        self::assertInstanceOf(SendGridApiTransport::class, $transport);
    }
}
