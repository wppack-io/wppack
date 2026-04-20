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

namespace WPPack\Plugin\RoleProvisioningPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\RoleProvider;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;

#[CoversClass(RoleProvisioningSettingsController::class)]
final class RoleProvisioningSettingsControllerTest extends TestCase
{
    private RoleProvisioningSettingsController $controller;

    protected function setUp(): void
    {
        delete_option(RoleProvisioningConfiguration::OPTION_NAME);
        $this->controller = new RoleProvisioningSettingsController(new RoleProvider());
    }

    protected function tearDown(): void
    {
        delete_option(RoleProvisioningConfiguration::OPTION_NAME);
    }

    private function request(array $body = []): \WP_REST_Request
    {
        $req = new \WP_REST_Request();
        $req->set_header('content-type', 'application/json');
        $req->set_body(json_encode($body, \JSON_THROW_ON_ERROR));

        return $req;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function getSettingsReturnsDefaultsWhenNoOptionSet(): void
    {
        $response = $this->controller->getSettings();

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);

        self::assertTrue($body['settings']['enabled']['value']);
        self::assertSame('default', $body['settings']['enabled']['source']);
        self::assertFalse($body['settings']['addUserToBlog']['value']);
        self::assertFalse($body['settings']['syncOnLogin']['value']);
        self::assertSame(['administrator'], $body['settings']['protectedRoles']['value']);
        self::assertSame([], $body['settings']['rules']['value']);
        self::assertArrayHasKey('roles', $body);
        self::assertArrayHasKey('isMultisite', $body);
    }

    #[Test]
    public function getSettingsMarksSavedFieldsWithOptionSource(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, [
            'enabled' => false,
            'rules' => [],
        ]);

        $body = $this->decode($this->controller->getSettings());

        self::assertFalse($body['settings']['enabled']['value']);
        self::assertSame('option', $body['settings']['enabled']['source']);
        self::assertSame('option', $body['settings']['rules']['source']);
        self::assertSame('default', $body['settings']['addUserToBlog']['source']);
    }

    #[Test]
    public function saveSettingsPersistsBooleans(): void
    {
        $response = $this->controller->saveSettings($this->request([
            'enabled' => false,
            'addUserToBlog' => true,
            'syncOnLogin' => true,
        ]));

        self::assertSame(200, $response->statusCode);

        $saved = get_option(RoleProvisioningConfiguration::OPTION_NAME);
        self::assertFalse($saved['enabled']);
        self::assertTrue($saved['addUserToBlog']);
        self::assertTrue($saved['syncOnLogin']);
    }

    #[Test]
    public function saveSettingsValidatesRuleConditions(): void
    {
        $this->controller->saveSettings($this->request([
            'rules' => [
                [
                    'role' => 'editor',
                    'conditions' => [
                        ['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev'],
                        ['field' => '', 'operator' => 'equals', 'value' => 'empty-field-dropped'],
                        ['field' => 'user.email', 'operator' => 'not_a_real_op', 'value' => 'also-dropped'],
                    ],
                ],
            ],
        ]));

        $saved = get_option(RoleProvisioningConfiguration::OPTION_NAME);
        self::assertCount(1, $saved['rules']);
        self::assertCount(1, $saved['rules'][0]['conditions'], 'invalid conditions filtered');
        self::assertSame('@wppack.dev', $saved['rules'][0]['conditions'][0]['value']);
    }

    #[Test]
    public function saveSettingsRejectsRuleWithoutRole(): void
    {
        $this->controller->saveSettings($this->request([
            'rules' => [
                ['role' => '', 'conditions' => [['field' => 'x', 'operator' => 'equals', 'value' => 'y']]],
            ],
        ]));

        $saved = get_option(RoleProvisioningConfiguration::OPTION_NAME);
        self::assertSame([], $saved['rules']);
    }

    #[Test]
    public function saveSettingsRejectsRuleWithNoValidConditions(): void
    {
        $this->controller->saveSettings($this->request([
            'rules' => [
                ['role' => 'editor', 'conditions' => []],
            ],
        ]));

        $saved = get_option(RoleProvisioningConfiguration::OPTION_NAME);
        self::assertSame([], $saved['rules']);
    }

    #[Test]
    public function saveSettingsBlogIdsCoercedToInts(): void
    {
        $this->controller->saveSettings($this->request([
            'rules' => [
                [
                    'role' => 'editor',
                    'conditions' => [['field' => 'user.email', 'operator' => 'equals', 'value' => 'x']],
                    'blogIds' => ['1', '2', '7'],
                ],
            ],
        ]));

        $saved = get_option(RoleProvisioningConfiguration::OPTION_NAME);
        self::assertSame([1, 2, 7], $saved['rules'][0]['blogIds']);
    }
}
