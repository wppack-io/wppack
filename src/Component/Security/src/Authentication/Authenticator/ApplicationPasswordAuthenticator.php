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

namespace WPPack\Component\Security\Authentication\Authenticator;

use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WPPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WPPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\Site\BlogContextInterface;

final class ApplicationPasswordAuthenticator implements StatelessAuthenticatorInterface
{
    public function __construct(
        private readonly BlogContextInterface $blogContext,
    ) {}

    public function supports(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization', '') ?? '';

        if (!str_starts_with($authHeader, 'Basic ')) {
            return false;
        }

        // Only supports REST API requests
        return str_contains($request->getPathInfo(), '/wp-json/')
            || (\defined('REST_REQUEST') && \REST_REQUEST);
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '') ?? '';
        $decoded = base64_decode(substr($authHeader, 6), true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            throw new AuthenticationException('Invalid Basic authentication header.');
        }

        [$username, $password] = explode(':', $decoded, 2);

        $user = wp_authenticate_application_password(null, $username, $password);

        if ($user instanceof \WP_Error || !$user instanceof \WP_User) {
            throw new AuthenticationException('Invalid application password.');
        }

        return new SelfValidatingPassport(
            new UserBadge($username, static fn(): \WP_User => $user),
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();
        $blogId = $this->blogContext->getCurrentBlogId();

        return new PostAuthenticationToken($user, array_values($user->roles), $blogId);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        // Application password authentication is passive
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Application password authentication is passive
        return null;
    }
}
