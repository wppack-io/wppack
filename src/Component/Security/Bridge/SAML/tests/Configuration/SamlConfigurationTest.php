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

namespace WpPack\Component\Security\Bridge\SAML\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;

#[CoversClass(SamlConfiguration::class)]
#[CoversClass(IdpSettings::class)]
#[CoversClass(SpSettings::class)]
final class SamlConfigurationTest extends TestCase
{
    private IdpSettings $idpSettings;
    private SpSettings $spSettings;

    protected function setUp(): void
    {
        $this->idpSettings = new IdpSettings(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: 'https://idp.example.com/slo',
            x509Cert: 'MIICDummyCert==',
            certFingerprint: 'AA:BB:CC:DD',
        );

        $this->spSettings = new SpSettings(
            entityId: 'https://sp.example.com/metadata',
            acsUrl: 'https://sp.example.com/acs',
            sloUrl: 'https://sp.example.com/slo',
        );
    }

    #[Test]
    public function idpSettingsGetters(): void
    {
        self::assertSame('https://idp.example.com/metadata', $this->idpSettings->getEntityId());
        self::assertSame('https://idp.example.com/sso', $this->idpSettings->getSsoUrl());
        self::assertSame('https://idp.example.com/slo', $this->idpSettings->getSloUrl());
        self::assertSame('MIICDummyCert==', $this->idpSettings->getX509Cert());
        self::assertSame('AA:BB:CC:DD', $this->idpSettings->getCertFingerprint());
    }

    #[Test]
    public function spSettingsGetters(): void
    {
        self::assertSame('https://sp.example.com/metadata', $this->spSettings->getEntityId());
        self::assertSame('https://sp.example.com/acs', $this->spSettings->getAcsUrl());
        self::assertSame('https://sp.example.com/slo', $this->spSettings->getSloUrl());
    }

    #[Test]
    public function spSettingsDefaults(): void
    {
        $sp = new SpSettings(
            entityId: 'https://sp.example.com',
            acsUrl: 'https://sp.example.com/acs',
        );

        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $sp->getNameIdFormat());
        self::assertNull($sp->getSloUrl());
    }

    #[Test]
    public function samlConfigurationGetters(): void
    {
        $config = new SamlConfiguration(
            idpSettings: $this->idpSettings,
            spSettings: $this->spSettings,
            strict: true,
            debug: false,
        );

        self::assertSame($this->idpSettings, $config->getIdpSettings());
        self::assertSame($this->spSettings, $config->getSpSettings());
        self::assertTrue($config->isStrict());
        self::assertFalse($config->isDebug());
        self::assertTrue($config->wantAssertionsSigned());
        self::assertFalse($config->wantNameIdEncrypted());
    }

    #[Test]
    public function toOneLoginArray(): void
    {
        $config = new SamlConfiguration(
            idpSettings: $this->idpSettings,
            spSettings: $this->spSettings,
            strict: true,
            debug: false,
        );

        $array = $config->toOneLoginArray();

        self::assertTrue($array['strict']);
        self::assertFalse($array['debug']);

        // SP settings
        self::assertSame('https://sp.example.com/metadata', $array['sp']['entityId']);
        self::assertSame('https://sp.example.com/acs', $array['sp']['assertionConsumerService']['url']);
        self::assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', $array['sp']['assertionConsumerService']['binding']);
        self::assertSame('https://sp.example.com/slo', $array['sp']['singleLogoutService']['url']);
        self::assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', $array['sp']['singleLogoutService']['binding']);
        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $array['sp']['NameIDFormat']);

        // IdP settings
        self::assertSame('https://idp.example.com/metadata', $array['idp']['entityId']);
        self::assertSame('https://idp.example.com/sso', $array['idp']['singleSignOnService']['url']);
        self::assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', $array['idp']['singleSignOnService']['binding']);
        self::assertSame('https://idp.example.com/slo', $array['idp']['singleLogoutService']['url']);
        self::assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', $array['idp']['singleLogoutService']['binding']);
        self::assertSame('MIICDummyCert==', $array['idp']['x509cert']);

        // Security section
        self::assertArrayHasKey('security', $array);
        self::assertTrue($array['security']['wantAssertionsSigned']);
        self::assertFalse($array['security']['wantNameIdEncrypted']);
    }

    #[Test]
    public function toOneLoginArrayWithNullSloUrl(): void
    {
        $idp = new IdpSettings(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: null,
            x509Cert: 'MIICDummyCert==',
        );

        $sp = new SpSettings(
            entityId: 'https://sp.example.com',
            acsUrl: 'https://sp.example.com/acs',
        );

        $config = new SamlConfiguration(idpSettings: $idp, spSettings: $sp);
        $array = $config->toOneLoginArray();

        // When SLO URLs are null, singleLogoutService keys should not be present
        self::assertArrayNotHasKey('singleLogoutService', $array['sp']);
        self::assertArrayNotHasKey('singleLogoutService', $array['idp']);
    }
}
