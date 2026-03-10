<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\ErrorHandler\FlattenException;
use WpPack\Component\Debug\ErrorHandler\WpDieException;

final class WpDieExceptionTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $previous = new \LogicException('root cause');
        $exception = new WpDieException(
            message: 'You do not have permission',
            statusCode: 403,
            wpDieTitle: 'Forbidden',
            wpDieArgs: ['response' => 403, 'exit' => false],
            wpErrorCodes: ['forbidden', 'capability_missing'],
            wpErrorData: ['forbidden' => ['required' => 'manage_options']],
            previous: $previous,
        );

        self::assertSame('You do not have permission', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame(403, $exception->getStatusCode());
        self::assertSame('Forbidden', $exception->getWpDieTitle());
        self::assertSame(['response' => 403, 'exit' => false], $exception->getWpDieArgs());
        self::assertSame(['forbidden', 'capability_missing'], $exception->getWpErrorCodes());
        self::assertSame(['forbidden' => ['required' => 'manage_options']], $exception->getWpErrorData());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function flattenExceptionPicksUpStatusCode(): void
    {
        $exception = new WpDieException(
            message: 'Not Found',
            statusCode: 404,
            wpDieTitle: 'Page Not Found',
            wpDieArgs: ['response' => 404],
        );

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame(404, $flat->getStatusCode());
    }

    #[Test]
    public function displayClassShowsWpErrorWithCodes(): void
    {
        $exception = new WpDieException(
            message: 'error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
            wpErrorCodes: ['db_connect_fail'],
        );

        self::assertSame('WP_Error (db_connect_fail)', $exception->getDisplayClass());
    }

    #[Test]
    public function displayClassShowsMultipleWpErrorCodes(): void
    {
        $exception = new WpDieException(
            message: 'error',
            statusCode: 403,
            wpDieTitle: '',
            wpDieArgs: [],
            wpErrorCodes: ['forbidden', 'capability_missing'],
        );

        self::assertSame('WP_Error (forbidden, capability_missing)', $exception->getDisplayClass());
    }

    #[Test]
    public function displayClassShowsWpDieForPlainStringMessage(): void
    {
        $exception = new WpDieException(
            message: 'plain error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
        );

        self::assertSame('wp_die()', $exception->getDisplayClass());
    }

    #[Test]
    public function flattenExceptionUsesDisplayClass(): void
    {
        $exception = new WpDieException(
            message: 'db error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
            wpErrorCodes: ['db_connect_fail'],
        );

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame('WP_Error (db_connect_fail)', $flat->getClass());
    }

    #[Test]
    public function flattenExceptionUsesWpDieDisplayClassForPlainMessage(): void
    {
        $exception = new WpDieException(
            message: 'test',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
        );

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame('wp_die()', $flat->getClass());
    }

    #[Test]
    public function flattenExceptionChainUsesDisplayClass(): void
    {
        $exception = new WpDieException(
            message: 'error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
            wpErrorCodes: ['db_error'],
        );

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame('WP_Error (db_error)', $flat->getChain()[0]['class']);
    }

    #[Test]
    public function defaultsForOptionalParameters(): void
    {
        $exception = new WpDieException(
            message: 'simple error',
            statusCode: 500,
            wpDieTitle: 'Error',
            wpDieArgs: [],
        );

        self::assertSame([], $exception->getWpErrorCodes());
        self::assertSame([], $exception->getWpErrorData());
        self::assertNull($exception->getPrevious());
    }
}
