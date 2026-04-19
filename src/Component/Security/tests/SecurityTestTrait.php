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

namespace WPPack\Component\Security\Tests;

use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WPPack\Component\Security\Security;

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
