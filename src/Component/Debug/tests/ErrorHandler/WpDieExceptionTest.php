<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\ErrorHandler\FlattenException;
use WpPack\Component\Debug\ErrorHandler\WpDieException;
use WpPack\Component\Debug\ErrorHandler\WpErrorException;

final class WpDieExceptionTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $previous = new WpErrorException('root cause', ['forbidden'], ['forbidden' => ['required' => 'manage_options']]);
        $exception = new WpDieException(
            message: 'You do not have permission',
            statusCode: 403,
            wpDieTitle: 'Forbidden',
            wpDieArgs: ['response' => 403, 'exit' => false],
            previous: $previous,
        );

        self::assertSame('You do not have permission', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame(403, $exception->getStatusCode());
        self::assertSame('Forbidden', $exception->getWpDieTitle());
        self::assertSame(['response' => 403, 'exit' => false], $exception->getWpDieArgs());
        self::assertSame($previous, $exception->getPrevious());
        self::assertInstanceOf(WpErrorException::class, $exception->getPrevious());
        self::assertSame(['forbidden'], $previous->getWpErrorCodes());
        self::assertSame(['forbidden' => ['required' => 'manage_options']], $previous->getWpErrorData());
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
    public function displayClassAlwaysReturnsWpDie(): void
    {
        $exception = new WpDieException(
            message: 'error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
        );

        self::assertSame('wp_die()', $exception->getDisplayClass());
    }

    #[Test]
    public function displayClassReturnsWpDieEvenWithWpErrorPrevious(): void
    {
        $previous = new WpErrorException('db error', ['db_connect_fail']);
        $exception = new WpDieException(
            message: 'db error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
            previous: $previous,
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
            previous: new WpErrorException('db error', ['db_connect_fail']),
        );

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame('wp_die()', $flat->getClass());
    }

    #[Test]
    public function flattenExceptionChainIncludesWpErrorException(): void
    {
        $wpErrorException = new WpErrorException('db error', ['db_connect_fail']);
        $exception = new WpDieException(
            message: 'db error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
            previous: $wpErrorException,
        );

        $flat = FlattenException::createFromThrowable($exception);
        $chain = $flat->getChain();

        self::assertCount(2, $chain);
        self::assertSame('wp_die()', $chain[0]['class']);
        self::assertSame('WP_Error (db_connect_fail)', $chain[1]['class']);
    }

    #[Test]
    public function flattenExceptionChainWithMultipleWpErrorCodes(): void
    {
        $wpErrorException = new WpErrorException('error', ['forbidden', 'capability_missing']);
        $exception = new WpDieException(
            message: 'error',
            statusCode: 403,
            wpDieTitle: '',
            wpDieArgs: [],
            previous: $wpErrorException,
        );

        $flat = FlattenException::createFromThrowable($exception);
        $chain = $flat->getChain();

        self::assertCount(2, $chain);
        self::assertSame('WP_Error (forbidden, capability_missing)', $chain[1]['class']);
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

        self::assertNull($exception->getPrevious());
    }

    #[Test]
    public function flattenExceptionChainHasSingleEntryWithoutPrevious(): void
    {
        $exception = new WpDieException(
            message: 'plain error',
            statusCode: 500,
            wpDieTitle: '',
            wpDieArgs: [],
        );

        $flat = FlattenException::createFromThrowable($exception);

        self::assertCount(1, $flat->getChain());
        self::assertSame('wp_die()', $flat->getChain()[0]['class']);
    }
}
