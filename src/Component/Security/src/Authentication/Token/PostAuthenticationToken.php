<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Token;

final readonly class PostAuthenticationToken implements TokenInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private \WP_User $user,
        private array $roles,
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isAuthenticated(): bool
    {
        return true;
    }
}
