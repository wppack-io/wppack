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

namespace WPPack\Component\Security\Authentication\Token;

final readonly class PostAuthenticationToken implements TokenInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private \WP_User $user,
        private array $roles,
        private ?int $blogId = null,
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

    public function getBlogId(): ?int
    {
        return $this->blogId;
    }
}
