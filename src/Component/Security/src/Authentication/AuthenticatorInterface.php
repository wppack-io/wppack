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

namespace WpPack\Component\Security\Authentication;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

interface AuthenticatorInterface
{
    public function supports(Request $request): bool;

    public function authenticate(Request $request): Passport;

    public function createToken(Passport $passport): TokenInterface;

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response;

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response;
}
