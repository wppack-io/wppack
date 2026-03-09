<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Authenticator;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

final class CookieAuthenticator implements StatelessAuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        if (\defined('LOGGED_IN_COOKIE')) {
            $cookieName = \LOGGED_IN_COOKIE;
        } elseif (\defined('COOKIEHASH')) {
            $cookieName = 'wordpress_logged_in_' . \COOKIEHASH;
        } else {
            return false;
        }

        return $request->cookies->has($cookieName);
    }

    public function authenticate(Request $request): Passport
    {
        $userId = wp_validate_auth_cookie('', 'logged_in');

        if ($userId === false) {
            throw new AuthenticationException('Invalid authentication cookie.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $userId, static function (string $identifier): \WP_User {
                $user = get_user_by('id', (int) $identifier);

                if ($user === false) {
                    throw new AuthenticationException('User not found.');
                }

                return $user;
            }),
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();

        return new PostAuthenticationToken($user, $user->roles);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): void
    {
        // Cookie authentication is passive - no action needed on success
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): void
    {
        // Cookie authentication is passive - no action needed on failure
    }
}
