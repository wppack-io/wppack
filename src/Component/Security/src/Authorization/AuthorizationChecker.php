<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authorization;

use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authorization\Voter\AccessDecisionManager;

final class AuthorizationChecker implements AuthorizationCheckerInterface
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
