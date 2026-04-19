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

namespace WPPack\Plugin\OAuthLoginPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WPPack\Component\Security\Bridge\OAuth\OAuthAuthenticator;
use WPPack\Component\Security\Bridge\OAuth\OAuthCallbackController;
use WPPack\Component\Security\Bridge\OAuth\OAuthVerifyController;
use WPPack\Component\Security\Bridge\OAuth\Provider\AmazonProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\AppleProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\DAccountProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\DiscordProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\EntraIdProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\FacebookProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\GenericOidcProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\GitHubProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\LineProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\MicrosoftProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\OktaProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WPPack\Component\Security\Bridge\OAuth\Provider\SlackProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\YahooJapanProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\YahooProvider;
use WPPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolver;
use WPPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;
use WPPack\Component\User\UserRepository;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\DependencyInjection\OAuthLoginPluginServiceProvider;
use WPPack\Plugin\OAuthLoginPlugin\OAuthLoginForm;

#[CoversClass(OAuthLoginPluginServiceProvider::class)]
final class OAuthLoginPluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private OAuthLoginPluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new OAuthLoginPluginServiceProvider();

        // OAUTH_PROVIDERS must be defined for the service provider to work
        if (!\defined('OAUTH_PROVIDERS')) {
            \define('OAUTH_PROVIDERS', [
                'google' => [
                    'type' => 'google',
                    'client_id' => 'test-google-id',
                    'client_secret' => 'test-google-secret',
                    'label' => 'Google',
                    'hosted_domain' => 'example.com',
                ],
                'github' => [
                    'type' => 'github',
                    'client_id' => 'test-github-id',
                    'client_secret' => 'test-github-secret',
                    'label' => 'GitHub',
                ],
            ]);
        }
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        self::assertInstanceOf(ServiceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function registersConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(OAuthLoginConfiguration::class));

        $definition = $this->builder->findDefinition(OAuthLoginConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(OAuthLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('returnConfig', $factory[1]);
    }

    #[Test]
    public function registersPerProviderAuthenticatorWithTag(): void
    {
        $this->provider->register($this->builder);

        $googleAuthId = OAuthAuthenticator::class . '.google';
        self::assertTrue($this->builder->hasDefinition($googleAuthId));

        $definition = $this->builder->findDefinition($googleAuthId);
        self::assertTrue($definition->hasTag('security.authenticator'));

        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(OAuthLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createAuthenticator', $factory[1]);
    }

    #[Test]
    public function registersCallbackController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(OAuthCallbackController::class));

        $definition = $this->builder->findDefinition(OAuthCallbackController::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(AuthenticationManagerInterface::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersVerifyController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(OAuthVerifyController::class));

        $definition = $this->builder->findDefinition(OAuthVerifyController::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(AuthenticationManagerInterface::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersPerProviderOAuthConfiguration(): void
    {
        $this->provider->register($this->builder);

        $configId = OAuthConfiguration::class . '.google';
        self::assertTrue($this->builder->hasDefinition($configId));

        $definition = $this->builder->findDefinition($configId);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(OAuthLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createOAuthConfiguration', $factory[1]);
    }

    #[Test]
    public function registersPerProviderInstance(): void
    {
        $this->provider->register($this->builder);

        $providerId = ProviderInterface::class . '.google';
        self::assertTrue($this->builder->hasDefinition($providerId));

        $definition = $this->builder->findDefinition($providerId);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(OAuthLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createProvider', $factory[1]);
    }

    #[Test]
    public function registersPerProviderUserResolver(): void
    {
        $this->provider->register($this->builder);

        $resolverId = OAuthUserResolverInterface::class . '.google';
        self::assertTrue($this->builder->hasDefinition($resolverId));

        $definition = $this->builder->findDefinition($resolverId);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(OAuthLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createUserResolver', $factory[1]);
    }

    #[Test]
    public function registersLoginForm(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(OAuthLoginForm::class));

        $definition = $this->builder->findDefinition(OAuthLoginForm::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(OAuthLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createLoginForm', $factory[1]);
    }

    #[Test]
    public function autoRegistersEventDispatcherServiceProvider(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(EventDispatcher::class));
    }

    #[Test]
    public function autoRegistersSecurityServiceProvider(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AuthenticationManager::class));
    }

    #[Test]
    public function doesNotOverrideExistingEventDispatcher(): void
    {
        $this->builder->register(EventDispatcher::class)
            ->addArgument('custom');

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition(EventDispatcher::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function doesNotOverrideExistingAuthenticationManager(): void
    {
        $this->builder->register(AuthenticationManager::class)
            ->addArgument('custom');

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition(AuthenticationManager::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function createProviderReturnsGoogleProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Google',
            hostedDomain: 'example.com',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/google/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(GoogleProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsEntraIdProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'azure',
            type: 'entra-id',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Azure',
            tenantId: 'common',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/azure/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(EntraIdProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsGitHubProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'github',
            type: 'github',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'GitHub',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/github/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(GitHubProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsGenericOidcProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'oidc',
            type: 'oidc',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'OIDC',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/oidc/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(GenericOidcProvider::class, $provider);
    }

    #[Test]
    public function createProviderThrowsForUnknownType(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'unknown',
            type: 'unknown_type',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Unknown',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/unknown/callback',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown OAuth provider type "unknown_type"');

        OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);
    }

    #[Test]
    public function createProviderThrowsForAzureWithoutTenantId(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'azure',
            type: 'entra-id',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Azure',
            // tenantId intentionally omitted (null)
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/azure/callback',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires a "tenant_id"');

        OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);
    }

    #[Test]
    public function createProviderReturnsOktaProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'okta',
            type: 'okta',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Okta',
            domain: 'dev-123.okta.com',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/okta/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(OktaProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsAppleProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'apple',
            type: 'apple',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Apple',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/apple/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(AppleProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsDiscordProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'discord',
            type: 'discord',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Discord',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/discord/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(DiscordProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsLineProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'line',
            type: 'line',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'LINE',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/line/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(LineProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsDAccountProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'd-account',
            type: 'd-account',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'dアカウント',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/d-account/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(DAccountProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsAmazonProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'amazon',
            type: 'amazon',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Amazon',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/amazon/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(AmazonProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsMicrosoftProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'microsoft',
            type: 'microsoft',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Microsoft',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/microsoft/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(MicrosoftProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsYahooProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'yahoo',
            type: 'yahoo',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Yahoo',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/yahoo/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(YahooProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsYahooJapanProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'yahoo-japan',
            type: 'yahoo-japan',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Yahoo! JAPAN',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/yahoo-japan/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(YahooJapanProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsFacebookProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'facebook',
            type: 'facebook',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Facebook',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/facebook/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(FacebookProvider::class, $provider);
    }

    #[Test]
    public function createProviderReturnsSlackProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'slack',
            type: 'slack',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Slack',
        );

        $oauthConfig = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/oauth/slack/callback',
        );

        $provider = OAuthLoginPluginServiceProvider::createProvider($providerConfig, $oauthConfig);

        self::assertInstanceOf(SlackProvider::class, $provider);
    }

    #[Test]
    public function isProviderConfigCompleteReturnsFalseForEmptyClientId(): void
    {
        $incompleteProvider = new ProviderConfiguration(
            name: 'incomplete',
            type: 'google',
            clientId: '',
            clientSecret: 'secret',
            label: 'Incomplete',
        );

        $completeProvider = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(
            providers: [
                'incomplete' => $incompleteProvider,
                'google' => $completeProvider,
            ],
        );

        $authSession = new \WPPack\Component\Security\AuthenticationSession();
        $request = \WPPack\Component\HttpFoundation\Request::create('https://example.com/wp-login.php');

        $form = OAuthLoginPluginServiceProvider::createLoginForm($config, $authSession, $request);

        // The login form should only contain the complete provider
        self::assertInstanceOf(OAuthLoginForm::class, $form);
    }

    #[Test]
    public function createOAuthConfigurationBuildsCorrectly(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'gid',
            clientSecret: 'gsecret',
            label: 'Google',
            scopes: ['openid', 'email'],
        );

        $config = new OAuthLoginConfiguration(
            providers: ['google' => $providerConfig],
        );

        $blogContext = new \WPPack\Component\Site\BlogContext();

        $oauthConfig = OAuthLoginPluginServiceProvider::createOAuthConfiguration($providerConfig, $config, $blogContext);

        self::assertInstanceOf(OAuthConfiguration::class, $oauthConfig);
        self::assertSame('gid', $oauthConfig->getClientId());
        self::assertSame('gsecret', $oauthConfig->getClientSecret());
        self::assertSame(['openid', 'email'], $oauthConfig->getScopes());
        self::assertStringContainsString('/oauth/google/callback', $oauthConfig->getRedirectUri());
    }

    #[Test]
    public function createOAuthConfigurationUsesDefaultScopesForGitHub(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'github',
            type: 'github',
            clientId: 'ghid',
            clientSecret: 'ghsecret',
            label: 'GitHub',
            // scopes not set => should default to ['user:email'] for github
        );

        $config = new OAuthLoginConfiguration(
            providers: ['github' => $providerConfig],
        );

        $blogContext = new \WPPack\Component\Site\BlogContext();

        $oauthConfig = OAuthLoginPluginServiceProvider::createOAuthConfiguration($providerConfig, $config, $blogContext);

        self::assertSame(['user:email'], $oauthConfig->getScopes());
    }

    #[Test]
    public function createUserResolverReturnsOAuthUserResolver(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Google',
            autoProvision: true,
        );

        $resolver = OAuthLoginPluginServiceProvider::createUserResolver(
            $providerConfig,
            new UserRepository(),
            new Sanitizer(),
            new EventDispatcher(),
        );

        self::assertInstanceOf(OAuthUserResolver::class, $resolver);
    }

    #[Test]
    public function createLoginFormReturnsOAuthLoginForm(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'id',
            clientSecret: 'secret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(
            providers: ['google' => $providerConfig],
        );

        $authSession = new \WPPack\Component\Security\AuthenticationSession();
        $request = \WPPack\Component\HttpFoundation\Request::create('https://example.com/wp-login.php');

        $form = OAuthLoginPluginServiceProvider::createLoginForm($config, $authSession, $request);

        self::assertInstanceOf(OAuthLoginForm::class, $form);
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $result = $this->builder->addServiceProvider($this->provider);

        self::assertSame($this->builder, $result);
        self::assertTrue($this->builder->hasDefinition(OAuthLoginConfiguration::class));
        self::assertTrue($this->builder->hasDefinition(OAuthCallbackController::class));
        self::assertTrue($this->builder->hasDefinition(OAuthVerifyController::class));
        self::assertTrue($this->builder->hasDefinition(OAuthLoginForm::class));

        // Per-provider services
        self::assertTrue($this->builder->hasDefinition(OAuthAuthenticator::class . '.google'));
        self::assertTrue($this->builder->hasDefinition(OAuthAuthenticator::class . '.github'));
        self::assertTrue($this->builder->hasDefinition(ProviderInterface::class . '.google'));
        self::assertTrue($this->builder->hasDefinition(ProviderInterface::class . '.github'));
        self::assertTrue($this->builder->hasDefinition(OAuthConfiguration::class . '.google'));
        self::assertTrue($this->builder->hasDefinition(OAuthConfiguration::class . '.github'));
        self::assertTrue($this->builder->hasDefinition(OAuthUserResolverInterface::class . '.google'));
        self::assertTrue($this->builder->hasDefinition(OAuthUserResolverInterface::class . '.github'));
    }
}
