<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Amazon\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WpPack\Component\Mailer\Transport\Dsn;

final class SesTransportFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SesTransportFactory::class)) {
            self::markTestSkipped('AmazonMailer Bridge is not available.');
        }
    }

    #[Test]
    #[DataProvider('supportedSchemes')]
    public function supportsExpectedSchemes(string $dsnString, bool $expected): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString($dsnString);

        self::assertSame($expected, $factory->supports($dsn));
    }

    #[Test]
    public function createReturnsSesTransportForDefaultScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses://AKID:SECRET@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertSame('ses://', (string) $transport);
    }

    #[Test]
    public function createReturnsSesApiTransportForApiScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+api://AKID:SECRET@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertSame('ses+api://', (string) $transport);
    }

    #[Test]
    public function createReturnsSesSmtpTransportForSmtpScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+smtp://user:pass@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertSame('ses+smtp://', (string) $transport);
    }

    #[Test]
    public function createThrowsForUnsupportedScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('unsupported://default');

        $this->expectException(\WpPack\Component\Mailer\Exception\UnsupportedSchemeException::class);
        $factory->create($dsn);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function supportedSchemes(): iterable
    {
        yield 'ses' => ['ses://default', true];
        yield 'ses+api' => ['ses+api://default', true];
        yield 'ses+https' => ['ses+https://default', true];
        yield 'ses+smtp' => ['ses+smtp://user:pass@default', true];
        yield 'ses+smtps' => ['ses+smtps://user:pass@default', true];
        yield 'native' => ['native://default', false];
        yield 'smtp' => ['smtp://localhost', false];
        yield 'azure' => ['azure://endpoint:key@default', false];
        yield 'sendgrid' => ['sendgrid://key@default', false];
    }
}
