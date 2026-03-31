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

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsController;
use WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;

#[CoversClass(AmazonMailerSettingsController::class)]
final class AmazonMailerSettingsControllerTest extends TestCase
{
    private AmazonMailerSettingsController $controller;

    protected function setUp(): void
    {
        delete_option(AmazonMailerConfiguration::OPTION_NAME);
        $this->controller = new AmazonMailerSettingsController();
    }

    protected function tearDown(): void
    {
        delete_option(AmazonMailerConfiguration::OPTION_NAME);
        delete_option('wppack_ses_suppression_list');
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

        self::assertArrayHasKey('dsn', $data);
        self::assertArrayHasKey('source', $data);
        self::assertArrayHasKey('readonly', $data);
        self::assertArrayHasKey('definitions', $data);
        self::assertArrayHasKey('suppression', $data);
        self::assertArrayHasKey('awsRegion', $data);
    }

    #[Test]
    public function getSettingsReturnsDefaultSourceWhenEmpty(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('default', $data['source']);
        self::assertFalse($data['readonly']);
        self::assertSame('', $data['dsn']);
    }

    #[Test]
    public function getSettingsReturnsOptionSourceWithMaskedPassword(): void
    {
        update_option(AmazonMailerConfiguration::OPTION_NAME, [
            'dsn' => 'ses+api://KEY:SECRET@default?region=us-east-1',
            'provider' => 'ses+api',
            'fields' => ['accessKey' => 'KEY', 'secretKey' => 'SECRET', 'region' => 'us-east-1'],
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['source']);
        self::assertStringContainsString(AmazonMailerConfiguration::MASKED_VALUE, $data['dsn']);
        self::assertStringNotContainsString('SECRET', $data['dsn']);
    }

    #[Test]
    public function getSettingsReturnsConstantSourceWhenEnvSet(): void
    {
        // If MAILER_DSN constant is already defined from another test, this tests line 85-87
        // Otherwise test the env branch (lines 88-90)
        if (!\defined('MAILER_DSN')) {
            putenv('MAILER_DSN=ses+api://default?region=us-east-1');
        }

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('constant', $data['source']);
        self::assertTrue($data['readonly']);
        self::assertSame(AmazonMailerConfiguration::MASKED_VALUE, $data['dsn']);

        putenv('MAILER_DSN');
    }

    #[Test]
    public function getSettingsReturnsTransportDefinitions(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        // Should have at least ses+api, native, smtp and dsn
        self::assertArrayHasKey('ses+api', $data['definitions']);
        self::assertArrayHasKey('native', $data['definitions']);
        self::assertArrayHasKey('dsn', $data['definitions']);

        // DSN direct input
        self::assertSame('DSN (Direct Input)', $data['definitions']['dsn']['label']);
    }

    #[Test]
    public function getSettingsReturnsSuppression(): void
    {
        update_option('wppack_ses_suppression_list', json_encode([
            ['email' => 'bounced@example.com', 'reason' => 'bounce'],
        ]));

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertCount(1, $data['suppression']);
        self::assertSame('bounced@example.com', $data['suppression'][0]['email']);
    }

    #[Test]
    public function saveSettingsPersistsDsnProvider(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'dsn',
            'fields' => ['dsn' => 'ses+api://KEY:SECRET@default?region=us-east-1'],
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertSame('ses+api://KEY:SECRET@default?region=us-east-1', $saved['dsn']);
        self::assertSame('dsn', $saved['provider']);
    }

    #[Test]
    public function saveSettingsSkipsMaskedDsn(): void
    {
        update_option(AmazonMailerConfiguration::OPTION_NAME, [
            'dsn' => 'original-dsn',
            'provider' => 'dsn',
            'fields' => ['dsn' => 'original-dsn'],
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'dsn',
            'fields' => ['dsn' => AmazonMailerConfiguration::MASKED_VALUE],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertSame('original-dsn', $saved['dsn']);
    }

    #[Test]
    public function saveSettingsBuildsDsnFromProviderFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'native',
            'fields' => [],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertStringStartsWith('native://', $saved['dsn']);
    }

    #[Test]
    public function sendTestEmailReturnsResult(): void
    {
        $response = $this->controller->sendTestEmail();

        self::assertSame(200, $response->statusCode);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('success', $data);
        self::assertArrayHasKey('to', $data);
    }

    #[Test]
    public function getSettingsReturnsDefinitionFields(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        $sesDef = $data['definitions']['ses+api'];
        self::assertNotEmpty($sesDef['fields']);

        $fieldNames = array_column($sesDef['fields'], 'name');
        self::assertContains('region', $fieldNames);
    }

    #[Test]
    public function saveSettingsBuildsDsnFromSesFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'ses+api',
            'fields' => ['accessKey' => 'KEY', 'secretKey' => 'SECRET', 'region' => 'ap-northeast-1'],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertStringStartsWith('ses+api://', $saved['dsn']);
        self::assertStringContainsString('ap-northeast-1', $saved['dsn']);
    }

    #[Test]
    public function saveSettingsRestoresMaskedPasswordInFields(): void
    {
        update_option(AmazonMailerConfiguration::OPTION_NAME, [
            'dsn' => 'ses+api://KEY:SECRET@default?region=us-east-1',
            'provider' => 'ses+api',
            'fields' => ['accessKey' => 'KEY', 'secretKey' => 'SECRET', 'region' => 'us-east-1'],
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'ses+api',
            'fields' => ['accessKey' => 'KEY', 'secretKey' => AmazonMailerConfiguration::MASKED_VALUE, 'region' => 'us-east-1'],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertSame('SECRET', $saved['fields']['secretKey']);
    }

    #[Test]
    public function saveSettingsHandlesUnknownProvider(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'unknown-transport',
            'fields' => [],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertSame('unknown-transport', $saved['provider']);
    }

    #[Test]
    public function saveSettingsDoesNotPersistWhenConstantDefined(): void
    {
        // MAILER_DSN constant is defined from AmazonMailerConfigurationTest
        if (!\defined('MAILER_DSN')) {
            self::markTestSkipped('MAILER_DSN constant not defined in this run.');
        }

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'dsn',
            'fields' => ['dsn' => 'native://default'],
        ]));

        $this->controller->saveSettings($request);

        // persistOptions returns early, so no option is saved
        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME, []);
        self::assertEmpty($saved);
    }

    #[Test]
    public function saveSettingsHandlesEmptyProvider(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => '',
            'fields' => [],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertSame('', $saved['provider']);
    }

    #[Test]
    public function getSettingsReturnsSmtpDefinition(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('smtp', $data['definitions']);
        self::assertSame('SMTP', $data['definitions']['smtp']['label']);
    }

    #[Test]
    public function saveSettingsBuildsDsnFromSmtpFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'smtp',
            'fields' => ['host' => 'smtp.example.com', 'port' => '587', 'username' => 'user', 'password' => 'pass'],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(AmazonMailerConfiguration::OPTION_NAME);
        self::assertStringStartsWith('smtp://', $saved['dsn']);
        self::assertStringContainsString('smtp.example.com', $saved['dsn']);
    }
}
