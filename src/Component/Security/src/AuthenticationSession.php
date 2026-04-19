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

namespace WPPack\Component\Security;

final class AuthenticationSession
{
    public function login(int $userId, bool $remember = false, bool $secure = false): void
    {
        wp_clear_auth_cookie();
        wp_set_auth_cookie($userId, $remember, $secure);
    }

    public function logout(): void
    {
        wp_logout();
    }

    public function validateAuthCookie(string $cookie = '', string $scheme = 'logged_in'): int|false
    {
        return wp_validate_auth_cookie($cookie, $scheme);
    }

    public function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    public function getCurrentUser(): \WP_User
    {
        return wp_get_current_user();
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
