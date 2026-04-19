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

final class SamlUserAttributesMappedEvent extends Event
{
    /**
     * @param array<string, mixed>        $userdata
     * @param array<string, mixed>        $userMeta
     * @param array<string, list<string>> $attributes
     */
    public function __construct(
        private array $userdata,
        private array $userMeta,
        private readonly array $attributes,
        private readonly string $nameId,
        private readonly bool $isNewUser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getUserdata(): array
    {
        return $this->userdata;
    }

    /**
     * @param array<string, mixed> $userdata
     */
    public function setUserdata(array $userdata): void
    {
        $this->userdata = $userdata;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserMeta(): array
    {
        return $this->userMeta;
    }

    /**
     * @param array<string, mixed> $userMeta
     */
    public function setUserMeta(array $userMeta): void
    {
        $this->userMeta = $userMeta;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getNameId(): string
    {
        return $this->nameId;
    }

    public function isNewUser(): bool
    {
        return $this->isNewUser;
    }
}
