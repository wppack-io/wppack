<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\NativeTransport;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\NullTransport;
use WpPack\Component\Mailer\Transport\SmtpTransport;
use WpPack\Component\Mailer\Transport\Transport;

final class TransportTest extends TestCase
{
    #[Test]
    public function fromDsnCreatesNativeTransport(): void
    {
        $transport = Transport::fromDsn('native://default');

        self::assertInstanceOf(NativeTransport::class, $transport);
    }

    #[Test]
    public function fromDsnCreatesNullTransport(): void
    {
        $transport = Transport::fromDsn('null://default');

        self::assertInstanceOf(NullTransport::class, $transport);
    }

    #[Test]
    public function fromDsnCreatesSmtpTransport(): void
    {
        $transport = Transport::fromDsn('smtp://user:pass@smtp.example.com:587');

        self::assertInstanceOf(SmtpTransport::class, $transport);
    }

    #[Test]
    public function fromDsnThrowsForUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);

        Transport::fromDsn('unsupported://default');
    }

    #[Test]
    public function fromStringCreatesTransportWithInjectedFactories(): void
    {
        $transport = new Transport([new NativeTransportFactory()]);

        self::assertInstanceOf(NativeTransport::class, $transport->fromString('native://default'));
    }

    #[Test]
    public function fromStringThrowsForUnsupportedScheme(): void
    {
        $transport = new Transport([new NativeTransportFactory()]);

        $this->expectException(UnsupportedSchemeException::class);

        $transport->fromString('unsupported://default');
    }

    #[Test]
    public function constructorAcceptsIterable(): void
    {
        $factories = (static function (): \Generator {
            yield new NativeTransportFactory();
        })();

        $transport = new Transport($factories);

        self::assertInstanceOf(NativeTransport::class, $transport->fromString('native://default'));
    }
}
