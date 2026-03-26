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

namespace WpPack\Plugin\SamlLoginPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;

#[CoversClass(SamlLoginConfiguration::class)]
final class SamlLoginConfigurationTest extends TestCase
{
    /** @var list<string> */
    private array $envVars = [
        'SAML_IDP_ENTITY_ID',
        'SAML_IDP_SSO_URL',
        'SAML_IDP_X509_CERT',
        'SAML_IDP_X509_CERT_FILE',
        'SAML_IDP_SLO_URL',
        'SAML_IDP_CERT_FINGERPRINT',
        'SAML_SP_ENTITY_ID',
        'SAML_SP_ACS_URL',
        'SAML_SP_SLO_URL',
        'SAML_SP_NAMEID_FORMAT',
        'SAML_STRICT',
        'SAML_DEBUG',
        'SAML_WANT_ASSERTIONS_SIGNED',
        'SAML_ALLOW_REPEAT_ATTRIBUTE_NAME',
        'SAML_AUTO_PROVISION',
        'SAML_DEFAULT_ROLE',
        'SAML_ROLE_ATTRIBUTE',
        'SAML_ROLE_MAPPING',
        'SAML_ADD_USER_TO_BLOG',
        'SAML_METADATA_PATH',
        'SAML_ACS_PATH',
        'SAML_SLO_PATH',
    ];

    protected function tearDown(): void
    {
        foreach ($this->envVars as $var) {
            putenv($var);
            unset($_ENV[$var]);
        }
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIID...',
            idpSloUrl: 'https://idp.example.com/slo',
            idpCertFingerprint: 'AA:BB:CC',
            spEntityId: 'https://sp.example.com',
            spAcsUrl: 'https://sp.example.com/saml/acs',
            spSloUrl: 'https://sp.example.com/saml/slo',
            spNameIdFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            strict: false,
            debug: true,
            wantAssertionsSigned: false,
            allowRepeatAttributeName: true,
            autoProvision: true,
            defaultRole: 'editor',
            roleAttribute: 'groups',
            roleMapping: ['admins' => 'administrator'],
            addUserToBlog: false,
            metadataPath: '/sso/metadata',
            acsPath: '/sso/acs',
            sloPath: '/sso/slo',
        );

