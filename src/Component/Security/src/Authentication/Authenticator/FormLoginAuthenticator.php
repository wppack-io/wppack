<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Authenticator;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

final class FormLoginAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->post->has('log')
            && $request->post->has('pwd');
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->post->getString('log');
        $password = $request->post->getString('pwd');
        $remember = $request->post->getBoolean('rememberme', false);

        return new Passport(
            new UserBadge($username),
            new CredentialsBadge($password),
            $remember ? [new RememberMeBadge(true)] : [],
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();
        $blogId = \function_exists('get_current_blog_id') ? get_current_blog_id() : null;

        return new PostAuthenticationToken($user, $user->roles, $blogId);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): void
    {
        // Remember me cookie handling is delegated to WordPress
        // via wp_set_auth_cookie which is called by the WordPress login flow
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): void
    {
        // Failure handling is done by returning WP_Error from AuthenticationManager
    }
}
