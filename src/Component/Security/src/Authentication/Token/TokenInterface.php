<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Token;

interface TokenInterface
{
    public function getUser(): \WP_User;

    /** @return list<string> */
    public function getRoles(): array;

    public function isAuthenticated(): bool;
}
