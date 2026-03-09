<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication;

use WpPack\Component\Security\Authentication\Token\TokenInterface;

interface AuthenticationManagerInterface
{
    /**
     * WordPress `authenticate` filter handler.
     */
    public function handleAuthentication(mixed $user, string $username, string $password): mixed;

    /**
     * WordPress `determine_current_user` filter handler.
     */
    public function handleStatelessAuthentication(int $userId): int;

    /**
     * Get the current authentication token (null if not authenticated).
     */
    public function getToken(): ?TokenInterface;
}
