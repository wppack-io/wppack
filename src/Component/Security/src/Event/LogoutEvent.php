<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Event;

use WpPack\Component\EventDispatcher\Event;

final class LogoutEvent extends Event
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }
}
