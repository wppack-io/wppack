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

namespace WPPack\Component\Security\Bridge\OAuth\Event;

use WPPack\Component\EventDispatcher\Event;

final class OAuthUserProvisionedEvent extends Event
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        private readonly \WP_User $user,
        private readonly string $subject,
        private readonly array $claims,
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }

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
}
