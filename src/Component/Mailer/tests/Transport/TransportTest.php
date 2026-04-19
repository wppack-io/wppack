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
use WPPack\Component\Mailer\Exception\InvalidArgumentException;
use WPPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WPPack\Component\Mailer\Transport\Dsn;
use WPPack\Component\Mailer\Transport\NativeTransport;
use WPPack\Component\Mailer\Transport\NativeTransportFactory;
use WPPack\Component\Mailer\Transport\NullTransport;
use WPPack\Component\Mailer\Transport\SmtpTransport;
use WPPack\Component\Mailer\Transport\Transport;

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

    #[Test]
    public function createWithDsnObjectDirectly(): void
    {
        $transport = new Transport([new NativeTransportFactory()]);
        $dsn = Dsn::fromString('native://default');

        self::assertInstanceOf(NativeTransport::class, $transport->create($dsn));
    }

    #[Test]
    public function nativeTransportGetNameReturnsMail(): void
    {
        $transport = new NativeTransport();

        self::assertSame('mail', $transport->getName());
    }

    #[Test]
    public function smtpTransportGetNameReturnsSmtp(): void
    {
        $transport = new SmtpTransport('smtp.example.com');

        self::assertSame('smtp', $transport->getName());
    }

    #[Test]
    public function dsnFromStringThrowsOnMissingHost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('smtp://');
    }

    #[Test]
    public function dsnFromStringThrowsOnInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('://');
    }
}
