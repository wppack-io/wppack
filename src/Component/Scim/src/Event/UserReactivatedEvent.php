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

namespace WpPack\Component\Scim\Event;

use WpPack\Component\EventDispatcher\Event;

final class UserReactivatedEvent extends Event
{
    public function __construct(
        private readonly \WP_User $user,
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }
}
