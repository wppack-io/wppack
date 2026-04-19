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

namespace WPPack\Component\Security\Event;

use WPPack\Component\EventDispatcher\Event;

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
