<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Bridge\Amazon\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Transport\Dsn;

final class SesTransportFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory::class)) {
            self::markTestSkipped('AmazonMailer Bridge is not available.');
        }
    }

    #[Test]
    #[DataProvider('supportedSchemes')]
    public function supportsExpectedSchemes(string $dsnString, bool $expected): void
    {
        $factory = new \WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory();
        $dsn = Dsn::fromString($dsnString);

        self::assertSame($expected, $factory->supports($dsn));
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
    }
}
