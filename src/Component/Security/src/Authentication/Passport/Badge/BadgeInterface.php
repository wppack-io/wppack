<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Passport\Badge;

interface BadgeInterface
{
    public function isResolved(): bool;
}
