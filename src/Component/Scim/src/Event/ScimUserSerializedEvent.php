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

final class ScimUserSerializedEvent extends Event
{
    /**
     * @param array<string, mixed> $scimAttributes
     */
    public function __construct(
        private array $scimAttributes,
        private readonly \WP_User $user,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getScimAttributes(): array
    {
        return $this->scimAttributes;
    }

    /**
     * @param array<string, mixed> $scimAttributes
     */
    public function setScimAttributes(array $scimAttributes): void
    {
        $this->scimAttributes = $scimAttributes;
    }

    public function getUser(): \WP_User
    {
        return $this->user;
    }
}
