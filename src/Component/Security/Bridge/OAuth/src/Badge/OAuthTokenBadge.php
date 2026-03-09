<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Badge;

use WpPack\Component\Security\Authentication\Passport\Badge\BadgeInterface;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;

final class OAuthTokenBadge implements BadgeInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        private readonly string $subject,
        private readonly array $claims,
        private readonly OAuthTokenSet $tokenSet,
    ) {}

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        return $this->claims;
    }

    public function getClaim(string $name): mixed
    {
        return $this->claims[$name] ?? null;
    }

    public function getTokenSet(): OAuthTokenSet
    {
        return $this->tokenSet;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
