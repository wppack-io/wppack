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

namespace WpPack\Component\Security\Authentication\Passport\Badge;

final class UserBadge implements BadgeInterface
{
    private ?\WP_User $user = null;

    /**
     * @param \Closure|null $userLoader A callable that receives the user identifier and returns a \WP_User
     */
    public function __construct(
        private readonly string $userIdentifier,
        private readonly ?\Closure $userLoader = null,
    ) {
        if ($userIdentifier === '') {
            throw new \InvalidArgumentException('User identifier must not be empty.');
        }
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getUserLoader(): ?\Closure
    {
        return $this->userLoader;
    }

    public function getUser(): \WP_User
    {
        if ($this->user === null) {
            if ($this->userLoader === null) {
                throw new \LogicException('No user loader configured. Call setUser() or provide a user loader.');
            }

            $this->user = ($this->userLoader)($this->userIdentifier);
        }

        return $this->user;
    }

    public function setUser(\WP_User $user): void
    {
        $this->user = $user;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
