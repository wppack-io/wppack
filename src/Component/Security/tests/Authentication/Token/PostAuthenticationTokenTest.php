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

namespace WPPack\Component\Security\Tests\Authentication\Token;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Authentication\Token\NullToken;
use WPPack\Component\Security\Authentication\Token\PostAuthenticationToken;

final class PostAuthenticationTokenTest extends TestCase
{
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
    public function nullTokenGetUserReturnsNull(): void
    {
        $token = new NullToken();

        self::assertNull($token->getUser());
    }

    #[Test]
    public function blogIdIsNullByDefault(): void
    {
        $user = new \WP_User();
        $user->ID = 1;

        $token = new PostAuthenticationToken($user, ['administrator']);

        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function blogIdIsReturnedWhenSet(): void
    {
        $user = new \WP_User();
        $user->ID = 1;

        $token = new PostAuthenticationToken($user, ['administrator'], 2);

        self::assertSame(2, $token->getBlogId());
    }

    #[Test]
    public function nullTokenBlogIdIsNull(): void
    {
        $token = new NullToken();

        self::assertNull($token->getBlogId());
    }
}
