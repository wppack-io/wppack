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

namespace WpPack\Plugin\OAuthLoginPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsController;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;

#[CoversClass(OAuthLoginSettingsController::class)]
final class OAuthLoginSettingsControllerTest extends TestCase
{
    private OAuthLoginSettingsController $controller;

    protected function setUp(): void
    {
        delete_option(OAuthLoginConfiguration::OPTION_NAME);

        $config = new OAuthLoginConfiguration(providers: []);
        $this->controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );
    }

    protected function tearDown(): void
    {
        delete_option(OAuthLoginConfiguration::OPTION_NAME);
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
        self::assertArrayHasKey('icons', $data);
        self::assertArrayHasKey('styles', $data);
        self::assertArrayHasKey('definitions', $data);
        self::assertArrayHasKey('global', $data);
        self::assertArrayHasKey('providers', $data);
    }

    #[Test]
    public function getSettingsReturnsDefinitions(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('google', $data['definitions']);
        self::assertSame('Google', $data['definitions']['google']['label']);
    }

    #[Test]
    public function getSettingsReturnsGlobalFieldsWithDefaultSource(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('default', $data['global']['ssoOnly']['source']);
        self::assertFalse($data['global']['ssoOnly']['readonly']);
    }

    #[Test]
    public function getSettingsReturnsGlobalFieldsWithOptionSource(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, ['ssoOnly' => true]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['global']['ssoOnly']['source']);
    }

    #[Test]
    public function getSettingsReturnsProviderWithMaskedSecret(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'gid',
            clientSecret: 'gsecret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(providers: ['google' => $google]);
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('google', $data['providers']);
        self::assertSame(OAuthLoginConfiguration::MASKED_VALUE, $data['providers']['google']['fields']['client_secret']);
        self::assertSame('gid', $data['providers']['google']['fields']['client_id']);
    }

    #[Test]
    public function saveSettingsPersistsProviders(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'providers' => [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'new-id',
                    'client_secret' => 'new-secret',
                    'label' => 'Google',
                ],
            ],
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertSame('new-id', $saved['providers']['google']['client_id']);
    }

    #[Test]
    public function saveSettingsPreservesMaskedSecrets(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'gid',
                    'client_secret' => 'original-secret',
                    'label' => 'Google',
                ],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'providers' => [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'gid',
                    'client_secret' => OAuthLoginConfiguration::MASKED_VALUE,
                    'label' => 'Google',
                ],
            ],
        ]));

        $controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertSame('original-secret', $saved['providers']['google']['client_secret']);
    }

    #[Test]
    public function saveSettingsDeletesProviders(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => ['type' => 'google', 'client_id' => 'gid', 'client_secret' => 'gs', 'label' => 'Google'],
                'github' => ['type' => 'github', 'client_id' => 'hid', 'client_secret' => 'hs', 'label' => 'GitHub'],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'deletedProviders' => ['github'],
        ]));

        $controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertArrayHasKey('google', $saved['providers']);
        self::assertArrayNotHasKey('github', $saved['providers']);
    }

    #[Test]
    public function saveSettingsReordersProviders(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => ['type' => 'google', 'client_id' => 'gid', 'client_secret' => 'gs', 'label' => 'Google'],
                'github' => ['type' => 'github', 'client_id' => 'hid', 'client_secret' => 'hs', 'label' => 'GitHub'],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'providerOrder' => ['github', 'google'],
        ]));

        $controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        $keys = array_keys($saved['providers']);
        self::assertSame(['github', 'google'], $keys);
    }

    #[Test]
    public function saveSettingsReorderAppendsProvidersNotInOrderList(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => ['type' => 'google', 'client_id' => 'gid', 'client_secret' => 'gs', 'label' => 'Google'],
                'github' => ['type' => 'github', 'client_id' => 'hid', 'client_secret' => 'hs', 'label' => 'GitHub'],
                'apple' => ['type' => 'apple', 'client_id' => 'aid', 'client_secret' => 'as', 'label' => 'Apple'],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'providerOrder' => ['github'],
        ]));

        $controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        $keys = array_keys($saved['providers']);
        // github first (from order), then google and apple appended
        self::assertSame('github', $keys[0]);
        self::assertCount(3, $keys);
    }

    #[Test]
    public function saveSettingsSkipsInvalidPath(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'global' => ['authorizePath' => 'no-slash'],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertArrayNotHasKey('authorizePath', $saved);
    }

    #[Test]
    public function saveSettingsAcceptsValidGlobal(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'global' => [
                'ssoOnly' => true,
                'autoProvision' => false,
                'buttonDisplay' => 'icon-only',
            ],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertTrue($saved['ssoOnly']);
        self::assertFalse($saved['autoProvision']);
        self::assertSame('icon-only', $saved['buttonDisplay']);
    }

    #[Test]
    public function saveSettingsAcceptsEmptyPath(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'global' => ['authorizePath' => ''],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertSame('', $saved['authorizePath']);
    }

    #[Test]
    public function saveSettingsAcceptsValidUrlField(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'providers' => [
                'oidc' => [
                    'type' => 'oidc',
                    'client_id' => 'oidc-id',
                    'client_secret' => 'oidc-secret',
                    'label' => 'OIDC',
                    'discovery_url' => 'https://idp.example.com/.well-known/openid-configuration',
                ],
            ],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        self::assertSame(
            'https://idp.example.com/.well-known/openid-configuration',
            $saved['providers']['oidc']['discovery_url'],
        );
    }

    #[Test]
    public function getSettingsReturnsIconsAndStyles(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertNotEmpty($data['icons']);
        self::assertNotEmpty($data['styles']);
        self::assertArrayHasKey('google', $data['icons']);
        self::assertArrayHasKey('google', $data['styles']);
    }

    #[Test]
    public function getSettingsReturnsProviderSourceOption(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'gid',
                    'client_secret' => 'gs',
                    'label' => 'Google',
                ],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['providers']['google']['source']);
        self::assertFalse($data['providers']['google']['readonly']);
    }

    #[Test]
    public function saveSettingsRemovesMaskedSecretWhenNoSavedValue(): void
    {
        // No pre-existing providers
        delete_option(OAuthLoginConfiguration::OPTION_NAME);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'providers' => [
                'newprov' => [
                    'type' => 'google',
                    'client_id' => 'id',
                    'client_secret' => OAuthLoginConfiguration::MASKED_VALUE,
                    'label' => 'New',
                ],
            ],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(OAuthLoginConfiguration::OPTION_NAME);
        // Masked secret with no saved value → field should be unset
        self::assertArrayNotHasKey('client_secret', $saved['providers']['newprov']);
    }

    #[Test]
    public function getSettingsReturnsProviderFieldsWithScopesAndButtonStyle(): void
    {
        $oidc = new ProviderConfiguration(
            name: 'oidc',
            type: 'oidc',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'OIDC',
            scopes: ['openid', 'profile'],
            buttonStyle: 'brand',
        );

        $config = new OAuthLoginConfiguration(providers: ['oidc' => $oidc]);
        $controller = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
        );

        $response = $controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        $fields = $data['providers']['oidc']['fields'];
        self::assertSame('openid profile', $fields['scopes']);
        self::assertSame('brand', $fields['button_style']);
    }

}
