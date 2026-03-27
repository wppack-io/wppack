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

final class GroupUpdatedEvent extends Event
{
    /**
     * @param array<string, mixed> $changes
     */
    public function __construct(
        private readonly string $roleName,
        private readonly array $changes,
    ) {}

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
