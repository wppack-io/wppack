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
use WpPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;

#[CoversClass(SpMetadataExporter::class)]
final class SpMetadataExporterTest extends TestCase
{
    private function createExporter(): SpMetadataExporter
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

        return new SpMetadataExporter($configuration);
    }

    #[Test]
    public function toXmlReturnsValidXml(): void
    {
        $exporter = $this->createExporter();
        $xml = $exporter->toXml();

        self::assertIsString($xml);

        $doc = new \DOMDocument();
        $loaded = @$doc->loadXML($xml);
        self::assertTrue($loaded);
    }

    #[Test]
    public function toXmlContainsExpectedElements(): void
    {
        $exporter = $this->createExporter();
        $xml = $exporter->toXml();

        self::assertStringContainsString('EntityDescriptor', $xml);
        self::assertStringContainsString('https://sp.example.com/metadata', $xml);
        self::assertStringContainsString('https://sp.example.com/acs', $xml);
        self::assertStringContainsString('https://sp.example.com/slo', $xml);
        self::assertStringContainsString('NameIDFormat', $xml);
    }

    #[Test]
    public function exportToFileWritesXml(): void
    {
        $exporter = $this->createExporter();

        $tmpFile = tempnam(sys_get_temp_dir(), 'sp_metadata_');
        self::assertNotFalse($tmpFile);

        try {
            $exporter->exportToFile($tmpFile);

            $content = file_get_contents($tmpFile);
            self::assertNotFalse($content);
            self::assertStringContainsString('EntityDescriptor', $content);
            self::assertStringContainsString('https://sp.example.com/metadata', $content);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function exportToFileThrowsExceptionForUnwritablePath(): void
    {
        $exporter = $this->createExporter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write SP metadata to file');

        $exporter->exportToFile('/non/existent/directory/metadata.xml');
    }
}
