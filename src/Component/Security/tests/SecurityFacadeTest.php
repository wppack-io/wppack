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

namespace WPPack\Component\Security\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WPPack\Component\Security\Exception\AccessDeniedException;
use WPPack\Component\Security\Security;

#[CoversClass(Security::class)]
final class SecurityFacadeTest extends TestCase
{
    private function makeUser(string $prefix = 'security_facade'): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);

        return new \WP_User($userId);
    }

    #[Test]
    public function isGrantedDelegatesToAuthorizationChecker(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())
            ->method('isGranted')
            ->with('manage_options', 'subject')
            ->willReturn(true);

        $security = new Security($checker, $this->createMock(AuthenticationManagerInterface::class));

        self::assertTrue($security->isGranted('manage_options', 'subject'));
    }

    #[Test]
    public function getUserReturnsNullWhenNoTokenPresent(): void
    {
        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('getToken')->willReturn(null);

        $security = new Security($this->createMock(AuthorizationCheckerInterface::class), $manager);

        self::assertNull($security->getUser());
    }

    #[Test]
    public function getUserReturnsNullWhenTokenNotAuthenticated(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('isAuthenticated')->willReturn(false);

        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('getToken')->willReturn($token);

        $security = new Security($this->createMock(AuthorizationCheckerInterface::class), $manager);

        self::assertNull($security->getUser());
    }

    #[Test]
    public function getUserReturnsWpUserFromAuthenticatedToken(): void
    {
        $user = $this->makeUser();
        $token = $this->createMock(TokenInterface::class);
        $token->method('isAuthenticated')->willReturn(true);
        $token->method('getUser')->willReturn($user);

        $manager = $this->createMock(AuthenticationManagerInterface::class);
        $manager->method('getToken')->willReturn($token);

        $security = new Security($this->createMock(AuthorizationCheckerInterface::class), $manager);

        self::assertSame($user, $security->getUser());
    }

    #[Test]
    public function denyAccessUnlessGrantedPassesSilentlyWhenGranted(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $security = new Security($checker, $this->createMock(AuthenticationManagerInterface::class));

        $security->denyAccessUnlessGranted('manage_options');
        self::assertTrue(true, 'no exception thrown');
    }

    #[Test]
    public function denyAccessUnlessGrantedThrowsWhenDenied(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $security = new Security($checker, $this->createMock(AuthenticationManagerInterface::class));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('nope');

        $security->denyAccessUnlessGranted('manage_options', null, 'nope');
    }
}
