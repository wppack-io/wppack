<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Exception\AccessDeniedException;
use WpPack\Component\Security\Security;

final class SecurityTest extends TestCase
{
    #[Test]
    public function isGrantedDelegatesToChecker(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return $attribute === 'allowed';
            }
        };

        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        self::assertTrue($security->isGranted('allowed'));
        self::assertFalse($security->isGranted('denied'));
    }

    #[Test]
    public function getUserReturnsNullWhenNoToken(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        self::assertNull($security->getUser());
    }

    #[Test]
    public function getUserReturnsNullWhenNotAuthenticated(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $authManager = $this->createAuthManager(new NullToken());
        $security = new Security($checker, $authManager);

        self::assertNull($security->getUser());
    }

    #[Test]
    public function getUserReturnsUserWhenAuthenticated(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $token = new \WpPack\Component\Security\Authentication\Token\PostAuthenticationToken($user, ['subscriber']);
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $authManager = $this->createAuthManager($token);
        $security = new Security($checker, $authManager);

        self::assertSame($user, $security->getUser());
    }

    #[Test]
    public function denyAccessUnlessGrantedThrowsAccessDeniedException(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return false;
            }
        };

        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied.');

        $security->denyAccessUnlessGranted('edit_posts');
    }

    #[Test]
    public function denyAccessUnlessGrantedPassesWhenGranted(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return true;
            }
        };

        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        $security->denyAccessUnlessGranted('edit_posts');

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function denyAccessUnlessGrantedUsesCustomMessage(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return false;
            }
        };

        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You shall not pass!');

        $security->denyAccessUnlessGranted('manage_options', null, 'You shall not pass!');
    }

    #[Test]
    public function isGrantedWithSubjectDelegatesToChecker(): void
    {
        $receivedSubject = null;
        $checker = new class ($receivedSubject) implements AuthorizationCheckerInterface {
            public function __construct(private mixed &$receivedSubject) {}

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                $this->receivedSubject = $subject;

                return true;
            }
        };

        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        self::assertTrue($security->isGranted('edit_post', 42));
        self::assertSame(42, $receivedSubject);
    }

    #[Test]
    public function denyAccessUnlessGrantedWithSubject(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return $subject === 'allowed-subject';
            }
        };

        $authManager = $this->createAuthManager(null);
        $security = new Security($checker, $authManager);

        // Should not throw
        $security->denyAccessUnlessGranted('edit_post', 'allowed-subject');

        // Should throw
        $this->expectException(AccessDeniedException::class);
        $security->denyAccessUnlessGranted('edit_post', 'denied-subject');
    }

    private function createAuthManager(?TokenInterface $token): AuthenticationManagerInterface
    {
        return new class ($token) implements AuthenticationManagerInterface {
            public function __construct(private readonly ?TokenInterface $token) {}

            public function handleAuthentication(mixed $user, string $username, string $password): mixed
            {
                return $user;
            }

            public function handleStatelessAuthentication(int $userId): int
            {
                return $userId;
            }

            public function getToken(): ?TokenInterface
            {
                return $this->token;
            }
        };
    }
}
