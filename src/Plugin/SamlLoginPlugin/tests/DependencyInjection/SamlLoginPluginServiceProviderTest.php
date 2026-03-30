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

namespace WpPack\Plugin\SamlLoginPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlAcsController;
use WpPack\Component\Security\Bridge\SAML\SamlAuthenticator;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutListener;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;
use WpPack\Component\Security\Bridge\SAML\SamlSloController;
use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Transient\TransientManager;
use WpPack\Component\User\UserRepository;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;
use WpPack\Plugin\SamlLoginPlugin\DependencyInjection\SamlLoginPluginServiceProvider;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginForm;

#[CoversClass(SamlLoginPluginServiceProvider::class)]
final class SamlLoginPluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private SamlLoginPluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new SamlLoginPluginServiceProvider();
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

        self::assertTrue($this->builder->hasDefinition(SamlLoginConfiguration::class));

        $definition = $this->builder->findDefinition(SamlLoginConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SamlLoginConfiguration::class, $factory[0]);
        self::assertSame('fromEnvironmentOrOptions', $factory[1]);
    }

    #[Test]
    public function registersIdpSettings(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(IdpSettings::class));

        $definition = $this->builder->findDefinition(IdpSettings::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SamlLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createIdpSettings', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SamlLoginConfiguration::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersSpSettings(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SpSettings::class));

        $definition = $this->builder->findDefinition(SpSettings::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SamlLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createSpSettings', $factory[1]);
    }

    #[Test]
    public function registersSamlConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlConfiguration::class));

        $definition = $this->builder->findDefinition(SamlConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SamlLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createSamlConfiguration', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertCount(3, $arguments);
        self::assertSame(SamlLoginConfiguration::class, (string) $arguments[0]);
        self::assertSame(IdpSettings::class, (string) $arguments[1]);
        self::assertSame(SpSettings::class, (string) $arguments[2]);
    }

    #[Test]
    public function registersSamlAuthFactory(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlAuthFactory::class));

        $definition = $this->builder->findDefinition(SamlAuthFactory::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SamlConfiguration::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersSamlUserResolver(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlUserResolver::class));

        $definition = $this->builder->findDefinition(SamlUserResolver::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SamlLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createUserResolver', $factory[1]);
    }

    #[Test]
    public function registersSamlAuthenticatorWithTag(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlAuthenticator::class));

        $definition = $this->builder->findDefinition(SamlAuthenticator::class);
        self::assertTrue($definition->hasTag('security.authenticator'));

        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SamlLoginPluginServiceProvider::class, $factory[0]);
        self::assertSame('createAuthenticator', $factory[1]);
    }

    #[Test]
    public function registersSamlEntryPoint(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlEntryPoint::class));

        $definition = $this->builder->findDefinition(SamlEntryPoint::class);
        $arguments = $definition->getArguments();
        self::assertCount(4, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SamlAuthFactory::class, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(AuthenticationSession::class, (string) $arguments[1]);
        self::assertInstanceOf(Reference::class, $arguments[2]);
        self::assertSame(Request::class, (string) $arguments[2]);
        self::assertInstanceOf(Reference::class, $arguments[3]);
        self::assertSame(TransientManager::class, (string) $arguments[3]);
    }

    #[Test]
    public function registersSamlLogoutHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlLogoutHandler::class));

        $definition = $this->builder->findDefinition(SamlLogoutHandler::class);
        $arguments = $definition->getArguments();
        self::assertCount(2, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SamlAuthFactory::class, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(AuthenticationSession::class, (string) $arguments[1]);
    }

    #[Test]
    public function registersSpMetadataExporter(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SpMetadataExporter::class));

        $definition = $this->builder->findDefinition(SpMetadataExporter::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SamlConfiguration::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersSamlMetadataController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlMetadataController::class));

        $definition = $this->builder->findDefinition(SamlMetadataController::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SpMetadataExporter::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersSamlAcsController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlAcsController::class));

        $definition = $this->builder->findDefinition(SamlAcsController::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(AuthenticationManagerInterface::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersSamlSloController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlSloController::class));

        $definition = $this->builder->findDefinition(SamlSloController::class);
        $arguments = $definition->getArguments();
        self::assertCount(5, $arguments);
        self::assertSame(SamlLogoutHandler::class, (string) $arguments[0]);
        self::assertSame(SamlSessionManager::class, (string) $arguments[1]);
        self::assertSame(AuthenticationSession::class, (string) $arguments[2]);
        self::assertSame(Request::class, (string) $arguments[3]);
        self::assertSame(\WpPack\Component\Site\BlogContextInterface::class, (string) $arguments[4]);
    }

    #[Test]
    public function registersRouteRegistry(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(RouteRegistry::class));

        $definition = $this->builder->findDefinition(RouteRegistry::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame(Request::class, (string) $arguments[0]);
    }

    #[Test]
    public function doesNotOverrideExistingRouteRegistry(): void
    {
        $this->builder->register(RouteRegistry::class)
            ->addArgument('custom');

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition(RouteRegistry::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function registersSamlLogoutListenerFromBridge(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlLogoutListener::class));

        $definition = $this->builder->findDefinition(SamlLogoutListener::class);
        $arguments = $definition->getArguments();
        self::assertCount(2, $arguments);
        self::assertSame(SamlLogoutHandler::class, (string) $arguments[0]);
        self::assertSame(SamlSessionManager::class, (string) $arguments[1]);
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
    public function createIdpSettingsReturnsCorrectSettings(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIICert',
            idpSloUrl: 'https://idp.example.com/slo',
            idpCertFingerprint: 'AA:BB:CC',
        );

        $settings = SamlLoginPluginServiceProvider::createIdpSettings($config);

        self::assertSame('https://idp.example.com', $settings->getEntityId());
        self::assertSame('https://idp.example.com/sso', $settings->getSsoUrl());
        self::assertSame('https://idp.example.com/slo', $settings->getSloUrl());
        self::assertSame('MIICert', $settings->getX509Cert());
        self::assertSame('AA:BB:CC', $settings->getCertFingerprint());
    }

    #[Test]
    public function createSpSettingsUsesHomeUrlDefaults(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIICert',
        );

        $settings = SamlLoginPluginServiceProvider::createSpSettings($config);

        // home_url() returns http://example.org in test environment
        self::assertSame(home_url(), $settings->getEntityId());
        self::assertSame(home_url('/saml/acs'), $settings->getAcsUrl());
        self::assertSame(home_url('/saml/slo'), $settings->getSloUrl());
    }

    #[Test]
    public function createSpSettingsUsesConfiguredValues(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIICert',
            spEntityId: 'https://custom-sp.example.com',
            spNameIdFormat: 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
            acsPath: '/custom/acs',
            sloPath: '/custom/slo',
        );

        $settings = SamlLoginPluginServiceProvider::createSpSettings($config);

        self::assertSame('https://custom-sp.example.com', $settings->getEntityId());
        self::assertStringEndsWith('/custom/acs', $settings->getAcsUrl());
        self::assertStringEndsWith('/custom/slo', $settings->getSloUrl());
        self::assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $settings->getNameIdFormat());
    }

    #[Test]
    public function createSamlConfigurationBuildsCorrectly(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIICert',
            strict: false,
            debug: true,
            wantAssertionsSigned: false,
        );

        $idpSettings = SamlLoginPluginServiceProvider::createIdpSettings($config);
        $spSettings = SamlLoginPluginServiceProvider::createSpSettings($config);

        $samlConfig = SamlLoginPluginServiceProvider::createSamlConfiguration($config, $idpSettings, $spSettings);

        self::assertFalse($samlConfig->isStrict());
        self::assertTrue($samlConfig->isDebug());
        self::assertFalse($samlConfig->wantAssertionsSigned());
        self::assertSame($idpSettings, $samlConfig->getIdpSettings());
        self::assertSame($spSettings, $samlConfig->getSpSettings());
    }

    #[Test]
    public function createUserResolverBuildsCorrectly(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIICert',
            autoProvision: true,
            defaultRole: 'editor',
            roleAttribute: 'groups',
            roleMapping: ['admins' => 'administrator'],
        );

        $resolver = SamlLoginPluginServiceProvider::createUserResolver($config, new EventDispatcher(), new UserRepository(), new Sanitizer());

        self::assertInstanceOf(SamlUserResolver::class, $resolver);
    }

    #[Test]
    public function createAuthenticatorBuildsCorrectly(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: 'https://idp.example.com',
            idpSsoUrl: 'https://idp.example.com/sso',
            idpX509Cert: 'MIICert',
            addUserToBlog: false,
        );

        $idpSettings = SamlLoginPluginServiceProvider::createIdpSettings($config);
        $spSettings = SamlLoginPluginServiceProvider::createSpSettings($config);
        $samlConfig = SamlLoginPluginServiceProvider::createSamlConfiguration($config, $idpSettings, $spSettings);
        $authFactory = new SamlAuthFactory($samlConfig);
        $userResolver = SamlLoginPluginServiceProvider::createUserResolver($config, new EventDispatcher(), new UserRepository(), new Sanitizer());
        $dispatcher = new EventDispatcher();

        $sessionManager = new SamlSessionManager(new UserRepository());
        $blogContext = new BlogContext();

        $authenticator = SamlLoginPluginServiceProvider::createAuthenticator(
            $authFactory,
            $userResolver,
            $dispatcher,
            $config,
            $sessionManager,
            $blogContext,
            new TransientManager(),
        );

        self::assertInstanceOf(SamlAuthenticator::class, $authenticator);
    }

    #[Test]
    public function registersSamlLoginForm(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SamlLoginForm::class));

        $definition = $this->builder->findDefinition(SamlLoginForm::class);
        $arguments = $definition->getArguments();
        self::assertCount(3, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SamlEntryPoint::class, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(AuthenticationSession::class, (string) $arguments[1]);
        self::assertInstanceOf(Reference::class, $arguments[2]);
        self::assertSame(Request::class, (string) $arguments[2]);
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $result = $this->builder->addServiceProvider($this->provider);

        self::assertSame($this->builder, $result);
        self::assertTrue($this->builder->hasDefinition(SamlLoginConfiguration::class));
        self::assertTrue($this->builder->hasDefinition(IdpSettings::class));
        self::assertTrue($this->builder->hasDefinition(SpSettings::class));
        self::assertTrue($this->builder->hasDefinition(SamlConfiguration::class));
        self::assertTrue($this->builder->hasDefinition(SamlAuthFactory::class));
        self::assertTrue($this->builder->hasDefinition(SamlUserResolver::class));
        self::assertTrue($this->builder->hasDefinition(SamlAuthenticator::class));
        self::assertTrue($this->builder->hasDefinition(SamlEntryPoint::class));
        self::assertTrue($this->builder->hasDefinition(SamlLogoutHandler::class));
        self::assertTrue($this->builder->hasDefinition(SpMetadataExporter::class));
        self::assertTrue($this->builder->hasDefinition(SamlMetadataController::class));
        self::assertTrue($this->builder->hasDefinition(SamlAcsController::class));
        self::assertTrue($this->builder->hasDefinition(SamlSloController::class));
        self::assertTrue($this->builder->hasDefinition(SamlLoginForm::class));
        self::assertTrue($this->builder->hasDefinition(RouteRegistry::class));
        self::assertTrue($this->builder->hasDefinition(SamlLogoutListener::class));
    }
}
