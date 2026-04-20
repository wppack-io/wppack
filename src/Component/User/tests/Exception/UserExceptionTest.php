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

namespace WPPack\Component\User\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\User\Exception\ExceptionInterface;
use WPPack\Component\User\Exception\UserException;

#[CoversClass(UserException::class)]
final class UserExceptionTest extends TestCase
{
    #[Test]
    public function defaultConstructorProducesEmptyWpErrorMetadata(): void
    {
        $e = new UserException('boom');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('boom', $e->getMessage());
        self::assertSame([], $e->getWpErrorCodes());
        self::assertSame([], $e->getWpErrorMessages());
    }

    #[Test]
    public function previousExceptionIsPreserved(): void
    {
        $previous = new \LogicException('cause');
        $e = new UserException('effect', previous: $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function fromWpErrorCopiesCodesAndMessages(): void
    {
        $error = new \WP_Error();
        $error->add('existing_user_login', 'Sorry, that username already exists!');
        $error->add('existing_user_email', 'Sorry, that email address is already used!');

        $e = UserException::fromWpError($error);

        self::assertStringContainsString('username', $e->getMessage());
        self::assertSame(['existing_user_login', 'existing_user_email'], $e->getWpErrorCodes());
        self::assertCount(2, $e->getWpErrorMessages());
    }

    #[Test]
    public function fromWpErrorEmptyErrorStillProducesException(): void
    {
        $e = UserException::fromWpError(new \WP_Error());

        self::assertSame('', $e->getMessage());
        self::assertSame([], $e->getWpErrorCodes());
    }
}
