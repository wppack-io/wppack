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

final class GroupProvisionedEvent extends Event
{
    public function __construct(
        private readonly string $roleName,
        private readonly string $roleLabel,
    ) {}

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    public function getRoleLabel(): string
    {
        return $this->roleLabel;
    }
}
