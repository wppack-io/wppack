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

final readonly class SamlConfiguration
{
    public function __construct(
        private IdpSettings $idpSettings,
        private SpSettings $spSettings,
        private bool $strict = true,
        private bool $debug = false,
        private bool $wantAssertionsSigned = true,
        private bool $wantNameIdEncrypted = false,
    ) {}

    public function getIdpSettings(): IdpSettings
    {
        return $this->idpSettings;
    }

    public function getSpSettings(): SpSettings
    {
        return $this->spSettings;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function wantAssertionsSigned(): bool
    {
        return $this->wantAssertionsSigned;
    }

    public function wantNameIdEncrypted(): bool
    {
        return $this->wantNameIdEncrypted;
    }

    public function getIdpSsoUrl(): string
    {
        return $this->idpSettings->getSsoUrl();
    }

    public function getIdpSloUrl(): ?string
    {
        return $this->idpSettings->getSloUrl();
    }

    public function getSpAcsUrl(): string
    {
        return $this->spSettings->getAcsUrl();
    }

    public function getSpSloUrl(): ?string
    {
        return $this->spSettings->getSloUrl();
    }

    public function getSpEntityId(): string
    {
        return $this->spSettings->getEntityId();
    }

    public function getIdpEntityId(): string
    {
        return $this->idpSettings->getEntityId();
    }

    public function getIdpX509Cert(): string
    {
        return $this->idpSettings->getX509Cert();
    }
}
