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
use WPPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpSettings;

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

        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified', $sp->getNameIdFormat());
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
    public function shortcutGetters(): void
    {
        $config = new SamlConfiguration(
            idpSettings: $this->idpSettings,
            spSettings: $this->spSettings,
            strict: true,
            debug: false,
        );

        self::assertSame('https://idp.example.com/sso', $config->getIdpSsoUrl());
        self::assertSame('https://idp.example.com/slo', $config->getIdpSloUrl());
        self::assertSame('https://sp.example.com/acs', $config->getSpAcsUrl());
        self::assertSame('https://sp.example.com/slo', $config->getSpSloUrl());
        self::assertSame('https://sp.example.com/metadata', $config->getSpEntityId());
        self::assertSame('https://idp.example.com/metadata', $config->getIdpEntityId());
        self::assertSame('MIICDummyCert==', $config->getIdpX509Cert());
    }

    #[Test]
    public function shortcutGettersWithNullSloUrl(): void
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

        self::assertNull($config->getIdpSloUrl());
        self::assertNull($config->getSpSloUrl());
    }
}
