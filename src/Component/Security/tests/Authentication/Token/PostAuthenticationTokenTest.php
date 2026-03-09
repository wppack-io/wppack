<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Token;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;

final class PostAuthenticationTokenTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }
    }

    #[Test]
    public function tokenReturnsUser(): void
    {
        $user = new \WP_User();
        $user->ID = 1;
        $user->user_login = 'admin';

        $token = new PostAuthenticationToken($user, ['administrator']);

        self::assertSame($user, $token->getUser());
    }

    #[Test]
    public function tokenReturnsRoles(): void
    {
        $user = new \WP_User();
        $user->ID = 1;

        $roles = ['administrator', 'editor'];
        $token = new PostAuthenticationToken($user, $roles);

        self::assertSame($roles, $token->getRoles());
    }

    #[Test]
    public function tokenIsAlwaysAuthenticated(): void
    {
        $user = new \WP_User();
        $user->ID = 1;

        $token = new PostAuthenticationToken($user, []);

        self::assertTrue($token->isAuthenticated());
    }

    #[Test]
    public function nullTokenIsNotAuthenticated(): void
    {
        $token = new NullToken();

        self::assertFalse($token->isAuthenticated());
    }

    #[Test]
    public function nullTokenThrowsOnGetUser(): void
    {
        $token = new NullToken();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('NullToken does not have a user.');

        $token->getUser();
    }
}
