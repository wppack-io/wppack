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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsController;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;

#[CoversClass(PasskeyLoginSettingsController::class)]
final class PasskeyLoginSettingsControllerTest extends TestCase
{
    private PasskeyLoginSettingsController $controller;

    protected function setUp(): void
    {
        delete_option(PasskeyLoginConfiguration::OPTION_NAME);
        $this->controller = new PasskeyLoginSettingsController();
    }

    protected function tearDown(): void
    {
        delete_option(PasskeyLoginConfiguration::OPTION_NAME);
    }

    /**
     * @param array<string, mixed> $body
     */
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
    public function getSettingsReturnsSiteUrlAndDefaults(): void
    {
        $response = $this->controller->getSettings();

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertArrayHasKey('siteUrl', $body);
        self::assertArrayHasKey('settings', $body);

        self::assertTrue($body['settings']['enabled']['value']);
        self::assertSame('default', $body['settings']['enabled']['source']);
        self::assertSame(60000, $body['settings']['timeout']['value']);
        self::assertSame([-7, -257], $body['settings']['algorithms']['value']);
    }

    #[Test]
    public function savedOptionIsReportedAsSource(): void
    {
        update_option(PasskeyLoginConfiguration::OPTION_NAME, ['rpName' => 'Custom Name']);

        $body = $this->decode($this->controller->getSettings());

        self::assertSame('Custom Name', $body['settings']['rpName']['value']);
        self::assertSame('option', $body['settings']['rpName']['source']);
        self::assertFalse($body['settings']['rpName']['readonly']);
    }

    #[Test]
    public function saveSettingsAcceptsValidButtonDisplayOnly(): void
    {
        $this->controller->saveSettings($this->request([
            'buttonDisplay' => 'icon-only',
        ]));

        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame('icon-only', $saved['buttonDisplay']);
    }

    #[Test]
    public function saveSettingsRejectsInvalidButtonDisplay(): void
    {
        $this->controller->saveSettings($this->request([
            'buttonDisplay' => 'exotic-bogus-style',
        ]));

        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME, []);
        self::assertArrayNotHasKey('buttonDisplay', $saved);
    }

    #[Test]
    public function saveSettingsAcceptsValidTimeoutAndRejectsOutOfRange(): void
    {
        $this->controller->saveSettings($this->request(['timeout' => 30000]));
        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame(30000, $saved['timeout']);

        $this->controller->saveSettings($this->request(['timeout' => 100]));
        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame(30000, $saved['timeout'], 'out-of-range timeout ignored');

        $this->controller->saveSettings($this->request(['timeout' => 999999]));
        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame(30000, $saved['timeout']);
    }

    #[Test]
    public function saveSettingsValidatesAlgorithms(): void
    {
        $this->controller->saveSettings($this->request([
            'algorithms' => [-7, -257, -8],
        ]));
        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame([-7, -257, -8], $saved['algorithms']);

        // Invalid COSE id dropped entirely (no partial save)
        $this->controller->saveSettings($this->request([
            'algorithms' => [-7, 9999],
        ]));
        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame([-7, -257, -8], $saved['algorithms']);

        // Empty list rejected
        $this->controller->saveSettings($this->request([
            'algorithms' => [],
        ]));
        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME);
        self::assertSame([-7, -257, -8], $saved['algorithms']);
    }

    #[Test]
    public function saveSettingsRejectsInvalidUserVerification(): void
    {
        $this->controller->saveSettings($this->request([
            'requireUserVerification' => 'magical',
        ]));

        $saved = get_option(PasskeyLoginConfiguration::OPTION_NAME, []);
        self::assertArrayNotHasKey('requireUserVerification', $saved);
    }

    #[Test]
    public function saveSettingsValidatesMaxCredentialsRange(): void
    {
        $this->controller->saveSettings($this->request(['maxCredentialsPerUser' => 5]));
        self::assertSame(5, get_option(PasskeyLoginConfiguration::OPTION_NAME)['maxCredentialsPerUser']);

        $this->controller->saveSettings($this->request(['maxCredentialsPerUser' => 100]));
        self::assertSame(5, get_option(PasskeyLoginConfiguration::OPTION_NAME)['maxCredentialsPerUser']);

        $this->controller->saveSettings($this->request(['maxCredentialsPerUser' => 0]));
        self::assertSame(5, get_option(PasskeyLoginConfiguration::OPTION_NAME)['maxCredentialsPerUser']);
    }

    #[Test]
    public function saveSettingsReturnsFreshStateAfterPersist(): void
    {
        $response = $this->controller->saveSettings($this->request([
            'rpName' => 'My Site',
            'rpId' => 'example.test',
        ]));

        $body = $this->decode($response);
        self::assertSame('My Site', $body['settings']['rpName']['value']);
        self::assertSame('example.test', $body['settings']['rpId']['value']);
        self::assertSame('option', $body['settings']['rpName']['source']);
    }
}
