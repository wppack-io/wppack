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
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\User\UserRepositoryInterface;

final class CookieAuthenticator implements StatelessAuthenticatorInterface
{
    public function __construct(
        private readonly AuthenticationSession $authSession,
        private readonly BlogContextInterface $blogContext,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function supports(Request $request): bool
    {
        return $request->cookies->has(\LOGGED_IN_COOKIE); // @phpstan-ignore constant.notFound
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $this->authSession->validateAuthCookie('', 'logged_in');

        if ($userId === false) {
            throw new AuthenticationException('Invalid authentication cookie.');
        }

        $userRepository = $this->userRepository;

        return new SelfValidatingPassport(
            new UserBadge((string) $userId, static function (string $identifier) use ($userRepository): \WP_User {
                $user = $userRepository->find((int) $identifier);

                if ($user === null) {
                    throw new AuthenticationException('User not found.');
                }

                return $user;
            }),
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
        // Cookie authentication is passive - no action needed on success
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Cookie authentication is passive - no action needed on failure
        return null;
    }
}