        self::assertSame('https://idp.example.com', $config->idpEntityId);
        self::assertSame('https://idp.example.com/sso', $config->idpSsoUrl);
        self::assertSame('MIID...', $config->idpX509Cert);
        self::assertSame('https://idp.example.com/slo', $config->idpSloUrl);
        self::assertSame('AA:BB:CC', $config->idpCertFingerprint);
        self::assertSame('https://sp.example.com', $config->spEntityId);
        self::assertSame('https://sp.example.com/saml/acs', $config->spAcsUrl);
        self::assertSame('https://sp.example.com/saml/slo', $config->spSloUrl);
        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $config->spNameIdFormat);
        self::assertFalse($config->strict);
        self::assertTrue($config->debug);
        self::assertFalse($config->wantAssertionsSigned);
        self::assertTrue($config->allowRepeatAttributeName);
        self::assertTrue($config->autoProvision);
        self::assertSame('editor', $config->defaultRole);
        self::assertSame('groups', $config->roleAttribute);
        self::assertSame(['admins' => 'administrator'], $config->roleMapping);
        self::assertFalse($config->addUserToBlog);
        self::assertSame('/sso/metadata', $config->metadataPath);
        self::assertSame('/sso/acs', $config->acsPath);
        self::assertSame('/sso/slo', $config->sloPath);
    }

    #[Test]
    public function constructorDefaults(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIID...',
        );

        self::assertNull($config->idpSloUrl);
        self::assertNull($config->idpCertFingerprint);
        self::assertSame('', $config->spEntityId);
        self::assertSame('', $config->spAcsUrl);
        self::assertSame('', $config->spSloUrl);
        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $config->spNameIdFormat);
        self::assertTrue($config->strict);
        self::assertFalse($config->debug);
        self::assertTrue($config->wantAssertionsSigned);
        self::assertFalse($config->allowRepeatAttributeName);
        self::assertFalse($config->autoProvision);
        self::assertSame('subscriber', $config->defaultRole);
        self::assertNull($config->roleAttribute);
        self::assertNull($config->roleMapping);
        self::assertTrue($config->addUserToBlog);
        self::assertSame('/saml/metadata', $config->metadataPath);
        self::assertSame('/saml/acs', $config->acsPath);
        self::assertSame('/saml/slo', $config->sloPath);
    }

    #[Test]
    public function fromEnvironmentReadsEnvVariables(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_AUTO_PROVISION=true');
        putenv('SAML_DEFAULT_ROLE=editor');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame('https://idp.example.com', $config->idpEntityId);
        self::assertSame('https://idp.example.com/sso', $config->idpSsoUrl);
        self::assertSame('MIICert', $config->idpX509Cert);
        self::assertTrue($config->autoProvision);
        self::assertSame('editor', $config->defaultRole);
    }

    #[Test]
    public function fromEnvironmentReadsEnvSuperglobal(): void
    {
        $_ENV['SAML_IDP_ENTITY_ID'] = 'https://idp.example.com';
        $_ENV['SAML_IDP_SSO_URL'] = 'https://idp.example.com/sso';
        $_ENV['SAML_IDP_X509_CERT'] = 'MIICert';

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame('https://idp.example.com', $config->idpEntityId);
    }

    #[Test]
    public function fromEnvironmentThrowsWhenEntityIdMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable "SAML_IDP_ENTITY_ID" is not set.');

        SamlLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentThrowsWhenSsoUrlMissing(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable "SAML_IDP_SSO_URL" is not set.');

        SamlLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentThrowsWhenCertMissing(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable "SAML_IDP_X509_CERT" is not set.');

        SamlLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentConvertsCertLiteralNewlines(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=-----BEGIN CERTIFICATE-----\nMIICert\n-----END CERTIFICATE-----');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertStringContainsString("\n", $config->idpX509Cert);
        self::assertStringNotContainsString('\\n', $config->idpX509Cert);
    }

    #[Test]
    public function fromEnvironmentReadsCertFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'saml_cert_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'MIICertFromFile');

        try {
            putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
            putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
            putenv('SAML_IDP_X509_CERT_FILE=' . $tmpFile);

            $config = SamlLoginConfiguration::fromEnvironment();

            self::assertSame('MIICertFromFile', $config->idpX509Cert);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentCertFileTakesPriorityOverCertValue(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'saml_cert_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'CertFromFile');

        try {
            putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
            putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
            putenv('SAML_IDP_X509_CERT=CertFromEnv');
            putenv('SAML_IDP_X509_CERT_FILE=' . $tmpFile);

            $config = SamlLoginConfiguration::fromEnvironment();

            self::assertSame('CertFromFile', $config->idpX509Cert);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentThrowsWhenCertFileNotFound(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT_FILE=/nonexistent/cert.pem');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        SamlLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentResolvesNameIdFormatShortName(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_SP_NAMEID_FORMAT=persistent');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $config->spNameIdFormat);
    }

    #[Test]
    public function fromEnvironmentPassesThroughFullNameIdFormatUrn(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_SP_NAMEID_FORMAT=urn:oasis:names:tc:SAML:2.0:nameid-format:persistent');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $config->spNameIdFormat);
    }

    #[Test]
    public function fromEnvironmentParsesRoleMapping(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_ROLE_MAPPING={"admins":"administrator","editors":"editor"}');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame(['admins' => 'administrator', 'editors' => 'editor'], $config->roleMapping);
    }

    #[Test]
    public function fromEnvironmentThrowsOnInvalidRoleMappingJson(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_ROLE_MAPPING={invalid}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAML_ROLE_MAPPING is not valid JSON.');

        SamlLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentBoolDefaults(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertTrue($config->strict);
        self::assertFalse($config->debug);
        self::assertTrue($config->wantAssertionsSigned);
        self::assertFalse($config->allowRepeatAttributeName);
        self::assertFalse($config->autoProvision);
        self::assertTrue($config->addUserToBlog);
    }

    #[Test]
    public function fromEnvironmentReadsPathEnvVars(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_METADATA_PATH=/sso/metadata');
        putenv('SAML_ACS_PATH=/sso/acs');
        putenv('SAML_SLO_PATH=/sso/slo');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame('/sso/metadata', $config->metadataPath);
        self::assertSame('/sso/acs', $config->acsPath);
        self::assertSame('/sso/slo', $config->sloPath);
    }

    #[Test]
    public function fromEnvironmentReadsBoolEnvValues(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_STRICT=false');
        putenv('SAML_DEBUG=true');
        putenv('SAML_AUTO_PROVISION=yes');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertFalse($config->strict);
        self::assertTrue($config->debug);
        self::assertTrue($config->autoProvision);
    }

    #[Test]
    public function fromEnvironmentReadsOptionalSettings(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://idp.example.com');
        putenv('SAML_IDP_SSO_URL=https://idp.example.com/sso');
        putenv('SAML_IDP_X509_CERT=MIICert');
        putenv('SAML_IDP_SLO_URL=https://idp.example.com/slo');
        putenv('SAML_IDP_CERT_FINGERPRINT=AA:BB:CC');
        putenv('SAML_SP_ENTITY_ID=https://sp.example.com');
        putenv('SAML_ROLE_ATTRIBUTE=groups');

        $config = SamlLoginConfiguration::fromEnvironment();

        self::assertSame('https://idp.example.com/slo', $config->idpSloUrl);
        self::assertSame('AA:BB:CC', $config->idpCertFingerprint);
        self::assertSame('https://sp.example.com', $config->spEntityId);
        self::assertSame('groups', $config->roleAttribute);
    }
}
