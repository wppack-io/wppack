<?php

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
