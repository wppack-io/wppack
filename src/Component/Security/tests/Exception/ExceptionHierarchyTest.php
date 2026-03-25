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

namespace WpPack\Component\Security\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Exception\AccessDeniedException;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Security\Exception\ExceptionInterface;
use WpPack\Component\Security\Exception\InvalidCredentialsException;
use WpPack\Component\Security\Exception\UserNotFoundException;

final class ExceptionHierarchyTest extends TestCase
{
    // ---------------------------------------------------------------
    // ExceptionInterface hierarchy
    // ---------------------------------------------------------------

    #[Test]
    public function authenticationExceptionImplementsExceptionInterface(): void
    {
        $exception = new AuthenticationException();

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\Throwable::class, $exception);
    }

    #[Test]
    public function invalidCredentialsExceptionImplementsExceptionInterface(): void
    {
        $exception = new InvalidCredentialsException();

        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function userNotFoundExceptionImplementsExceptionInterface(): void
    {
        $exception = new UserNotFoundException();

        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function accessDeniedExceptionImplementsExceptionInterface(): void
    {
        $exception = new AccessDeniedException();

        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function allExceptionsAreInstanceOfThrowable(): void
    {
        self::assertInstanceOf(\Throwable::class, new AuthenticationException());
        self::assertInstanceOf(\Throwable::class, new InvalidCredentialsException());
        self::assertInstanceOf(\Throwable::class, new UserNotFoundException());
        self::assertInstanceOf(\Throwable::class, new AccessDeniedException());
    }

    // ---------------------------------------------------------------
    // AuthenticationException
    // ---------------------------------------------------------------

    #[Test]
    public function authenticationExceptionExtendsRuntimeException(): void
    {
        $exception = new AuthenticationException();

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function authenticationExceptionSafeMessageIsGeneric(): void
    {
        $exception = new AuthenticationException('Sensitive internal error: DB connection refused');

        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function authenticationExceptionMessagePreservedInternally(): void
    {
        $internal = 'User table corrupted for user ID 42';
        $exception = new AuthenticationException($internal);

        self::assertSame($internal, $exception->getMessage());
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function authenticationExceptionDefaultMessageIsEmpty(): void
    {
        $exception = new AuthenticationException();

        self::assertSame('', $exception->getMessage());
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function authenticationExceptionSafeMessageNeverRevealsInternalDetails(): void
    {
        $sensitiveMessages = [
            'SQLSTATE[HY000] [2002] Connection refused',
            'User admin@secret-domain.com not found in LDAP',
            'Redis connection timeout after 5s',
            'Internal password hash mismatch for user_id=42',
        ];

        foreach ($sensitiveMessages as $message) {
            $exception = new AuthenticationException($message);
            self::assertSame(
                'Authentication failed.',
                $exception->getSafeMessage(),
                \sprintf('Safe message leaked internal details for: %s', $message),
            );
        }
    }

    #[Test]
    public function authenticationExceptionSupportsErrorCode(): void
    {
        $exception = new AuthenticationException('Test', 401);

        self::assertSame(401, $exception->getCode());
    }

    #[Test]
    public function authenticationExceptionSupportsPreviousException(): void
    {
        $previous = new \RuntimeException('DB error');
        $exception = new AuthenticationException('Auth failed', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function authenticationExceptionCanBeCaught(): void
    {
        $caught = false;

        try {
            throw new AuthenticationException('test');
        } catch (ExceptionInterface) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    // ---------------------------------------------------------------
    // InvalidCredentialsException
    // ---------------------------------------------------------------

    #[Test]
    public function invalidCredentialsExceptionExtendsAuthenticationException(): void
    {
        $exception = new InvalidCredentialsException();

        self::assertInstanceOf(AuthenticationException::class, $exception);
    }

    #[Test]
    public function invalidCredentialsExceptionHasDefaultMessage(): void
    {
        $exception = new InvalidCredentialsException();

        self::assertSame('Invalid credentials.', $exception->getMessage());
    }

    #[Test]
    public function invalidCredentialsExceptionInheritsSafeMessage(): void
    {
        $exception = new InvalidCredentialsException();

        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function invalidCredentialsExceptionSafeMessageDoesNotRevealCredentialDetails(): void
    {
        $exception = new InvalidCredentialsException('Password "s3cr3t!" does not match hash');

        // getSafeMessage() must never expose credential details
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
        self::assertSame('Password "s3cr3t!" does not match hash', $exception->getMessage());
    }

    #[Test]
    public function invalidCredentialsExceptionAcceptsCustomMessage(): void
    {
        $exception = new InvalidCredentialsException('Custom internal message');

        self::assertSame('Custom internal message', $exception->getMessage());
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function invalidCredentialsExceptionSupportsErrorCode(): void
    {
        $exception = new InvalidCredentialsException('bad creds', 403);

        self::assertSame(403, $exception->getCode());
    }

    #[Test]
    public function invalidCredentialsExceptionSupportsPreviousException(): void
    {
        $previous = new \InvalidArgumentException('hash mismatch');
        $exception = new InvalidCredentialsException('Invalid credentials.', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function invalidCredentialsExceptionCanBeCaughtAsAuthenticationException(): void
    {
        $caught = false;

        try {
            throw new InvalidCredentialsException();
        } catch (AuthenticationException) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    #[Test]
    public function invalidCredentialsExceptionCanBeCaughtAsExceptionInterface(): void
    {
        $caught = false;

        try {
            throw new InvalidCredentialsException();
        } catch (ExceptionInterface) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    // ---------------------------------------------------------------
    // UserNotFoundException
    // ---------------------------------------------------------------

    #[Test]
    public function userNotFoundExceptionExtendsAuthenticationException(): void
    {
        $exception = new UserNotFoundException();

        self::assertInstanceOf(AuthenticationException::class, $exception);
    }

    #[Test]
    public function userNotFoundExceptionInheritsSafeMessage(): void
    {
        $exception = new UserNotFoundException();

        self::assertSame('Authentication failed.', $exception->getSafeMessage());
    }

    #[Test]
    public function userNotFoundExceptionUserIdentifierIsEmptyByDefault(): void
    {
        $exception = new UserNotFoundException();

        self::assertSame('', $exception->getUserIdentifier());
    }

    #[Test]
    public function userNotFoundExceptionSetsAndGetsUserIdentifier(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('admin@example.com');

        self::assertSame('admin@example.com', $exception->getUserIdentifier());
    }

    #[Test]
    public function userNotFoundExceptionUserIdentifierCanBeOverwritten(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('first@example.com');
        $exception->setUserIdentifier('second@example.com');

        self::assertSame('second@example.com', $exception->getUserIdentifier());
    }

    #[Test]
    public function userNotFoundExceptionUserIdentifierAcceptsUsernameFormat(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('admin');

        self::assertSame('admin', $exception->getUserIdentifier());
    }

    #[Test]
    public function userNotFoundExceptionUserIdentifierAcceptsEmailFormat(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('user@domain.co.jp');

        self::assertSame('user@domain.co.jp', $exception->getUserIdentifier());
    }

    #[Test]
    public function userNotFoundExceptionSafeMessageDoesNotRevealUserIdentifier(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('secret_admin@internal.corp');

        // Safe message must never reveal the user identifier to prevent user enumeration
        self::assertSame('Authentication failed.', $exception->getSafeMessage());
        self::assertStringNotContainsString('secret_admin', $exception->getSafeMessage());
    }

    #[Test]
    public function userNotFoundExceptionCanBeCaughtAsAuthenticationException(): void
    {
        $caught = false;

        try {
            throw new UserNotFoundException();
        } catch (AuthenticationException) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    #[Test]
    public function userNotFoundExceptionCanBeCaughtAsExceptionInterface(): void
    {
        $caught = false;

        try {
            throw new UserNotFoundException();
        } catch (ExceptionInterface) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    // ---------------------------------------------------------------
    // AccessDeniedException
    // ---------------------------------------------------------------

    #[Test]
    public function accessDeniedExceptionExtendsRuntimeException(): void
    {
        $exception = new AccessDeniedException();

        self::assertInstanceOf(\RuntimeException::class, $exception);
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
        $exception = new AccessDeniedException('You do not have permission to edit this post.');

        self::assertSame('You do not have permission to edit this post.', $exception->getMessage());
    }

    #[Test]
    public function accessDeniedExceptionSupportsErrorCode(): void
    {
        $exception = new AccessDeniedException('Forbidden', 403);

        self::assertSame(403, $exception->getCode());
    }

    #[Test]
    public function accessDeniedExceptionSupportsPreviousException(): void
    {
        $previous = new \RuntimeException('Underlying authorization error');
        $exception = new AccessDeniedException('Access Denied.', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function accessDeniedExceptionDoesNotExtendAuthenticationException(): void
    {
        $exception = new AccessDeniedException();

        self::assertNotInstanceOf(AuthenticationException::class, $exception);
    }

    #[Test]
    public function accessDeniedExceptionCanBeCaughtAsExceptionInterface(): void
    {
        $caught = false;

        try {
            throw new AccessDeniedException();
        } catch (ExceptionInterface) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    #[Test]
    public function accessDeniedExceptionCanBeCaughtAsRuntimeException(): void
    {
        $caught = false;

        try {
            throw new AccessDeniedException('No access');
        } catch (\RuntimeException) {
            $caught = true;
        }

        self::assertTrue($caught);
    }

    // ---------------------------------------------------------------
    // Cross-exception catch hierarchy
    // ---------------------------------------------------------------

    #[Test]
    public function exceptionInterfaceCatchesAllSecurityExceptions(): void
    {
        $exceptions = [
            new AuthenticationException('auth'),
            new InvalidCredentialsException('creds'),
            new UserNotFoundException(),
            new AccessDeniedException('denied'),
        ];

        foreach ($exceptions as $exception) {
            $caught = false;

            try {
                throw $exception;
            } catch (ExceptionInterface) {
                $caught = true;
            }

            self::assertTrue(
                $caught,
                \sprintf('ExceptionInterface did not catch %s', $exception::class),
            );
        }
    }

    #[Test]
    public function authenticationExceptionCatchesBothSubclasses(): void
    {
        $subclasses = [
            new InvalidCredentialsException(),
            new UserNotFoundException(),
        ];

        foreach ($subclasses as $exception) {
            $caught = false;

            try {
                throw $exception;
            } catch (AuthenticationException) {
                $caught = true;
            }

            self::assertTrue(
                $caught,
                \sprintf('AuthenticationException did not catch %s', $exception::class),
            );
        }
    }

    #[Test]
    public function accessDeniedExceptionIsNotCaughtByAuthenticationException(): void
    {
        $caughtByAuth = false;
        $caughtByRuntime = false;

        try {
            throw new AccessDeniedException();
        } catch (AuthenticationException) {
            $caughtByAuth = true;
        } catch (\RuntimeException) {
            $caughtByRuntime = true;
        }

        self::assertFalse($caughtByAuth);
        self::assertTrue($caughtByRuntime);
    }
}
