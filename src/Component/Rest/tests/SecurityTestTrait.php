<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Security;

trait SecurityTestTrait
{
    private function createSecurity(?\WP_User $user = null, bool $granted = false): Security
    {
        $checker = new class ($granted) implements AuthorizationCheckerInterface {
            public function __construct(private readonly bool $granted) {}

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return $this->granted;
            }
        };

        $token = $user !== null ? new PostAuthenticationToken($user, ['subscriber']) : null;

        $authManager = new class ($token) implements AuthenticationManagerInterface {
            public function __construct(private readonly ?TokenInterface $token) {}

            public function handleAuthentication(mixed $user, string $username, string $password): mixed
            {
                return $user;
            }

            public function handleStatelessAuthentication(int $userId): int
            {
                return $userId;
            }

            public function getToken(): ?TokenInterface
            {
                return $this->token;
            }
        };

        return new Security($checker, $authManager);
    }
}
