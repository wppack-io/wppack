<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Transport\Dsn;

final class DsnTest extends TestCase
{
    #[Test]
    public function parseNativeDsn(): void
    {
        $dsn = Dsn::fromString('native://default');

        self::assertSame('native', $dsn->getScheme());
        self::assertSame('default', $dsn->getHost());
        self::assertNull($dsn->getUser());
        self::assertNull($dsn->getPassword());
        self::assertNull($dsn->getPort());
    }

    #[Test]
    public function parseSmtpDsn(): void
    {
        $dsn = Dsn::fromString('smtp://user:pass@smtp.example.com:587?encryption=tls');

        self::assertSame('smtp', $dsn->getScheme());
        self::assertSame('smtp.example.com', $dsn->getHost());
        self::assertSame('user', $dsn->getUser());
        self::assertSame('pass', $dsn->getPassword());
        self::assertSame(587, $dsn->getPort());
        self::assertSame('tls', $dsn->getOption('encryption'));
    }

    #[Test]
    public function parseSesDsn(): void
    {
        $dsn = Dsn::fromString('ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

        self::assertSame('ses+api', $dsn->getScheme());
        self::assertSame('default', $dsn->getHost());
        self::assertSame('ACCESS_KEY', $dsn->getUser());
        self::assertSame('SECRET_KEY', $dsn->getPassword());
        self::assertSame('ap-northeast-1', $dsn->getOption('region'));
    }

    #[Test]
    public function optionWithDefault(): void
    {
        $dsn = Dsn::fromString('native://default');

        self::assertSame('us-east-1', $dsn->getOption('region', 'us-east-1'));
        self::assertNull($dsn->getOption('nonexistent'));
    }

    #[Test]
    public function toStringMasksPassword(): void
    {
        $dsn = Dsn::fromString('ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

        self::assertSame('ses+api://ACCESS_KEY:****@default', (string) $dsn);
        self::assertStringNotContainsString('SECRET_KEY', (string) $dsn);
    }

    #[Test]
    public function toStringWithoutPassword(): void
    {
        $dsn = Dsn::fromString('native://default');

        self::assertSame('native://default', (string) $dsn);
    }

    #[Test]
    public function invalidDsnThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('not-a-valid-dsn');
    }

    #[Test]
    public function urlEncodedCredentials(): void
    {
        $dsn = Dsn::fromString('smtp://user%40domain:p%40ss%23word@smtp.example.com:587');

        self::assertSame('user@domain', $dsn->getUser());
        self::assertSame('p@ss#word', $dsn->getPassword());
    }
}
