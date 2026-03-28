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

namespace WpPack\Component\Scim\Authentication;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WpPack\Component\Security\Authentication\Token\ServiceToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

final readonly class ScimBearerAuthenticator implements StatelessAuthenticatorInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private string $bearerToken,
        private string $pathPrefix = '/wp-json/scim/v2',
    ) {
        if ($bearerToken === '') {
            throw new \InvalidArgumentException('Bearer token must not be empty.');
        }
    }

    public function supports(Request $request): bool
    {
        $path = $request->getPathInfo();

        if (!str_starts_with($path, $this->pathPrefix)) {
            return false;
        }

        $authHeader = $request->headers->get('Authorization');

        return $authHeader !== null && str_starts_with($authHeader, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null) {
            throw new AuthenticationException('Missing Authorization header.');
        }

        $token = substr($authHeader, 7);

        if ($token === '' || !hash_equals($this->bearerToken, $token)) {
            throw new AuthenticationException('Invalid bearer token.');
        }

        return new SelfValidatingPassport(
            new UserBadge('scim-service'),
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        return new ServiceToken(
            serviceIdentifier: 'scim-service',
            capabilities: [ScimConstants::CAPABILITY_PROVISION],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
