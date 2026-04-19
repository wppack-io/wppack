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

final readonly class IdpSettings
{
    public function __construct(
        private string $entityId,
        private string $ssoUrl,
        private ?string $sloUrl,
        private string $x509Cert,
        private ?string $certFingerprint = null,
    ) {}

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getSsoUrl(): string
    {
        return $this->ssoUrl;
    }

    public function getSloUrl(): ?string
    {
        return $this->sloUrl;
    }

    public function getX509Cert(): string
    {
        return $this->x509Cert;
    }

    public function getCertFingerprint(): ?string
    {
        return $this->certFingerprint;
    }
}
