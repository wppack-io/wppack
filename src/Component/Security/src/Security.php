<?php

declare(strict_types=1);

namespace WpPack\Component\Security;

use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Exception\AccessDeniedException;

final class Security
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
