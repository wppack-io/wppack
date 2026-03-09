<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Event;

use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;

final class OAuthResponseReceivedEvent extends Event
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

    public function getTokenSet(): OAuthTokenSet
    {
        return $this->tokenSet;
    }
}
