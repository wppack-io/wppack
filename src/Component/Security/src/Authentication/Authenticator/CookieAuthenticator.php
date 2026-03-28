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

namespace WpPack\Component\Security\Authentication\Authenticator;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\User\UserRepositoryInterface;

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

        return new PostAuthenticationToken($user, $user->roles, $blogId);
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
