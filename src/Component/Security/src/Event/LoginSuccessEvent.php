<?php

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
