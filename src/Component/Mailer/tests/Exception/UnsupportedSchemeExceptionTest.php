<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;

final class UnsupportedSchemeExceptionTest extends TestCase
{
    #[Test]
    public function messageContainsScheme(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringContainsString('not supported', $exception->getMessage());
    }

    #[Test]
    public function messageContainsNameAndSupportedSchemes(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn, 'TestTransport', ['smtp', 'smtps']);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringContainsString('"TestTransport"', $exception->getMessage());
        self::assertStringContainsString('smtp, smtps', $exception->getMessage());
    }

    #[Test]
    public function messageWithoutNameDoesNotAppendSupported(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn, null, ['smtp']);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringNotContainsString('smtp', $exception->getMessage());
    }

    #[Test]
    public function messageWithNameButEmptySupportedDoesNotAppend(): void
    {
        $dsn = Dsn::fromString('foobar://default');
        $exception = new UnsupportedSchemeException($dsn, 'TestTransport', []);

        self::assertStringContainsString('"foobar"', $exception->getMessage());
        self::assertStringNotContainsString('TestTransport', $exception->getMessage());
    }
}
