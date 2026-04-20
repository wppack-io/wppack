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

namespace WPPack\Component\Database\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Exception\ConnectionException;
use WPPack\Component\Database\Exception\CredentialsExpiredException;
use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Exception\DriverThrottledException;
use WPPack\Component\Database\Exception\DriverTimeoutException;
use WPPack\Component\Database\Exception\ExceptionInterface;
use WPPack\Component\Database\Exception\ParserFailureException;
use WPPack\Component\Database\Exception\QueryException;
use WPPack\Component\Database\Exception\TranslationException;
use WPPack\Component\Database\Exception\UnsupportedFeatureException;
use WPPack\Component\Database\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

#[CoversClass(DriverException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(CredentialsExpiredException::class)]
#[CoversClass(DriverThrottledException::class)]
#[CoversClass(DriverTimeoutException::class)]
#[CoversClass(QueryException::class)]
#[CoversClass(TranslationException::class)]
#[CoversClass(ParserFailureException::class)]
#[CoversClass(UnsupportedFeatureException::class)]
#[CoversClass(UnsupportedSchemeException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    // ── DriverException ────────────────────────────────────────────────

    #[Test]
    public function driverExceptionCarriesMessageAndDriverErrno(): void
    {
        $e = new DriverException(message: 'boom', code: 2006, driverErrno: 1213);

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('boom', $e->getMessage());
        self::assertSame(2006, $e->getCode());
        self::assertSame(1213, $e->driverErrno);
    }

    #[Test]
    public function driverExceptionDefaults(): void
    {
        $e = new DriverException();

        self::assertSame('', $e->getMessage());
        self::assertSame(0, $e->getCode());
        self::assertNull($e->driverErrno);
    }

    #[Test]
    public function driverSubclassesInheritDriverException(): void
    {
        foreach ([
            ConnectionException::class,
            CredentialsExpiredException::class,
            DriverThrottledException::class,
            DriverTimeoutException::class,
        ] as $class) {
            self::assertTrue(is_subclass_of($class, DriverException::class));
            $e = new $class('x');
            self::assertInstanceOf(ExceptionInterface::class, $e);
        }
    }

    // ── QueryException ─────────────────────────────────────────────────

    #[Test]
    public function queryExceptionFormatsMessageWithQuery(): void
    {
        $e = new QueryException('SELECT * FROM posts', 'syntax error');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('SELECT * FROM posts', $e->query);
        self::assertSame('syntax error', $e->dbError);
        self::assertStringContainsString('syntax error', $e->getMessage());
        self::assertStringContainsString('SELECT * FROM posts', $e->getMessage());
    }

    #[Test]
    public function queryExceptionTruncatesLongQueriesInMessage(): void
    {
        $longQuery = 'SELECT ' . str_repeat('x, ', 200) . 'FROM posts';
        $e = new QueryException($longQuery, 'boom');

        self::assertStringContainsString('...', $e->getMessage());
        self::assertSame($longQuery, $e->query, 'full query retained as public prop');
    }

    #[Test]
    public function queryExceptionPreservesPrevious(): void
    {
        $prev = new \RuntimeException('inner');
        $e = new QueryException('x', 'y', $prev);

        self::assertSame($prev, $e->getPrevious());
    }

    // ── TranslationException ───────────────────────────────────────────

    #[Test]
    public function translationExceptionFormatsEngineAndParserErrors(): void
    {
        $e = new TranslationException(
            query: 'SELECT 1',
            engine: 'sqlite',
            parserErrors: ['unexpected STR_TO_DATE', 'unsupported MATCH()'],
        );

        self::assertSame('SELECT 1', $e->query);
        self::assertSame('sqlite', $e->engine);
        self::assertSame(['unexpected STR_TO_DATE', 'unsupported MATCH()'], $e->parserErrors);
        self::assertStringContainsString('sqlite', $e->getMessage());
        self::assertStringContainsString('unexpected STR_TO_DATE', $e->getMessage());
    }

    #[Test]
    public function translationExceptionWithoutParserErrorsUsesDefaultDetail(): void
    {
        $e = new TranslationException('SELECT 1', 'postgresql');

        self::assertStringContainsString('parser returned no statement', $e->getMessage());
    }

    #[Test]
    public function translationExceptionTruncatesLongQueryInMessage(): void
    {
        $longQuery = str_repeat('a', 500);
        $e = new TranslationException($longQuery, 'sqlite');

        self::assertStringContainsString('...', $e->getMessage());
        self::assertSame($longQuery, $e->query);
    }

    #[Test]
    public function parserAndUnsupportedFeatureExtendTranslationException(): void
    {
        self::assertTrue(is_subclass_of(ParserFailureException::class, TranslationException::class));
        self::assertTrue(is_subclass_of(UnsupportedFeatureException::class, TranslationException::class));

        $e = new UnsupportedFeatureException('q', 'sqlite', ['WINDOW FUNCTION']);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }

    // ── UnsupportedSchemeException ─────────────────────────────────────

    #[Test]
    public function unsupportedSchemeIncludesSchemeInMessage(): void
    {
        $dsn = Dsn::fromString('unknown://localhost');
        $e = new UnsupportedSchemeException($dsn);

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertStringContainsString('unknown', $e->getMessage());
        self::assertStringContainsString('not supported', $e->getMessage());
    }
}
