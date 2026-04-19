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

namespace WPPack\Component\Security\Bridge\SAML\Event;

use WPPack\Component\EventDispatcher\Event;

final class SamlUserProvisionedEvent extends Event
{
    /**
     * @param array<string, list<string>> $attributes
     */
    public function __construct(
        private readonly \WP_User $user,
        private readonly string $nameId,
        private readonly array $attributes,
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }

    public function getNameId(): string
    {
        return $this->nameId;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
