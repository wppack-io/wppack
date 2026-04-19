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

namespace WPPack\Component\Mailer\Bridge\Amazon\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport;
use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport;
use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesSmtpTransport;
use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WPPack\Component\Dsn\Dsn;

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
    public function createReturnsSesApiTransportForDefaultScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses://AKID:SECRET@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SesApiTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSesApiTransportForApiScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+api://AKID:SECRET@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SesApiTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSesHttpTransportForHttpsScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+https://AKID:SECRET@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SesHttpTransport::class, $transport);
    }

    #[Test]
    public function createReturnsSesSmtpTransportForSmtpScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+smtp://user:pass@default?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SesSmtpTransport::class, $transport);
    }

    #[Test]
    public function createThrowsForUnsupportedScheme(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('unsupported://default');

        $this->expectException(\WPPack\Component\Mailer\Exception\UnsupportedSchemeException::class);
        $factory->create($dsn);
    }

    #[Test]
    public function createWithSessionToken(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+api://AKID:SECRET@default?region=us-east-1&session_token=TOKEN');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SesApiTransport::class, $transport);
    }

    #[Test]
    public function createWithCustomEndpoint(): void
    {
        $factory = new SesTransportFactory();
        $dsn = Dsn::fromString('ses+api://AKID:SECRET@custom-host.example.com:8443?region=us-east-1');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(SesApiTransport::class, $transport);
    }

    #[Test]
    public function definitionsReturnsThreeDefinitions(): void
    {
        $definitions = SesTransportFactory::definitions();

        self::assertCount(3, $definitions);

        $schemes = array_map(static fn($d) => $d->scheme, $definitions);
        self::assertContains('ses+api', $schemes);
        self::assertContains('ses+https', $schemes);
        self::assertContains('ses+smtp', $schemes);
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
