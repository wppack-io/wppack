<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use WpPack\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use WpPack\Component\Mailer\Transport\Dsn;

final class AzureTransportFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AzureTransportFactory::class)) {
            self::markTestSkipped('AzureMailer Bridge is not available.');
        }
    }

    #[Test]
    #[DataProvider('supportedSchemes')]
    public function supportsExpectedSchemes(string $dsnString, bool $expected): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString($dsnString);

        self::assertSame($expected, $factory->supports($dsn));
    }

    #[Test]
    public function createReturnsAzureApiTransportForDefaultScheme(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('azure://my-resource.communication.azure.com:accesskey123@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(AzureApiTransport::class, $transport);
    }

    #[Test]
    public function createReturnsAzureApiTransportForApiScheme(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('azure+api://my-resource.communication.azure.com:accesskey123@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(AzureApiTransport::class, $transport);
    }

    #[Test]
    public function createThrowsForMissingCredentials(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('azure://default');

        $this->expectException(\WpPack\Component\Mailer\Exception\InvalidArgumentException::class);
        $factory->create($dsn);
    }

    #[Test]
    public function createThrowsForUnsupportedScheme(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('unsupported://default');

        $this->expectException(\WpPack\Component\Mailer\Exception\UnsupportedSchemeException::class);
        $factory->create($dsn);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function supportedSchemes(): iterable
    {
        yield 'azure' => ['azure://endpoint:key@default', true];
        yield 'azure+api' => ['azure+api://endpoint:key@default', true];
        yield 'azure+https' => ['azure+https://endpoint:key@default', false];
        yield 'native' => ['native://default', false];
        yield 'smtp' => ['smtp://localhost', false];
        yield 'ses' => ['ses://default', false];
        yield 'sendgrid' => ['sendgrid://key@default', false];
    }
}
