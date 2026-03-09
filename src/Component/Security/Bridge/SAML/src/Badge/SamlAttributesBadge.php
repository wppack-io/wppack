<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Badge;

use WpPack\Component\Security\Authentication\Passport\Badge\BadgeInterface;

final class SamlAttributesBadge implements BadgeInterface
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

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getAttributeValues(string $name): array
    {
        return $this->attributes[$name] ?? [];
    }

    public function getSessionIndex(): ?string
    {
        return $this->sessionIndex;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
