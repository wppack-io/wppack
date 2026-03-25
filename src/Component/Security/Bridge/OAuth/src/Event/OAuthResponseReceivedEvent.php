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
