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

use WPPack\Component\Security\Authentication\Token\TokenInterface;

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
