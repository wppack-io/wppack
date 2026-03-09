<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use OneLogin\Saml2\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;

#[CoversClass(SamlMetadataController::class)]
final class SamlMetadataControllerTest extends TestCase
{
    #[Test]
    public function getMetadataXml(): void
    {
        $configuration = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com/metadata',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: 'MIICDummyCert==',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com/metadata',
                acsUrl: 'https://sp.example.com/acs',
                sloUrl: 'https://sp.example.com/slo',
            ),
        );

        $controller = new SamlMetadataController($configuration);
        $xml = $controller->getMetadataXml();

        self::assertIsString($xml);
        self::assertStringContainsString('https://sp.example.com/metadata', $xml);
        self::assertStringContainsString('https://sp.example.com/acs', $xml);
        self::assertStringContainsString('EntityDescriptor', $xml);
    }
}
