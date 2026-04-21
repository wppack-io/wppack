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
use WPPack\Component\Security\Authentication\AuthenticatorInterface;
use WPPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\Site\BlogContextInterface;

final class FormLoginAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly BlogContextInterface $blogContext,
    ) {}

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
        $blogId = $this->blogContext->getCurrentBlogId();

        return new PostAuthenticationToken($user, array_values($user->roles), $blogId);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        // Remember me cookie handling is delegated to WordPress
        // via wp_set_auth_cookie which is called by the WordPress login flow
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Failure handling is done by returning WP_Error from AuthenticationManager
        return null;
    }
}
