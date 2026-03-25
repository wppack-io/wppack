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

namespace WpPack\Component\Security\Authentication\Passport;

use WpPack\Component\Security\Authentication\Passport\Badge\BadgeInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Exception\AuthenticationException;

class Passport
{
    /** @var array<class-string<BadgeInterface>, BadgeInterface> */
    private array $badges = [];

    /**
     * @param list<BadgeInterface> $badges
     */
    public function __construct(
        private readonly UserBadge $userBadge,
        ?CredentialsBadge $credentialsBadge = null,
        array $badges = [],
    ) {
        $this->addBadge($userBadge);

        if ($credentialsBadge !== null) {
            $this->addBadge($credentialsBadge);
        }

        foreach ($badges as $badge) {
            $this->addBadge($badge);
        }
    }

    public function getUser(): \WP_User
    {
        return $this->userBadge->getUser();
    }

    public function getUserBadge(): UserBadge
    {
        return $this->userBadge;
    }

    public function addBadge(BadgeInterface $badge): self
    {
        $this->badges[$badge::class] = $badge;

        return $this;
    }

    /**
     * @param class-string<BadgeInterface> $badgeClass
     */
    public function hasBadge(string $badgeClass): bool
    {
        return isset($this->badges[$badgeClass]);
    }

    /**
     * @template T of BadgeInterface
     * @param class-string<T> $badgeClass
     * @return T|null
     */
    public function getBadge(string $badgeClass): ?BadgeInterface
    {
        return $this->badges[$badgeClass] ?? null;
    }

    /**
     * Ensures all badges have been resolved.
     *
     * This prevents authentication from proceeding without proper credential verification.
     *
     * @throws AuthenticationException if any badge is unresolved
     */
    public function ensureAllBadgesResolved(): void
    {
        foreach ($this->badges as $badge) {
            if (!$badge->isResolved()) {
                throw new AuthenticationException(\sprintf(
                    'Badge "%s" has not been resolved. Did you forget to register the required event listener?',
                    $badge::class,
                ));
            }
        }
    }
}
