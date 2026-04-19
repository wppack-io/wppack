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

namespace WPPack\Component\Security\Authentication;

use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Exception\AuthenticationException;

interface AuthenticatorInterface
{
    public function supports(Request $request): bool;

    public function authenticate(Request $request): Passport;

    public function createToken(Passport $passport): TokenInterface;

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response;

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response;
}
