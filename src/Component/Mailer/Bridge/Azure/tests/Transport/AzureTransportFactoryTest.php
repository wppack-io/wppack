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

namespace WPPack\Component\Mailer\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use WPPack\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;

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
        $dsn = Dsn::fromString('azure://my-resource:accesskey123@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(AzureApiTransport::class, $transport);
    }

    #[Test]
    public function createReturnsAzureApiTransportForApiScheme(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('azure+api://my-resource:accesskey123@default');

        $transport = $factory->create($dsn);

        self::assertInstanceOf(AzureApiTransport::class, $transport);
    }

    #[Test]
    public function createThrowsForMissingCredentials(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('azure://default');

        $this->expectException(\WPPack\Component\Mailer\Exception\InvalidArgumentException::class);
        $factory->create($dsn);
    }

    #[Test]
    public function createThrowsForUnsupportedScheme(): void
    {
        $factory = new AzureTransportFactory();
        $dsn = Dsn::fromString('unsupported://default');

        $this->expectException(\WPPack\Component\Mailer\Exception\UnsupportedSchemeException::class);
        $factory->create($dsn);
    }

    #[Test]
    public function definitionsReturnsOneDefinition(): void
    {
        $definitions = AzureTransportFactory::definitions();

        self::assertCount(1, $definitions);
        self::assertSame('azure+api', $definitions[0]->scheme);
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
