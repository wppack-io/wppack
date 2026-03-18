<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Authenticator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Authenticator\CookieAuthenticator;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Exception\AuthenticationException;

final class CookieAuthenticatorTest extends TestCase
{
    private CookieAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new CookieAuthenticator();
    }

    #[Test]
    public function supportsRequestWithAuthCookie(): void
    {
        $cookieName = LOGGED_IN_COOKIE;

        $request = new Request(
            cookies: [$cookieName => 'some_cookie_value'],
            server: ['REQUEST_METHOD' => 'GET'],
        );

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function doesNotSupportRequestWithoutCookie(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET'],
        );

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function authenticateThrowsExceptionForInvalidCookie(): void
    {
        // wp_validate_auth_cookie returns false for invalid cookies
        $request = new Request(
            cookies: [LOGGED_IN_COOKIE => 'invalid_cookie_value'],
            server: ['REQUEST_METHOD' => 'GET'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid authentication cookie.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateReturnsPassportForValidUser(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'cookie_auth_test_' . uniqid(),
            'user_email' => 'cookie_auth_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        try {
            // Generate a valid auth cookie for this user
            $expiration = time() + DAY_IN_SECONDS;
            $cookie = wp_generate_auth_cookie($userId, $expiration, 'logged_in');

            // Set the cookie in the global so wp_validate_auth_cookie can find it
            $_COOKIE[LOGGED_IN_COOKIE] = $cookie;

            $request = new Request(
                cookies: [LOGGED_IN_COOKIE => $cookie],
                server: ['REQUEST_METHOD' => 'GET'],
            );

            $passport = $this->authenticator->authenticate($request);

            self::assertInstanceOf(SelfValidatingPassport::class, $passport);
            self::assertTrue($passport->hasBadge(UserBadge::class));

            $userBadge = $passport->getBadge(UserBadge::class);
            self::assertInstanceOf(UserBadge::class, $userBadge);
            self::assertSame((string) $userId, $userBadge->getUserIdentifier());

            // Trigger user loading
            $user = $passport->getUser();
            self::assertInstanceOf(\WP_User::class, $user);
            self::assertSame($userId, $user->ID);
        } finally {
            unset($_COOKIE[LOGGED_IN_COOKIE]);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function createTokenReturnsPostAuthenticationToken(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'cookie_token_test_' . uniqid(),
            'user_email' => 'cookie_token_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'editor',
        ]);

        self::assertIsInt($userId);

        try {
            $user = get_user_by('id', $userId);
            self::assertInstanceOf(\WP_User::class, $user);

            $userBadge = new UserBadge((string) $userId, static fn() => $user);
            $passport = new SelfValidatingPassport($userBadge);

            $token = $this->authenticator->createToken($passport);

            self::assertInstanceOf(PostAuthenticationToken::class, $token);
            self::assertSame($user, $token->getUser());
            self::assertContains('editor', $token->getRoles());
            self::assertSame(get_current_blog_id(), $token->getBlogId());
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function onAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $user = $this->createMock(\WP_User::class);
        $user->roles = ['subscriber'];
        $token = new PostAuthenticationToken($user, ['subscriber']);

        self::assertNull($this->authenticator->onAuthenticationSuccess($request, $token));
    }

    #[Test]
    public function onAuthenticationFailureReturnsNull(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $exception = new AuthenticationException('test');

        self::assertNull($this->authenticator->onAuthenticationFailure($request, $exception));
    }

    #[Test]
    public function authenticateUserLoaderThrowsWhenUserNotFound(): void
    {
        // Create a user, generate auth cookie, then delete the user
        $userId = wp_insert_user([
            'user_login' => 'cookie_deleted_' . uniqid(),
            'user_email' => 'cookie_deleted_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $expiration = time() + DAY_IN_SECONDS;
        $cookie = wp_generate_auth_cookie($userId, $expiration, 'logged_in');
        $_COOKIE[LOGGED_IN_COOKIE] = $cookie;

        $request = new Request(
            cookies: [LOGGED_IN_COOKIE => $cookie],
            server: ['REQUEST_METHOD' => 'GET'],
        );

        try {
            $passport = $this->authenticator->authenticate($request);

            // Delete user before calling getUser()
            wp_delete_user($userId);

            $this->expectException(AuthenticationException::class);
            $this->expectExceptionMessage('User not found.');

            $passport->getUser();
        } finally {
            unset($_COOKIE[LOGGED_IN_COOKIE]);
            // Ensure cleanup
            if (get_user_by('id', $userId)) {
                wp_delete_user($userId);
            }
        }
    }
}
