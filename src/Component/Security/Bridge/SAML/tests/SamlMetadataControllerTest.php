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

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use OneLogin\Saml2\Error;
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
    private function createValidConfiguration(): SamlConfiguration
    {
        return new SamlConfiguration(
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
    }

    #[Test]
    public function getMetadataXml(): void
    {
        $configuration = $this->createValidConfiguration();

        $controller = new SamlMetadataController($configuration);
        $xml = $controller->getMetadataXml();

        self::assertIsString($xml);
        self::assertStringContainsString('https://sp.example.com/metadata', $xml);
        self::assertStringContainsString('https://sp.example.com/acs', $xml);
        self::assertStringContainsString('EntityDescriptor', $xml);
    }

    #[Test]
    public function getMetadataXmlReturnsValidXml(): void
    {
        $configuration = $this->createValidConfiguration();

        $controller = new SamlMetadataController($configuration);
        $xml = $controller->getMetadataXml();

        // Verify it's valid XML
        $doc = new \DOMDocument();
        $loaded = @$doc->loadXML($xml);
        self::assertTrue($loaded);
    }

    #[Test]
    public function getMetadataXmlContainsSloUrl(): void
    {
        $configuration = $this->createValidConfiguration();

        $controller = new SamlMetadataController($configuration);
        $xml = $controller->getMetadataXml();

        self::assertStringContainsString('https://sp.example.com/slo', $xml);
    }

    #[Test]
    public function getMetadataXmlThrowsExceptionForInvalidMetadata(): void
    {
        // Create a configuration that produces invalid metadata
        // Use an empty entity ID which should fail validation
        $configuration = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: '',
                ssoUrl: '',
                sloUrl: null,
                x509Cert: '',
            ),
            spSettings: new SpSettings(
                entityId: '',
                acsUrl: '',
                sloUrl: null,
            ),
        );

        $controller = new SamlMetadataController($configuration);

        // Empty settings cause OneLogin\Saml2\Error during Settings construction
        // before the metadata validation in getMetadataXml() is reached.
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Invalid array settings');

        $controller->getMetadataXml();
    }

    #[Test]
    public function getMetadataXmlContainsNameIdFormat(): void
    {
        $configuration = $this->createValidConfiguration();

        $controller = new SamlMetadataController($configuration);
        $xml = $controller->getMetadataXml();

        // SP metadata should contain NameIDFormat element
        self::assertStringContainsString('NameIDFormat', $xml);
    }

    #[Test]
    public function getMetadataXmlReturnsNonEmptyString(): void
    {
        $configuration = $this->createValidConfiguration();

        $controller = new SamlMetadataController($configuration);
        $xml = $controller->getMetadataXml();

        self::assertNotEmpty($xml);
        self::assertGreaterThan(100, \strlen($xml));
    }

    #[Test]
    public function getMetadataXmlIsIdempotent(): void
    {
        $configuration = $this->createValidConfiguration();

        $controller = new SamlMetadataController($configuration);

        $xml1 = $controller->getMetadataXml();
        $xml2 = $controller->getMetadataXml();

        self::assertSame($xml1, $xml2);
    }
}
