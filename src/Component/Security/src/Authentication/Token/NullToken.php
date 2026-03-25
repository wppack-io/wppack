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

namespace WpPack\Component\Security\Authentication\Token;

final class NullToken implements TokenInterface
{
    public function getUser(): \WP_User
    {
        throw new \LogicException('NullToken does not have a user. Check isAuthenticated() first.');
    }

    public function getRoles(): array
    {
        return [];
    }

    public function isAuthenticated(): bool
    {
        return false;
    }

    public function getBlogId(): ?int
    {
        return null;
    }
}
