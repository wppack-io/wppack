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

namespace WpPack\Plugin\OAuthLoginPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\OAuthAuthenticator;
use WpPack\Component\Security\Bridge\OAuth\OAuthCallbackController;
use WpPack\Component\Security\Bridge\OAuth\OAuthVerifyController;
use WpPack\Component\Security\Bridge\OAuth\Provider\AzureProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\GenericOidcProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\GitHubProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolver;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;
use WpPack\Component\User\UserRepository;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\DependencyInjection\OAuthLoginPluginServiceProvider;
use WpPack\Plugin\OAuthLoginPlugin\OAuthLoginForm;

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
    public function createProviderReturnsAzureProvider(): void
    {
        $providerConfig = new ProviderConfiguration(
            name: 'azure',
            type: 'azure',
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

        self::assertInstanceOf(AzureProvider::class, $provider);
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
            type: 'azure',
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

        $blogContext = new \WpPack\Component\Site\BlogContext();

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

        $blogContext = new \WpPack\Component\Site\BlogContext();

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
            defaultRole: 'editor',
            roleClaim: 'roles',
            roleMapping: ['admin' => 'administrator'],
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

        $authSession = new \WpPack\Component\Security\AuthenticationSession();
        $request = \WpPack\Component\HttpFoundation\Request::create('https://example.com/wp-login.php');

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
