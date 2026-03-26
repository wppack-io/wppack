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

namespace WpPack\Component\Security\Bridge\SAML\Configuration;

final readonly class SamlConfiguration
{
    public function __construct(
        private IdpSettings $idpSettings,
        private SpSettings $spSettings,
        private bool $strict = true,
        private bool $debug = false,
        private bool $wantAssertionsSigned = true,
        private bool $wantNameIdEncrypted = false,
        private bool $allowRepeatAttributeName = false,
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

    /**
     * @return array<string, mixed>
     */
    public function toOneLoginArray(): array
    {
        $sp = [
            'entityId' => $this->spSettings->getEntityId(),
            'assertionConsumerService' => [
                'url' => $this->spSettings->getAcsUrl(),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            ],
            'NameIDFormat' => $this->spSettings->getNameIdFormat(),
        ];

        if ($this->spSettings->getSloUrl() !== null) {
            $sp['singleLogoutService'] = [
                'url' => $this->spSettings->getSloUrl(),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ];
        }

        $idp = [
            'entityId' => $this->idpSettings->getEntityId(),
            'singleSignOnService' => [
                'url' => $this->idpSettings->getSsoUrl(),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'x509cert' => $this->idpSettings->getX509Cert(),
        ];

        if ($this->idpSettings->getSloUrl() !== null) {
            $idp['singleLogoutService'] = [
                'url' => $this->idpSettings->getSloUrl(),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ];
        }

        if ($this->idpSettings->getCertFingerprint() !== null) {
            $idp['certFingerprint'] = $this->idpSettings->getCertFingerprint();
        }

        return [
            'strict' => $this->strict,
            'debug' => $this->debug,
            'sp' => $sp,
            'idp' => $idp,
            'security' => [
                'wantAssertionsSigned' => $this->wantAssertionsSigned,
                'wantNameIdEncrypted' => $this->wantNameIdEncrypted,
                'allowRepeatAttributeName' => $this->allowRepeatAttributeName,
            ],
        ];
    }
}
