<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Event;

use WpPack\Component\EventDispatcher\Event;

final class SamlResponseReceivedEvent extends Event
{
    /**
     * @param array<string, list<string>> $attributes
     */
    public function __construct(
        private readonly string $nameId,
        private readonly array $attributes,
        private readonly ?string $sessionIndex,
    ) {}

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

    public function getSessionIndex(): ?string
    {
        return $this->sessionIndex;
    }
}
