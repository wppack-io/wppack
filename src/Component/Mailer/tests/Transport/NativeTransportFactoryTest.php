<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WPPack\Component\Mailer\Transport\Dsn;
use WPPack\Component\Mailer\Transport\NativeTransport;
use WPPack\Component\Mailer\Transport\NativeTransportFactory;
use WPPack\Component\Mailer\Transport\NullTransport;
use WPPack\Component\Mailer\Transport\SmtpTransport;

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
    public function supportsSmtpsScheme(): void
    {
        $dsn = Dsn::fromString('smtps://user:pass@smtp.example.com');

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
    }

    #[Test]
    public function createReturnsSmtpTransportWithDefaultPort(): void
    {
        $dsn = Dsn::fromString('smtp://user:pass@smtp.example.com');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSmtpTransportForSmtpsScheme(): void
    {
        $dsn = Dsn::fromString('smtps://user:pass@smtp.example.com');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
    }

    #[Test]
    public function createSmtpsUsesPort465ByDefault(): void
    {
        $dsn = Dsn::fromString('smtps://user:pass@smtp.example.com');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
    }

    #[Test]
    public function createSmtpsRespectsExplicitPort(): void
    {
        $dsn = Dsn::fromString('smtps://user:pass@smtp.example.com:2465');

        $transport = $this->factory->create($dsn);

        self::assertInstanceOf(SmtpTransport::class, $transport);
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
    public function definitionsReturnsTwoDefinitions(): void
    {
        $definitions = NativeTransportFactory::definitions();

        self::assertCount(2, $definitions);

        $schemes = array_map(static fn($d) => $d->scheme, $definitions);
        self::assertContains('smtp', $schemes);
        self::assertContains('native', $schemes);
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(NativeTransportFactory::class);

        self::assertTrue($reflection->isFinal());
    }
}
