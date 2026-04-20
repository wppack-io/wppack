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

namespace WPPack\Component\Security\Bridge\SAML\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WPPack\Component\Security\Bridge\SAML\UserResolution\SamlAttributeMapping;

#[CoversClass(SpSettings::class)]
#[CoversClass(IdpSettings::class)]
#[CoversClass(SamlAttributeMapping::class)]
final class SamlConfigurationDtosTest extends TestCase
{
    // ── SpSettings ──────────────────────────────────────────────────

    #[Test]
    public function spSettingsCarriesEveryAttribute(): void
    {
        $sp = new SpSettings(
            entityId: 'https://wp.example.com',
            acsUrl: 'https://wp.example.com/saml/acs',
            sloUrl: 'https://wp.example.com/saml/slo',
            nameIdFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        );

        self::assertSame('https://wp.example.com', $sp->getEntityId());
        self::assertSame('https://wp.example.com/saml/acs', $sp->getAcsUrl());
        self::assertSame('https://wp.example.com/saml/slo', $sp->getSloUrl());
        self::assertSame(
            'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            $sp->getNameIdFormat(),
        );
    }

    #[Test]
    public function spSettingsSloUrlIsOptional(): void
    {
        $sp = new SpSettings(entityId: 'sp', acsUrl: 'https://acs');

        self::assertNull($sp->getSloUrl());
        self::assertSame(
            'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
            $sp->getNameIdFormat(),
            'default nameIdFormat is the SAML 1.1 "unspecified" URN',
        );
    }

    // ── IdpSettings ─────────────────────────────────────────────────

    #[Test]
    public function idpSettingsCarriesEveryAttribute(): void
    {
        $idp = new IdpSettings(
            entityId: 'https://idp.example.com',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: 'https://idp.example.com/slo',
            x509Cert: 'CERT-VALUE',
            certFingerprint: 'fp:123',
        );

        self::assertSame('https://idp.example.com', $idp->getEntityId());
        self::assertSame('https://idp.example.com/sso', $idp->getSsoUrl());
        self::assertSame('https://idp.example.com/slo', $idp->getSloUrl());
        self::assertSame('CERT-VALUE', $idp->getX509Cert());
        self::assertSame('fp:123', $idp->getCertFingerprint());
    }

    #[Test]
    public function idpSettingsFingerprintAndSloUrlAreOptional(): void
    {
        $idp = new IdpSettings(entityId: 'idp', ssoUrl: 'https://sso', sloUrl: null, x509Cert: 'x');

        self::assertNull($idp->getSloUrl());
        self::assertNull($idp->getCertFingerprint());
    }

    // ── SamlAttributeMapping ────────────────────────────────────────

    #[Test]
    public function samlAttributeMappingExposesPublicPair(): void
    {
        $mapping = new SamlAttributeMapping(samlAttribute: 'http://saml/attr/title', metaKey: '_saml_title');

        self::assertSame('http://saml/attr/title', $mapping->samlAttribute);
        self::assertSame('_saml_title', $mapping->metaKey);
    }
}
