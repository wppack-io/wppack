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

/**
 * Token for service-level authentication without a WordPress user.
 *
 * Used by machine-to-machine integrations (e.g., SCIM provisioning) where
 * authorization is based on the token's capabilities rather than a user's roles.
 */
final readonly class ServiceToken implements TokenInterface
{
    /**
     * @param list<string> $roles
     * @param list<string> $capabilities
     */
    public function __construct(
        private string $serviceIdentifier,
        private array $roles = [],
        private array $capabilities = [],
        private ?int $blogId = null,
    ) {}

    public function getUser(): ?\WP_User
    {
        return null;
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

    public function getServiceIdentifier(): string
    {
        return $this->serviceIdentifier;
    }

    /**
     * @return list<string>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }
}
