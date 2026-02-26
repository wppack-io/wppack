<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;
use WpPack\Component\Mailer\Transport\NativeTransport;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\NullTransport;
use WpPack\Component\Mailer\Transport\SmtpTransport;

final class NativeTransportFactoryTest extends TestCase
{
    private NativeTransportFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new NativeTransportFactory();
    }

    #[Test]
    public function supportsNativeScheme(): void
    {
        $dsn = Dsn::fromString('native://default');

        self::assertTrue($this->factory->supports($dsn));
    }

    #[Test]
    public function supportsSmtpScheme(): void
    {
        $dsn = Dsn::fromString('smtp://user:pass@smtp.example.com:587');

        self::assertTrue($this->factory->supports($dsn));
    }

    #[Test]
    public function supportsNullScheme(): void
    {
        $dsn = Dsn::fromString('null://default');

        self::assertTrue($this->factory->supports($dsn));
    }

    #[Test]
    public function doesNotSupportUnknownScheme(): void
    {
        $dsn = Dsn::fromString('ses+api://default');

        self::assertFalse($this->factory->supports($dsn));
    }

    #[Test]
    public function doesNotSupportSendmailScheme(): void
    {
        $dsn = Dsn::fromString('sendmail://default');

        self::assertFalse($this->factory->supports($dsn));
    }

    #[Test]
    public function createReturnsNativeTransport(): void
    {
        $dsn = Dsn::fromString('native://default');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(NativeTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSmtpTransport(): void
    {
        $dsn = Dsn::fromString('smtp://user:pass@smtp.example.com:587');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSmtpTransportWithCorrectConfig(): void
    {
        $dsn = Dsn::fromString('smtp://myuser:mypass@mail.example.com:465?encryption=ssl');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
        self::assertSame('smtp://mail.example.com:465', (string) $transport);
    }

    #[Test]
    public function createReturnsSmtpTransportWithDefaultPort(): void
    {
        $dsn = Dsn::fromString('smtp://user:pass@smtp.example.com');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
        self::assertSame('smtp://smtp.example.com:587', (string) $transport);
    }

    #[Test]
    public function createReturnsNullTransport(): void
    {
        $dsn = Dsn::fromString('null://default');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(NullTransport::class, $transport);
    }

    #[Test]
    public function createThrowsForUnsupportedScheme(): void
    {
        $dsn = Dsn::fromString('ses+api://default');

        $this->expectException(UnsupportedSchemeException::class);

        $this->factory->create($dsn);
    }

    #[Test]
    public function createThrowsForUnknownScheme(): void
    {
        $dsn = Dsn::fromString('custom://default');

        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('The "custom" scheme is not supported.');

        $this->factory->create($dsn);
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(NativeTransportFactory::class);

        self::assertTrue($reflection->isFinal());
    }
}
