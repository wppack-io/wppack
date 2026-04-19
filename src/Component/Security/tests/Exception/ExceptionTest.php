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

namespace WPPack\Component\Security\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Exception\AccessDeniedException;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\Security\Exception\InvalidCredentialsException;
use WPPack\Component\Security\Exception\UserNotFoundException;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function authenticationExceptionHasGenericSafeMessage(): void
    {
        $exception = new AuthenticationException('Detailed internal error.');

        self::assertSame('Authentication failed.', $exception->getSafeMessage());
        self::assertSame('Detailed internal error.', $exception->getMessage());
    }

    #[Test]
    public function accessDeniedExceptionHasDefaultMessage(): void
    {
        $exception = new AccessDeniedException();

        self::assertSame('Access Denied.', $exception->getMessage());
    }

    #[Test]
    public function accessDeniedExceptionAcceptsCustomMessage(): void
    {
        $exception = new AccessDeniedException('Custom access denied.');

        self::assertSame('Custom access denied.', $exception->getMessage());
    }

    #[Test]
    public function invalidCredentialsExceptionExtendsAuthenticationException(): void
    {
        $exception = new InvalidCredentialsException();

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame('Invalid credentials.', $exception->getMessage());
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function userNotFoundExceptionTracksIdentifier(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('admin@example.com');

        self::assertSame('admin@example.com', $exception->getUserIdentifier());
        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }
}
