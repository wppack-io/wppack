<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Authenticator;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

final class ApplicationPasswordAuthenticator implements StatelessAuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authHeader, 'Basic ')) {
            return false;
        }

        // Only supports REST API requests
        return str_contains($request->getPathInfo(), '/wp-json/')
            || (\defined('REST_REQUEST') && \REST_REQUEST);
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');
        $decoded = base64_decode(substr($authHeader, 6), true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            throw new AuthenticationException('Invalid Basic authentication header.');
        }

        [$username, $password] = explode(':', $decoded, 2);

        $user = wp_authenticate_application_password(null, $username, $password);

        if ($user instanceof \WP_Error) {
            throw new AuthenticationException('Invalid application password.');
        }

        return new SelfValidatingPassport(
            new UserBadge($username, static fn(): \WP_User => $user),
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();
        $blogId = \function_exists('get_current_blog_id') ? get_current_blog_id() : null;

        return new PostAuthenticationToken($user, $user->roles, $blogId);
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
