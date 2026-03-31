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

namespace WpPack\Plugin\SamlLoginPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsController;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;

#[CoversClass(SamlLoginSettingsController::class)]
final class SamlLoginSettingsControllerTest extends TestCase
{
    private SamlLoginSettingsController $controller;

    protected function setUp(): void
    {
        delete_option('wppack_saml_login');

        $config = new SamlLoginConfiguration(
            idpEntityId: '',
            idpSsoUrl: '',
            idpX509Cert: '',
        );
        $this->controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );
    }

    protected function tearDown(): void
    {
        delete_option('wppack_saml_login');
    }

    #[Test]
    public function extendsAbstractRestController(): void
    {
        self::assertInstanceOf(AbstractRestController::class, $this->controller);
    }

    #[Test]
    public function getSettingsReturnsJsonResponse(): void
    {
        $response = $this->controller->getSettings();

        self::assertSame(200, $response->statusCode);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('siteUrl', $data);
        self::assertArrayHasKey('fields', $data);
    }

    #[Test]
    public function getSettingsReturnsFieldsWithDefaultSource(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('default', $data['fields']['idpEntityId']['source']);
        self::assertFalse($data['fields']['idpEntityId']['readonly']);
        self::assertSame('', $data['fields']['idpEntityId']['value']);
    }

    #[Test]
    public function getSettingsReturnsSavedFieldSource(): void
    {
        update_option('wppack_saml_login', ['idpEntityId' => 'https://idp.example.com']);

        $config = SamlLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['fields']['idpEntityId']['source']);
        self::assertSame('https://idp.example.com', $data['fields']['idpEntityId']['value']);
    }

    #[Test]
    public function getSettingsMasksSensitiveFields(): void
    {
        update_option('wppack_saml_login', ['idpX509Cert' => 'MIIC...cert-data']);

        $config = SamlLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('********', $data['fields']['idpX509Cert']['value']);
    }

    #[Test]
    public function downloadMetadataReturns400WhenNotConfigured(): void
    {
        $response = $this->controller->downloadMetadata();

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function saveSettingsPersistsOptions(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'idpEntityId' => 'https://idp.example.com',
            'idpSsoUrl' => 'https://idp.example.com/sso',
            'autoProvision' => true,
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option('wppack_saml_login');
        self::assertSame('https://idp.example.com', $saved['idpEntityId']);
        self::assertSame('https://idp.example.com/sso', $saved['idpSsoUrl']);
    }

    #[Test]
    public function saveSettingsSkipsMaskedSensitiveFields(): void
    {
        update_option('wppack_saml_login', ['idpX509Cert' => 'original-cert']);

        $config = SamlLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'idpX509Cert' => '********',
        ]));

        $controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertSame('original-cert', $saved['idpX509Cert']);
    }

    #[Test]
    public function saveSettingsSkipsInvalidRole(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'defaultRole' => 'nonexistent_role_xyz',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertArrayNotHasKey('defaultRole', $saved);
    }

    #[Test]
    public function saveSettingsSkipsPathWithoutSlash(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'metadataPath' => 'no-leading-slash',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertArrayNotHasKey('metadataPath', $saved);
    }

    #[Test]
    public function saveSettingsAcceptsValidPath(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'metadataPath' => '/custom/metadata',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertSame('/custom/metadata', $saved['metadataPath']);
    }

    #[Test]
    public function saveSettingsAcceptsValidRole(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'defaultRole' => 'subscriber',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertSame('subscriber', $saved['defaultRole']);
    }

    #[Test]
    public function getSettingsEncodesRoleMappingAsJson(): void
    {
        update_option('wppack_saml_login', [
            'idpEntityId' => 'https://idp.test',
            'roleMapping' => ['admin' => 'administrator'],
        ]);

        $config = SamlLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        // roleMapping should be JSON-encoded string in the response
        self::assertIsString($data['fields']['roleMapping']['value']);
        self::assertStringContainsString('administrator', $data['fields']['roleMapping']['value']);
    }

    #[Test]
    public function saveSettingsAcceptsEmptyPath(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'metadataPath' => '',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertSame('', $saved['metadataPath']);
    }

    #[Test]
    public function saveSettingsPersistsBooleanField(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'autoProvision' => true,
            'ssoOnly' => true,
            'wantAssertionsSigned' => false,
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option('wppack_saml_login');
        self::assertTrue($saved['autoProvision']);
        self::assertTrue($saved['ssoOnly']);
        self::assertFalse($saved['wantAssertionsSigned']);
    }

    #[Test]
    public function downloadMetadataReturnsXmlWhenConfigured(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.test',
            idpSsoUrl: 'https://idp.test/sso',
            idpX509Cert: 'MIICert',
        );

        $samlConfig = new SamlConfiguration(
            new IdpSettings('https://idp.test', 'https://idp.test/sso', null, 'MIICert'),
            new SpSettings('https://sp.test', 'https://sp.test/saml/acs'),
        );
        $exporter = new SpMetadataExporter($samlConfig);

        $controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
            $exporter,
        );

        $response = $controller->downloadMetadata();

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('xml', $response->headers['Content-Type']);
        self::assertStringContainsString('sp-metadata.xml', $response->headers['Content-Disposition']);
    }

    #[Test]
    public function getSettingsReturnsEnvSourceForEnvVariable(): void
    {
        putenv('SAML_IDP_ENTITY_ID=https://env-idp.test');

        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://env-idp.test',
            idpSsoUrl: '',
            idpX509Cert: '',
        );
        $controller = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        // env source for idpEntityId
        if (!\defined('SAML_IDP_ENTITY_ID')) {
            self::assertSame('env', $data['fields']['idpEntityId']['source']);
        }

        putenv('SAML_IDP_ENTITY_ID');
    }

    #[Test]
    public function saveSettingsUpdatesFieldAndReturnsUpdated(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'idpEntityId' => 'https://new-idp.example.com',
            'idpSsoUrl' => 'https://new-idp.example.com/sso',
            'idpX509Cert' => 'MIIC...',
        ]));

        $response = $this->controller->saveSettings($request);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('https://new-idp.example.com', $data['fields']['idpEntityId']['value']);
        self::assertSame('option', $data['fields']['idpEntityId']['source']);
        // Certificate should be masked in response
        self::assertSame('********', $data['fields']['idpX509Cert']['value']);
    }
}
