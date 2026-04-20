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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;

// Several tests define OAUTH_* PHP constants to exercise the constant
// branch of fromEnvironment / fromEnvironmentOrOptions. Running each
// test in its own process prevents one test's define() from silently
// short-circuiting the next test's wp_options fallback.
#[CoversClass(OAuthLoginConfiguration::class)]
#[CoversClass(ProviderConfiguration::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
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
        \define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                'client_id' => 'gid',
                'client_secret' => 'gsecret',
                'label' => 'Google SSO',
                'hosted_domain' => 'example.com',
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironment();

        self::assertCount(1, $config->providers);
        self::assertSame('google', $config->providers['google']->type);
        self::assertSame('gid', $config->providers['google']->clientId);
        self::assertSame('Google SSO', $config->providers['google']->label);
        self::assertSame('example.com', $config->providers['google']->hostedDomain);
        self::assertFalse($config->ssoOnly);
        self::assertFalse($config->autoProvision);
        self::assertSame('/oauth/{provider}/authorize', $config->authorizePath);
    }

    #[Test]
    public function fromEnvironmentThrowsWhenConstantNotDefined(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_PROVIDERS is not configured');

        OAuthLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentRejectsInvalidProviderName(): void
    {
        \define('OAUTH_PROVIDERS', [
            'Invalid Name!' => [
                'type' => 'google',
                'client_id' => 'id',
                'client_secret' => 'secret',
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is invalid');

        OAuthLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentRejectsMissingRequiredFields(): void
    {
        \define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                // missing client_id and client_secret
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required fields');

        OAuthLoginConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentGlobalAutoProvisionWithProviderOverride(): void
    {
        \define('OAUTH_AUTO_PROVISION', true);
        \define('OAUTH_PROVIDERS', [
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

        $config = OAuthLoginConfiguration::fromEnvironment();

        self::assertTrue($config->autoProvision);
        self::assertTrue($config->providers['google']->autoProvision);
        self::assertFalse($config->providers['azure']->autoProvision);
    }

    #[Test]
    public function fromEnvironmentCustomPaths(): void
    {
        \define('OAUTH_AUTHORIZE_PATH', '/sso/{provider}/login');
        \define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                'client_id' => 'gid',
                'client_secret' => 'gsecret',
            ],
        ]);

        $config = OAuthLoginConfiguration::fromEnvironment();

        self::assertSame('/sso/{provider}/login', $config->authorizePath);
    }

    #[Test]
    public function fromEnvironmentOrOptionsPrefersConstantsOverOptions(): void
    {
        \define('OAUTH_PROVIDERS', [
            'google' => [
                'type' => 'google',
                'client_id' => 'const-id',
                'client_secret' => 'const-secret',
            ],
        ]);
        \define('OAUTH_SSO_ONLY', true);
        \define('OAUTH_AUTO_PROVISION', true);
        \define('OAUTH_AUTHORIZE_PATH', '/custom/{provider}/go');
        \define('OAUTH_CALLBACK_PATH', '/custom/{provider}/back');
        \define('OAUTH_VERIFY_PATH', '/custom/{provider}/check');
        \define('OAUTH_BUTTON_DISPLAY', 'icon-only');

        update_option(OAuthLoginConfiguration::OPTION_NAME, [
            'providers' => [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'option-id',
                    'client_secret' => 'option-secret',
                ],
            ],
            'ssoOnly' => false,
        ]);

        try {
            $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

            self::assertSame('const-id', $config->providers['google']->clientId);
            self::assertTrue($config->ssoOnly);
            self::assertTrue($config->autoProvision);
            self::assertSame('/custom/{provider}/go', $config->authorizePath);
            self::assertSame('/custom/{provider}/back', $config->callbackPath);
            self::assertSame('/custom/{provider}/check', $config->verifyPath);
            self::assertSame('icon-only', $config->buttonDisplay);
        } finally {
            delete_option(OAuthLoginConfiguration::OPTION_NAME);
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
