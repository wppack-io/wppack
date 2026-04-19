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

namespace WPPack\Component\Security\Authorization;

use WPPack\Component\Role\Authorization\AuthorizationCheckerInterface as RoleAuthorizationCheckerInterface;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Authentication\Token\NullToken;
use WPPack\Component\Security\Authorization\Voter\AccessDecisionManager;

final class AuthorizationChecker implements AuthorizationCheckerInterface, RoleAuthorizationCheckerInterface
{
    public function __construct(
        private readonly AccessDecisionManager $accessDecisionManager,
        private readonly AuthenticationManagerInterface $authenticationManager,
    ) {}

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        $token = $this->authenticationManager->getToken() ?? new NullToken();

        return $this->accessDecisionManager->decide($token, $attribute, $subject);
    }
}
