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

final class RememberMeBadge implements BadgeInterface
{
    public function __construct(
        private readonly bool $rememberMe = false,
    ) {}

    public function isEnabled(): bool
    {
        return $this->rememberMe;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
