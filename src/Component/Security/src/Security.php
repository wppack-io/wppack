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

namespace WPPack\Component\Security;

use WPPack\Component\Role\Authorization\AuthorizationCheckerInterface as RoleAuthorizationCheckerInterface;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WPPack\Component\Security\Exception\AccessDeniedException;

final class Security implements RoleAuthorizationCheckerInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly AuthenticationManagerInterface $authenticationManager,
    ) {}

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        return $this->authorizationChecker->isGranted($attribute, $subject);
    }

    public function getUser(): ?\WP_User
    {
        $token = $this->authenticationManager->getToken();

        if ($token === null || !$token->isAuthenticated()) {
            return null;
        }

        return $token->getUser();
    }

    public function denyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            throw new AccessDeniedException($message);
        }
    }
}
