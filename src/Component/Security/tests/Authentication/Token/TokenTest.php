<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Token;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;

final class TokenTest extends TestCase
{
    // ---------------------------------------------------------------
    // PostAuthenticationToken
    // ---------------------------------------------------------------

    #[Test]
    public function postAuthenticationTokenImplementsTokenInterface(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, ['administrator']);

        self::assertInstanceOf(TokenInterface::class, $token);
    }

    #[Test]
    public function postAuthenticationTokenReturnsSameUserInstance(): void
    {
        $user = $this->createWpUser(42, 'editor_user');
        $token = new PostAuthenticationToken($user, ['editor']);

        self::assertSame($user, $token->getUser());
        self::assertSame(42, $token->getUser()->ID);
        self::assertSame('editor_user', $token->getUser()->user_login);
    }

    #[Test]
    public function postAuthenticationTokenReturnsSingleRole(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, ['subscriber']);

        self::assertSame(['subscriber'], $token->getRoles());
        self::assertCount(1, $token->getRoles());
    }

    #[Test]
    public function postAuthenticationTokenReturnsMultipleRoles(): void
    {
        $user = $this->createWpUser(1);
        $roles = ['administrator', 'editor', 'author'];
        $token = new PostAuthenticationToken($user, $roles);

        self::assertSame($roles, $token->getRoles());
        self::assertCount(3, $token->getRoles());
    }

    #[Test]
    public function postAuthenticationTokenAcceptsEmptyRoles(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, []);

        self::assertSame([], $token->getRoles());
        self::assertCount(0, $token->getRoles());
    }

    #[Test]
    public function postAuthenticationTokenIsAlwaysAuthenticated(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, []);

        self::assertTrue($token->isAuthenticated());
    }

    #[Test]
    public function postAuthenticationTokenIsAuthenticatedEvenWithEmptyRoles(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, []);

        // Even with no roles, PostAuthenticationToken is always authenticated
        self::assertTrue($token->isAuthenticated());
    }

    #[Test]
    public function postAuthenticationTokenBlogIdIsNullByDefault(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, ['administrator']);

        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function postAuthenticationTokenReturnsBlogIdWhenProvided(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, ['administrator'], 5);

        self::assertSame(5, $token->getBlogId());
    }

    #[Test]
    public function postAuthenticationTokenAcceptsBlogIdOne(): void
    {
        $user = $this->createWpUser(1);
        $token = new PostAuthenticationToken($user, ['editor'], 1);

        self::assertSame(1, $token->getBlogId());
    }

    #[Test]
    public function postAuthenticationTokenIsReadonly(): void
    {
        $user = $this->createWpUser(10, 'readonly_user');
        $roles = ['subscriber'];
        $token = new PostAuthenticationToken($user, $roles, 3);

        // Verify all accessors return consistent values across multiple calls
        self::assertSame($user, $token->getUser());
        self::assertSame($roles, $token->getRoles());
        self::assertTrue($token->isAuthenticated());
        self::assertSame(3, $token->getBlogId());

        // Second access should return identical results
        self::assertSame($user, $token->getUser());
        self::assertSame($roles, $token->getRoles());
        self::assertTrue($token->isAuthenticated());
        self::assertSame(3, $token->getBlogId());
    }

    #[Test]
    public function postAuthenticationTokenPreservesRoleOrder(): void
    {
        $user = $this->createWpUser(1);
        $roles = ['contributor', 'author', 'editor', 'administrator'];
        $token = new PostAuthenticationToken($user, $roles);

        self::assertSame($roles, $token->getRoles());
        self::assertSame('contributor', $token->getRoles()[0]);
        self::assertSame('administrator', $token->getRoles()[3]);
    }

    // ---------------------------------------------------------------
    // NullToken
    // ---------------------------------------------------------------

    #[Test]
    public function nullTokenImplementsTokenInterface(): void
    {
        $token = new NullToken();

        self::assertInstanceOf(TokenInterface::class, $token);
    }

    #[Test]
    public function nullTokenIsNotAuthenticated(): void
    {
        $token = new NullToken();

        self::assertFalse($token->isAuthenticated());
    }

    #[Test]
    public function nullTokenReturnsEmptyRoles(): void
    {
        $token = new NullToken();

        self::assertSame([], $token->getRoles());
        self::assertCount(0, $token->getRoles());
    }

    #[Test]
    public function nullTokenGetUserThrowsLogicException(): void
    {
        $token = new NullToken();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('NullToken does not have a user.');

        $token->getUser();
    }

    #[Test]
    public function nullTokenGetUserExceptionMessageSuggestsCheckingAuthentication(): void
    {
        $token = new NullToken();

        try {
            $token->getUser();
            self::fail('Expected LogicException was not thrown.');
        } catch (\LogicException $e) {
            self::assertStringContainsString('Check isAuthenticated() first', $e->getMessage());
        }
    }

    #[Test]
    public function nullTokenBlogIdIsNull(): void
    {
        $token = new NullToken();

        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function nullTokenIsConsistentAcrossMultipleCalls(): void
    {
        $token = new NullToken();

        self::assertFalse($token->isAuthenticated());
        self::assertSame([], $token->getRoles());
        self::assertNull($token->getBlogId());

        // Second round of calls should return the same values
        self::assertFalse($token->isAuthenticated());
        self::assertSame([], $token->getRoles());
        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function multipleNullTokenInstancesAreIndependent(): void
    {
        $token1 = new NullToken();
        $token2 = new NullToken();

        self::assertFalse($token1->isAuthenticated());
        self::assertFalse($token2->isAuthenticated());
        self::assertNotSame($token1, $token2);
    }

    // ---------------------------------------------------------------
    // TokenInterface contract comparison
    // ---------------------------------------------------------------

    #[Test]
    public function authenticatedAndUnauthenticatedTokensContrastCorrectly(): void
    {
        $user = $this->createWpUser(1, 'admin');
        $authToken = new PostAuthenticationToken($user, ['administrator'], 1);
        $nullToken = new NullToken();

        // Authentication status
        self::assertTrue($authToken->isAuthenticated());
        self::assertFalse($nullToken->isAuthenticated());

        // Roles
        self::assertNotEmpty($authToken->getRoles());
        self::assertEmpty($nullToken->getRoles());

        // Blog ID
        self::assertSame(1, $authToken->getBlogId());
        self::assertNull($nullToken->getBlogId());
    }

    private function createWpUser(int $id, string $login = 'testuser'): \WP_User
    {
        $user = new \WP_User();
        $user->ID = $id;
        $user->user_login = $login;

        return $user;
    }
}
