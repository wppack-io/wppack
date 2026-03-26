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

namespace WpPack\Component\Security\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\AuthenticationSession;

#[CoversClass(AuthenticationSession::class)]
final class AuthenticationSessionTest extends TestCase
{
    private AuthenticationSession $authSession;

    private ?\Closure $suppressCookies = null;

    protected function setUp(): void
    {
        $this->authSession = new AuthenticationSession();

        // Prevent setcookie() calls from wp_clear_auth_cookie() which produce
        // "Cannot modify header information" warnings when running under coverage.
        $this->suppressCookies = static fn(): bool => false;
        add_filter('send_auth_cookies', $this->suppressCookies, \PHP_INT_MAX);
    }

    protected function tearDown(): void
    {
        if ($this->suppressCookies !== null) {
            remove_filter('send_auth_cookies', $this->suppressCookies, \PHP_INT_MAX);
            $this->suppressCookies = null;
        }

        wp_set_current_user(0);
    }

    #[Test]
    public function loginSetsAuthCookie(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'auth_session_login_' . uniqid(),
            'user_email' => 'auth_session_login_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        try {
            // login() should not throw
            $this->authSession->login($userId, false, false);
            self::assertTrue(true);
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function logoutCallsWpLogout(): void
    {
        $logoutFired = false;
        $listener = static function () use (&$logoutFired): void {
            $logoutFired = true;
        };
        add_action('wp_logout', $listener);

        try {
            $this->authSession->logout();
            self::assertTrue($logoutFired);
        } finally {
            remove_action('wp_logout', $listener);
        }
    }

    #[Test]
    public function validateAuthCookieReturnsFalseForInvalidCookie(): void
    {
        $result = $this->authSession->validateAuthCookie('invalid', 'logged_in');

        self::assertFalse($result);
    }

    #[Test]
    public function validateAuthCookieReturnsUserIdForValidCookie(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'auth_session_cookie_' . uniqid(),
            'user_email' => 'auth_session_cookie_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        try {
            $expiration = time() + DAY_IN_SECONDS;
            $cookie = wp_generate_auth_cookie($userId, $expiration, 'logged_in');
            $_COOKIE[LOGGED_IN_COOKIE] = $cookie;

            $result = $this->authSession->validateAuthCookie($cookie, 'logged_in');

            self::assertSame($userId, $result);
        } finally {
            unset($_COOKIE[LOGGED_IN_COOKIE]);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function getCurrentUserIdReturnsZeroWhenNotLoggedIn(): void
    {
        wp_set_current_user(0);

        self::assertSame(0, $this->authSession->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdReturnsUserIdWhenLoggedIn(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'auth_session_uid_' . uniqid(),
            'user_email' => 'auth_session_uid_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        try {
            wp_set_current_user($userId);

            self::assertSame($userId, $this->authSession->getCurrentUserId());
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function getCurrentUserReturnsWpUser(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'auth_session_user_' . uniqid(),
            'user_email' => 'auth_session_user_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        try {
            wp_set_current_user($userId);

            $user = $this->authSession->getCurrentUser();

            self::assertInstanceOf(\WP_User::class, $user);
            self::assertSame($userId, $user->ID);
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isLoggedInReturnsFalseWhenNotLoggedIn(): void
    {
        wp_set_current_user(0);

        self::assertFalse($this->authSession->isLoggedIn());
    }

    #[Test]
    public function isLoggedInReturnsTrueWhenLoggedIn(): void
    {
        $userId = (int) wp_insert_user([
            'user_login' => 'auth_session_logged_' . uniqid(),
            'user_email' => 'auth_session_logged_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        try {
            wp_set_current_user($userId);

            self::assertTrue($this->authSession->isLoggedIn());
        } finally {
            wp_delete_user($userId);
        }
    }
}
