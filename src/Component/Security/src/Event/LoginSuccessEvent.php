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

namespace WpPack\Component\Security\Event;

use WpPack\Component\EventDispatcher\Event;

/**
 * Dispatched after a successful WordPress login (wp_login hook).
 */
final class LoginSuccessEvent extends Event
{
    public function __construct(
        private readonly \WP_User $user,
        private readonly string $username,
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
