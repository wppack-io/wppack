<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Exception\AccessDeniedException;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Security\Exception\InvalidCredentialsException;
use WpPack\Component\Security\Exception\UserNotFoundException;

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
