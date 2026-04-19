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

namespace WPPack\Plugin\OAuthLoginPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;

#[CoversClass(OAuthLoginConfiguration::class)]
#[CoversClass(ProviderConfiguration::class)]
final class OAuthLoginConfigurationTest extends TestCase
{
    #[Test]
    public function constructorStoresProperties(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'google-client-id',
            clientSecret: 'google-secret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(
            providers: ['google' => $google],
            ssoOnly: true,
            autoProvision: true,
            authorizePath: '/sso/{provider}/authorize',
            callbackPath: '/sso/{provider}/callback',
            verifyPath: '/sso/{provider}/verify',
        );

        self::assertSame(['google' => $google], $config->providers);
        self::assertTrue($config->ssoOnly);
        self::assertTrue($config->autoProvision);
        self::assertSame('/sso/{provider}/authorize', $config->authorizePath);
    }

    #[Test]
    public function constructorDefaults(): void
    {
        $config = new OAuthLoginConfiguration(providers: []);

        self::assertSame([], $config->providers);
        self::assertFalse($config->ssoOnly);
        self::assertFalse($config->autoProvision);
        self::assertSame('/oauth/{provider}/authorize', $config->authorizePath);
        self::assertSame('/oauth/{provider}/callback', $config->callbackPath);
        self::assertSame('/oauth/{provider}/verify', $config->verifyPath);
    }

    #[Test]
    public function getProviderReturnsConfigOrNull(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(providers: ['google' => $google]);

        self::assertSame($google, $config->getProvider('google'));
        self::assertNull($config->getProvider('nonexistent'));
    }

    #[Test]
    public function getAuthorizePathGeneratesCorrectPath(): void
    {
        $config = new OAuthLoginConfiguration(providers: []);

        self::assertSame('/oauth/google/authorize', $config->getAuthorizePath('google'));
    }

    #[Test]
    public function getCallbackPathGeneratesCorrectPath(): void
    {
        $config = new OAuthLoginConfiguration(providers: []);

        self::assertSame('/oauth/google/callback', $config->getCallbackPath('google'));
    }

    #[Test]
    public function getVerifyPathGeneratesCorrectPath(): void
    {
        $config = new OAuthLoginConfiguration(providers: []);

        self::assertSame('/oauth/google/verify', $config->getVerifyPath('google'));
    }

    #[Test]
    public function pathsUseCustomPaths(): void
    {
        $config = new OAuthLoginConfiguration(
            providers: [],
            authorizePath: '/sso/{provider}/login',
            callbackPath: '/sso/{provider}/return',
            verifyPath: '/sso/{provider}/check',
        );

        self::assertSame('/sso/azure/login', $config->getAuthorizePath('azure'));
        self::assertSame('/sso/azure/return', $config->getCallbackPath('azure'));
        self::assertSame('/sso/azure/check', $config->getVerifyPath('azure'));
    }

    #[Test]
    public function fromEnvironmentParsesProviders(): void
    {
        // Since PHP constants cannot be undefined once defined, we use
        // runkit or a separate process. Instead, test via constructor for
        // most logic and use fromEnvironment() only in one dedicated test.
        // We use a child process to define OAUTH_PROVIDERS.
        $script = <<<'PHP'
        <?php
        define('ABSPATH', '%s');
        require_once '%s';

        define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                'client_id' => 'gid',
                'client_secret' => 'gsecret',
                'label' => 'Google SSO',
                'hosted_domain' => 'example.com',
            ],
        ]);

        $config = \WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration::fromEnvironment();
        echo json_encode([
            'count' => count($config->providers),
            'google_type' => $config->providers['google']->type,
            'google_client_id' => $config->providers['google']->clientId,
            'google_label' => $config->providers['google']->label,
            'google_hosted_domain' => $config->providers['google']->hostedDomain,
            'sso_only' => $config->ssoOnly,
            'auto_provision' => $config->autoProvision,
            'authorize_path' => $config->authorizePath,
        ]);
        PHP;

        $abspath = \defined('ABSPATH') ? ABSPATH : '/tmp/';
        $autoloader = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $script = \sprintf($script, addslashes($abspath), addslashes($autoloader));

        $tmpFile = tempnam(sys_get_temp_dir(), 'oauth_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            self::assertNotNull($output);
            $result = json_decode($output, true);
            self::assertIsArray($result, 'Script output: ' . ($output ?? ''));

            self::assertSame(1, $result['count']);
            self::assertSame('google', $result['google_type']);
            self::assertSame('gid', $result['google_client_id']);
            self::assertSame('Google SSO', $result['google_label']);
            self::assertSame('example.com', $result['google_hosted_domain']);
            self::assertFalse($result['sso_only']);
            self::assertFalse($result['auto_provision']);
            self::assertSame('/oauth/{provider}/authorize', $result['authorize_path']);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentThrowsWhenConstantNotDefined(): void
    {
        $script = <<<'PHP'
        <?php
        define('ABSPATH', '%s');
        require_once '%s';

        try {
            \WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration::fromEnvironment();
            echo 'NO_EXCEPTION';
        } catch (\RuntimeException $e) {
            echo $e->getMessage();
        }
        PHP;

        $abspath = \defined('ABSPATH') ? ABSPATH : '/tmp/';
        $autoloader = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $script = \sprintf($script, addslashes($abspath), addslashes($autoloader));

        $tmpFile = tempnam(sys_get_temp_dir(), 'oauth_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            self::assertStringContainsString('OAUTH_PROVIDERS is not configured', $output ?? '');
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentRejectsInvalidProviderName(): void
    {
        $script = <<<'PHP'
        <?php
        define('ABSPATH', '%s');
        require_once '%s';

        define('OAUTH_PROVIDERS', [
            'Invalid Name!' => [
                'type' => 'google',
                'client_id' => 'id',
                'client_secret' => 'secret',
            ],
        ]);

        try {
            \WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration::fromEnvironment();
            echo 'NO_EXCEPTION';
        } catch (\RuntimeException $e) {
            echo $e->getMessage();
        }
        PHP;

        $abspath = \defined('ABSPATH') ? ABSPATH : '/tmp/';
        $autoloader = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $script = \sprintf($script, addslashes($abspath), addslashes($autoloader));

        $tmpFile = tempnam(sys_get_temp_dir(), 'oauth_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            self::assertStringContainsString('is invalid', $output ?? '');
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentRejectsMissingRequiredFields(): void
    {
        $script = <<<'PHP'
        <?php
        define('ABSPATH', '%s');
        require_once '%s';

        define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                // missing client_id and client_secret
            ],
        ]);

        try {
            \WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration::fromEnvironment();
            echo 'NO_EXCEPTION';
        } catch (\RuntimeException $e) {
            echo $e->getMessage();
        }
        PHP;

        $abspath = \defined('ABSPATH') ? ABSPATH : '/tmp/';
        $autoloader = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $script = \sprintf($script, addslashes($abspath), addslashes($autoloader));

        $tmpFile = tempnam(sys_get_temp_dir(), 'oauth_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            self::assertStringContainsString('missing required fields', $output ?? '');
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentGlobalAutoProvisionWithProviderOverride(): void
    {
        $script = <<<'PHP'
        <?php
        define('ABSPATH', '%s');
        require_once '%s';

        define('OAUTH_AUTO_PROVISION', true);
        define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                'client_id' => 'gid',
                'client_secret' => 'gsecret',
            ],
            'azure' => [
                'type' => 'azure',
                'client_id' => 'aid',
                'client_secret' => 'asecret',
                'tenant_id' => 'tid',
                'auto_provision' => false,
            ],
        ]);

        $config = \WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration::fromEnvironment();
        echo json_encode([
            'global_auto_provision' => $config->autoProvision,
            'google_auto_provision' => $config->providers['google']->autoProvision,
            'azure_auto_provision' => $config->providers['azure']->autoProvision,
        ]);
        PHP;

        $abspath = \defined('ABSPATH') ? ABSPATH : '/tmp/';
        $autoloader = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $script = \sprintf($script, addslashes($abspath), addslashes($autoloader));

        $tmpFile = tempnam(sys_get_temp_dir(), 'oauth_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            self::assertNotNull($output);
            $result = json_decode($output, true);
            self::assertIsArray($result, 'Script output: ' . ($output ?? ''));

            // Global values
            self::assertTrue($result['global_auto_provision']);

            // Google inherits global
            self::assertTrue($result['google_auto_provision']);

            // Azure overrides global
            self::assertFalse($result['azure_auto_provision']);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentCustomPaths(): void
    {
        $script = <<<'PHP'
        <?php
        define('ABSPATH', '%s');
        require_once '%s';

        define('OAUTH_AUTHORIZE_PATH', '/sso/{provider}/login');
        define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                'client_id' => 'gid',
                'client_secret' => 'gsecret',
            ],
        ]);

        $config = \WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration::fromEnvironment();
        echo $config->authorizePath;
        PHP;

        $abspath = \defined('ABSPATH') ? ABSPATH : '/tmp/';
        $autoloader = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $script = \sprintf($script, addslashes($abspath), addslashes($autoloader));

        $tmpFile = tempnam(sys_get_temp_dir(), 'oauth_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $script);

        try {
            $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
            self::assertSame('/sso/{provider}/login', trim($output ?? ''));
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsFromWpOptions(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'id',
                    'client_secret' => 'secret',
                    'label' => 'Google',
                ],
            ],
            'ssoOnly' => true,
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        self::assertCount(1, $config->providers);
        self::assertArrayHasKey('google', $config->providers);
        self::assertSame('google', $config->providers['google']->type);
        self::assertSame('id', $config->providers['google']->clientId);
        self::assertSame('secret', $config->providers['google']->clientSecret);
        self::assertSame('Google', $config->providers['google']->label);
        self::assertTrue($config->ssoOnly);

        delete_option(OAuthLoginConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsSkipsInvalidProviderNames(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'valid-name' => [
                    'type' => 'google',
                    'client_id' => 'id',
                    'client_secret' => 'secret',
                    'label' => 'Valid',
                ],
                'invalid name' => [
                    'type' => 'google',
                    'client_id' => 'id2',
                    'client_secret' => 'secret2',
                    'label' => 'Invalid',
                ],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        self::assertCount(1, $config->providers);
        self::assertArrayHasKey('valid-name', $config->providers);
        self::assertArrayNotHasKey('invalid name', $config->providers);

        delete_option(OAuthLoginConfiguration::OPTION_NAME);
    }

    #[Test]
    public function providerConfigurationStoresAllProperties(): void
    {
        $provider = new ProviderConfiguration(
            name: 'azure',
            type: 'azure',
            clientId: 'azure-id',
            clientSecret: 'azure-secret',
            label: 'Azure AD',
            tenantId: 'tenant-123',
            hostedDomain: null,
            discoveryUrl: 'https://login.microsoftonline.com/tenant/.well-known/openid-configuration',
            scopes: ['openid', 'email'],
            autoProvision: true,
        );

        self::assertSame('azure', $provider->name);
        self::assertSame('azure', $provider->type);
        self::assertSame('azure-id', $provider->clientId);
        self::assertSame('azure-secret', $provider->clientSecret);
        self::assertSame('Azure AD', $provider->label);
        self::assertSame('tenant-123', $provider->tenantId);
        self::assertNull($provider->hostedDomain);
        self::assertSame('https://login.microsoftonline.com/tenant/.well-known/openid-configuration', $provider->discoveryUrl);
        self::assertSame(['openid', 'email'], $provider->scopes);
        self::assertTrue($provider->autoProvision);
    }

    #[Test]
    public function providerConfigurationDefaults(): void
    {
        $provider = new ProviderConfiguration(
            name: 'github',
            type: 'github',
            clientId: 'gh-id',
            clientSecret: 'gh-secret',
            label: 'GitHub',
        );

        self::assertNull($provider->tenantId);
        self::assertNull($provider->hostedDomain);
        self::assertNull($provider->discoveryUrl);
        self::assertNull($provider->scopes);
        self::assertFalse($provider->autoProvision);
    }

    #[Test]
    public function fromEnvironmentOrOptionsSkipsMissingRequiredFields(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'incomplete' => [
                    'type' => 'google',
                    // missing client_id and client_secret
                ],
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        self::assertEmpty($config->providers);

        delete_option(OAuthLoginConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsAllProviderOptionalFields(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'oidc' => [
                    'type' => 'oidc',
                    'client_id' => 'oid',
                    'client_secret' => 'osecret',
                    'label' => 'OIDC Provider',
                    'tenant_id' => 'tid',
                    'domain' => 'example.com',
                    'hosted_domain' => 'corp.example.com',
                    'discovery_url' => 'https://idp.example.com/.well-known',
                    'scopes' => ['openid', 'profile'],
                    'auto_provision' => true,
                    'button_style' => 'brand',
                ],
            ],
            'autoProvision' => true,
            'buttonDisplay' => 'icon-only',
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        $oidc = $config->providers['oidc'];
        self::assertSame('oidc', $oidc->type);
        self::assertSame('tid', $oidc->tenantId);
        self::assertSame('example.com', $oidc->domain);
        self::assertSame('corp.example.com', $oidc->hostedDomain);
        self::assertSame('https://idp.example.com/.well-known', $oidc->discoveryUrl);
        self::assertSame(['openid', 'profile'], $oidc->scopes);
        self::assertTrue($oidc->autoProvision);
        self::assertSame('brand', $oidc->buttonStyle);
        self::assertTrue($config->autoProvision);
        self::assertSame('icon-only', $config->buttonDisplay);

        delete_option(OAuthLoginConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsGlobalSettingsFromOptions(): void
    {
        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'ssoOnly' => true,
            'autoProvision' => true,
            'authorizePath' => '/sso/{provider}/login',
            'callbackPath' => '/sso/{provider}/return',
            'verifyPath' => '/sso/{provider}/check',
            'buttonDisplay' => 'text-only',
        ]);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        self::assertTrue($config->ssoOnly);
        self::assertTrue($config->autoProvision);
        self::assertSame('/sso/{provider}/login', $config->authorizePath);
        self::assertSame('/sso/{provider}/return', $config->callbackPath);
        self::assertSame('/sso/{provider}/check', $config->verifyPath);
        self::assertSame('text-only', $config->buttonDisplay);

        delete_option(OAuthLoginConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReturnsEmptyWhenNoProviders(): void
    {
        delete_option(OAuthLoginConfiguration::OPTION_NAME);

        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        self::assertEmpty($config->providers);
    }

    #[Test]
    public function providerConfigurationDomainProperty(): void
    {
        $provider = new ProviderConfiguration(
            name: 'oidc',
            type: 'oidc',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'OIDC',
            domain: 'example.com',
        );

        self::assertSame('example.com', $provider->domain);
    }
}
