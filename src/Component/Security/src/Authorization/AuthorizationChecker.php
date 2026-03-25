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

namespace WpPack\Component\Security\Authorization;

use WpPack\Component\Role\Authorization\AuthorizationCheckerInterface as RoleAuthorizationCheckerInterface;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authorization\Voter\AccessDecisionManager;

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
