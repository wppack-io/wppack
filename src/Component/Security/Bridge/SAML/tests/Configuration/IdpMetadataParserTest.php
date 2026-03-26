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
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpMetadataParser;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;

#[CoversClass(IdpMetadataParser::class)]
final class IdpMetadataParserTest extends TestCase
{
    private IdpMetadataParser $parser;

    protected function setUp(): void
    {
        $this->parser = new IdpMetadataParser();
    }

    #[Test]
    public function parseXmlReturnsIdpSettings(): void
    {
        $xml = $this->createIdpMetadataXml(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: 'https://idp.example.com/slo',
            x509Cert: 'MIICDummyCertBase64==',
        );

        $settings = $this->parser->parseXml($xml);

        self::assertInstanceOf(IdpSettings::class, $settings);
        self::assertSame('https://idp.example.com/metadata', $settings->getEntityId());
        self::assertSame('https://idp.example.com/sso', $settings->getSsoUrl());
        self::assertSame('https://idp.example.com/slo', $settings->getSloUrl());
        self::assertSame('MIICDummyCertBase64==', $settings->getX509Cert());
        self::assertNull($settings->getCertFingerprint());
    }

    #[Test]
    public function parseXmlWithoutSloUrlReturnNullSloUrl(): void
    {
        $xml = $this->createIdpMetadataXml(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: null,
            x509Cert: 'MIICDummyCertBase64==',
        );

        $settings = $this->parser->parseXml($xml);

        self::assertNull($settings->getSloUrl());
    }

    #[Test]
    public function parseXmlWithMultiCertUsesFirstSigningCert(): void
    {
        $xml = $this->createIdpMetadataXmlWithMultiCert(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            signingCerts: ['MIICSigningCert1==', 'MIICSigningCert2=='],
            encryptionCert: 'MIICEncryptionCert==',
        );

        $settings = $this->parser->parseXml($xml);

        self::assertSame('MIICSigningCert1==', $settings->getX509Cert());
    }

    #[Test]
    public function parseXmlThrowsExceptionForInvalidXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse IdP metadata XML');

        $this->parser->parseXml('not valid xml');
    }

    #[Test]
    public function parseFileReturnsIdpSettings(): void
    {
        $xml = $this->createIdpMetadataXml(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: 'https://idp.example.com/slo',
            x509Cert: 'MIICDummyCertBase64==',
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'idp_metadata_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $xml);

        try {
            $settings = $this->parser->parseFile($tmpFile);

            self::assertInstanceOf(IdpSettings::class, $settings);
            self::assertSame('https://idp.example.com/metadata', $settings->getEntityId());
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function parseFileThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IdP metadata file not found or not readable');

        $this->parser->parseFile('/non/existent/path/metadata.xml');
    }

    private function createIdpMetadataXml(
        string $entityId,
        string $ssoUrl,
        ?string $sloUrl,
        string $x509Cert,
    ): string {
        $sloDescriptor = '';
        if ($sloUrl !== null) {
            $sloDescriptor = \sprintf(
                '<md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="%s"/>',
                $sloUrl,
            );
        }

        return \sprintf(
            '<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="%s">
  <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
    <md:KeyDescriptor use="signing">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
          <ds:X509Certificate>%s</ds:X509Certificate>
        </ds:X509Data>
      </ds:KeyInfo>
    </md:KeyDescriptor>
    %s
    <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="%s"/>
  </md:IDPSSODescriptor>
</md:EntityDescriptor>',
            $entityId,
            $x509Cert,
            $sloDescriptor,
            $ssoUrl,
        );
    }

    /**
     * @param list<string> $signingCerts
     */
    private function createIdpMetadataXmlWithMultiCert(
        string $entityId,
        string $ssoUrl,
        array $signingCerts,
        string $encryptionCert,
    ): string {
        $keyDescriptors = '';
        foreach ($signingCerts as $cert) {
            $keyDescriptors .= \sprintf(
                '    <md:KeyDescriptor use="signing">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
          <ds:X509Certificate>%s</ds:X509Certificate>
        </ds:X509Data>
      </ds:KeyInfo>
    </md:KeyDescriptor>
',
                $cert,
            );
        }

        $keyDescriptors .= \sprintf(
            '    <md:KeyDescriptor use="encryption">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
          <ds:X509Certificate>%s</ds:X509Certificate>
        </ds:X509Data>
      </ds:KeyInfo>
    </md:KeyDescriptor>',
            $encryptionCert,
        );

        return \sprintf(
            '<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="%s">
  <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
%s
    <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="%s"/>
  </md:IDPSSODescriptor>
</md:EntityDescriptor>',
            $entityId,
            $keyDescriptors,
            $ssoUrl,
        );
    }
}
