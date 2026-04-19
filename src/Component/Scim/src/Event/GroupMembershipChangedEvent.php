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

final class GroupMembershipChangedEvent extends Event
{
    /**
     * @param list<int> $added
     * @param list<int> $removed
     */
    public function __construct(
        private readonly string $roleName,
        private readonly array $added,
        private readonly array $removed,
    ) {}

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    /**
     * @return list<int>
     */
    public function getAdded(): array
    {
        return $this->added;
    }

    /**
     * @return list<int>
     */
    public function getRemoved(): array
    {
        return $this->removed;
    }
}
