<?php

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
