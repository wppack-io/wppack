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

namespace WPPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\ErrorHandler\FlattenException;
use WPPack\Component\Debug\ErrorHandler\WpErrorException;

final class WpErrorExceptionTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $previous = new \LogicException('root');
        $exception = new WpErrorException(
            message: 'Something went wrong',
            wpErrorCodes: ['db_error', 'timeout'],
            wpErrorData: ['db_error' => ['table' => 'wp_posts']],
            previous: $previous,
        );

        self::assertSame('Something went wrong', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame(['db_error', 'timeout'], $exception->getWpErrorCodes());
        self::assertSame(['db_error' => ['table' => 'wp_posts']], $exception->getWpErrorData());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function displayClassShowsErrorCodes(): void
    {
        $exception = new WpErrorException('error', ['forbidden', 'capability_missing']);

        self::assertSame('WP_Error (forbidden, capability_missing)', $exception->getDisplayClass());
    }

    #[Test]
    public function displayClassShowsSingleCode(): void
    {
        $exception = new WpErrorException('error', ['db_connect_fail']);

        self::assertSame('WP_Error (db_connect_fail)', $exception->getDisplayClass());
    }

    #[Test]
    public function displayClassShowsWpErrorWithoutCodes(): void
    {
        $exception = new WpErrorException('error');

        self::assertSame('WP_Error', $exception->getDisplayClass());
    }

    #[Test]
    public function defaultsForOptionalParameters(): void
    {
        $exception = new WpErrorException('error');

        self::assertSame([], $exception->getWpErrorCodes());
        self::assertSame([], $exception->getWpErrorData());
        self::assertNull($exception->getPrevious());
    }

    #[Test]
    public function flattenExceptionUsesDisplayClass(): void
    {
        $exception = new WpErrorException('db error', ['db_connect_fail']);
        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame('WP_Error (db_connect_fail)', $flat->getClass());
    }
}
