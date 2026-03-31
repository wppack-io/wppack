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

namespace WpPack\Plugin\ScimPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Role\RoleProvider;
use WpPack\Plugin\ScimPlugin\Admin\ScimSettingsController;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;

#[CoversClass(ScimSettingsController::class)]
final class ScimSettingsControllerTest extends TestCase
{
    private ScimSettingsController $controller;

    protected function setUp(): void
    {
        delete_option(ScimConfiguration::OPTION_NAME);
        $this->controller = new ScimSettingsController(new RoleProvider());
    }

    protected function tearDown(): void
    {
        delete_option(ScimConfiguration::OPTION_NAME);
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

        self::assertArrayHasKey('settings', $data);
        self::assertArrayHasKey('baseUrl', $data);
        self::assertArrayHasKey('roles', $data);
    }

    #[Test]
    public function getSettingsReturnsDefaultValues(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('default', $data['settings']['bearerToken']['source']);
        self::assertFalse($data['settings']['bearerToken']['readonly']);

        self::assertTrue($data['settings']['autoProvision']['value']);
        self::assertSame('subscriber', $data['settings']['defaultRole']['value']);
        self::assertTrue($data['settings']['allowGroupManagement']['value']);
        self::assertFalse($data['settings']['allowUserDeletion']['value']);
        self::assertSame(100, $data['settings']['maxResults']['value']);
    }

    #[Test]
    public function getSettingsReturnsSavedValues(): void
    {
        update_option(ScimConfiguration::OPTION_NAME, [
            'bearerToken' => 'my-token',
            'autoProvision' => false,
            'maxResults' => 50,
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['settings']['bearerToken']['source']);
        // Bearer token should be masked
        self::assertSame(ScimConfiguration::MASKED_VALUE, $data['settings']['bearerToken']['value']);
        self::assertFalse($data['settings']['autoProvision']['value']);
        self::assertSame(50, $data['settings']['maxResults']['value']);
    }

    #[Test]
    public function saveSettingsPersistsOptions(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'bearerToken' => 'new-token',
            'autoProvision' => true,
            'maxResults' => 200,
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option(ScimConfiguration::OPTION_NAME);
        self::assertSame('new-token', $saved['bearerToken']);
        self::assertTrue($saved['autoProvision']);
        self::assertSame(200, $saved['maxResults']);
    }

    #[Test]
    public function saveSettingsSkipsMaskedBearerToken(): void
    {
        update_option(ScimConfiguration::OPTION_NAME, ['bearerToken' => 'original-token']);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'bearerToken' => ScimConfiguration::MASKED_VALUE,
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(ScimConfiguration::OPTION_NAME);
        self::assertSame('original-token', $saved['bearerToken']);
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

        $saved = get_option(ScimConfiguration::OPTION_NAME);
        self::assertArrayNotHasKey('defaultRole', $saved);
    }

    #[Test]
    public function saveSettingsClampsMaxResults(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'maxResults' => 5000,
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(ScimConfiguration::OPTION_NAME);
        self::assertSame(1000, $saved['maxResults']);
    }

    #[Test]
    public function saveSettingsClampsMaxResultsMinimum(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'maxResults' => -10,
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(ScimConfiguration::OPTION_NAME);
        self::assertSame(1, $saved['maxResults']);
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

        $saved = get_option(ScimConfiguration::OPTION_NAME);
        self::assertSame('subscriber', $saved['defaultRole']);
    }
}
