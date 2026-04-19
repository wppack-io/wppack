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

namespace WPPack\Component\Security\Bridge\SAML\Configuration;

final readonly class SpSettings
{
    public function __construct(
        private string $entityId,
        private string $acsUrl,
        private ?string $sloUrl = null,
        private string $nameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    ) {}

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getAcsUrl(): string
    {
        return $this->acsUrl;
    }

    public function getSloUrl(): ?string
    {
        return $this->sloUrl;
    }

    public function getNameIdFormat(): string
    {
        return $this->nameIdFormat;
    }
}
