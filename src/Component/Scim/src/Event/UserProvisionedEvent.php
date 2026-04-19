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

namespace WPPack\Component\Scim\Event;

use WPPack\Component\EventDispatcher\Event;

final class UserProvisionedEvent extends Event
{
    /**
     * @param array<string, mixed> $scimAttributes
     */
    public function __construct(
        private readonly \WP_User $user,
        private readonly array $scimAttributes,
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }

    /**
     * @return array<string, mixed>
     */
    public function getScimAttributes(): array
    {
        return $this->scimAttributes;
    }
}
