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
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpMetadataParser;
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;

#[CoversClass(IdpMetadataParser::class)]
final class IdpMetadataParserTest extends TestCase
{
    private IdpMetadataParser $parser;

    /**
     * A valid self-signed X.509 certificate (base64 body only, no PEM headers).
     * LightSAML requires real certificates when parsing KeyDescriptor elements.
     */
    private static string $testCert;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Test IdP'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 365);
        openssl_x509_export($cert, $pem);

        $lines = explode("\n", trim($pem));
        array_shift($lines); // remove -----BEGIN CERTIFICATE-----
        array_pop($lines);   // remove -----END CERTIFICATE-----
        self::$testCert = implode('', $lines);
    }

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
            x509Cert: self::$testCert,
        );

        $settings = $this->parser->parseXml($xml);

        self::assertInstanceOf(IdpSettings::class, $settings);
        self::assertSame('https://idp.example.com/metadata', $settings->getEntityId());
        self::assertSame('https://idp.example.com/sso', $settings->getSsoUrl());
        self::assertSame('https://idp.example.com/slo', $settings->getSloUrl());
        self::assertSame(self::$testCert, $settings->getX509Cert());
        self::assertNull($settings->getCertFingerprint());
    }

    #[Test]
    public function parseXmlWithoutSloUrlReturnNullSloUrl(): void
    {
        $xml = $this->createIdpMetadataXml(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            sloUrl: null,
            x509Cert: self::$testCert,
        );

        $settings = $this->parser->parseXml($xml);

        self::assertNull($settings->getSloUrl());
    }

    #[Test]
    public function parseXmlWithMultiCertUsesFirstSigningCert(): void
    {
        // Generate a second certificate to test multi-cert parsing
        $key2 = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        $csr2 = openssl_csr_new(['commonName' => 'Test IdP 2'], $key2);
        $cert2 = openssl_csr_sign($csr2, null, $key2, 365);
        openssl_x509_export($cert2, $pem2);
        $lines2 = explode("\n", trim($pem2));
        array_shift($lines2);
        array_pop($lines2);
        $secondCert = implode('', $lines2);

        $xml = $this->createIdpMetadataXmlWithMultiCert(
            entityId: 'https://idp.example.com/metadata',
            ssoUrl: 'https://idp.example.com/sso',
            signingCerts: [self::$testCert, $secondCert],
            encryptionCert: $secondCert,
        );

        $settings = $this->parser->parseXml($xml);

        self::assertSame(self::$testCert, $settings->getX509Cert());
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
            x509Cert: self::$testCert,
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
