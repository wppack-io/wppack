<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridApiTransport;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridSmtpTransport;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridTransportFactory;
use WpPack\Component\Mailer\Transport\Dsn;

final class SendGridTransportFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SendGridTransportFactory::class)) {
            self::markTestSkipped('SendGridMailer Bridge is not available.');
        }
    }

    #[Test]
    #[DataProvider('supportedSchemes')]
    public function supportsExpectedSchemes(string $dsnString, bool $expected): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString($dsnString);

        self::assertSame($expected, $factory->supports($dsn));
    }

    #[Test]
    public function createReturnsApiTransportForDefaultScheme(): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString('sendgrid://SG.xxxx@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SendGridApiTransport::class, $transport);
    }

    #[Test]
    public function createReturnsApiTransportForApiScheme(): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString('sendgrid+api://SG.xxxx@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SendGridApiTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSmtpTransportForSmtpScheme(): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString('sendgrid+smtp://apikey:SG.xxxx@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SendGridSmtpTransport::class, $transport);
    }

    #[Test]
    public function createThrowsForMissingApiKey(): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString('sendgrid://default');

        $this->expectException(\WpPack\Component\Mailer\Exception\InvalidArgumentException::class);
        $factory->create($dsn);
    }

    #[Test]
    public function createThrowsForMissingSmtpPassword(): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString('sendgrid+smtp://apikey@default');

        $this->expectException(\WpPack\Component\Mailer\Exception\InvalidArgumentException::class);
        $factory->create($dsn);
    }

    #[Test]
    public function createThrowsForUnsupportedScheme(): void
    {
        $factory = new SendGridTransportFactory();
        $dsn = Dsn::fromString('unsupported://default');

        $this->expectException(\WpPack\Component\Mailer\Exception\UnsupportedSchemeException::class);
        $factory->create($dsn);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function supportedSchemes(): iterable
    {
        yield 'sendgrid' => ['sendgrid://key@default', true];
        yield 'sendgrid+api' => ['sendgrid+api://key@default', true];
        yield 'sendgrid+smtp' => ['sendgrid+smtp://apikey:key@default', true];
        yield 'sendgrid+smtps' => ['sendgrid+smtps://apikey:key@default', true];
        yield 'sendgrid+https' => ['sendgrid+https://key@default', false];
        yield 'native' => ['native://default', false];
        yield 'smtp' => ['smtp://localhost', false];
        yield 'ses' => ['ses://default', false];
        yield 'azure' => ['azure://endpoint:key@default', false];
    }
}
