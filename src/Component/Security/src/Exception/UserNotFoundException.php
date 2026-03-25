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

namespace WpPack\Component\Security\Exception;

/**
 * Thrown when a user cannot be found.
 *
 * The exception message intentionally does NOT reveal whether the user exists,
 * to prevent user enumeration attacks.
 */
final class UserNotFoundException extends AuthenticationException
{
    private string $userIdentifier = '';

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }
}
