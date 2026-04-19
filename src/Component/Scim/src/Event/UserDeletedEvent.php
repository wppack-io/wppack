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

final class UserDeletedEvent extends Event
{
    public function __construct(
        private readonly int $userId,
        private readonly string $userLogin,
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserLogin(): string
    {
        return $this->userLogin;
    }
}
