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

namespace WpPack\Plugin\SamlLoginPlugin\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
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
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginForm;

final class SamlLoginPluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Auto-register dependent service providers
        if (!$builder->hasDefinition(EventDispatcher::class)) {
            (new EventDispatcherServiceProvider())->register($builder);
        }

        if (!$builder->hasDefinition(AuthenticationManager::class)) {
            (new SecurityServiceProvider())->register($builder);
        }

        // Configuration
        $builder->register(SamlLoginConfiguration::class)
            ->setFactory([SamlLoginConfiguration::class, 'fromEnvironment']);

        // SAML Configuration objects
        $builder->register(IdpSettings::class)
            ->setFactory([self::class, 'createIdpSettings'])
            ->addArgument(new Reference(SamlLoginConfiguration::class));

        $builder->register(SpSettings::class)
            ->setFactory([self::class, 'createSpSettings'])
            ->addArgument(new Reference(SamlLoginConfiguration::class));

        $builder->register(SamlConfiguration::class)
            ->setFactory([self::class, 'createSamlConfiguration'])
            ->addArgument(new Reference(SamlLoginConfiguration::class))
            ->addArgument(new Reference(IdpSettings::class))
            ->addArgument(new Reference(SpSettings::class));

        // SAML Auth Factory
        $builder->register(SamlAuthFactory::class)
            ->addArgument(new Reference(SamlConfiguration::class));

        // User Resolver
        $builder->register(SamlUserResolver::class)
            ->setFactory([self::class, 'createUserResolver'])
            ->addArgument(new Reference(SamlLoginConfiguration::class));
        $builder->setAlias(SamlUserResolverInterface::class, SamlUserResolver::class);

        // SamlAuthenticator (tagged for RegisterAuthenticatorsPass)
        $builder->register(SamlAuthenticator::class)
            ->setFactory([self::class, 'createAuthenticator'])
            ->addArgument(new Reference(SamlAuthFactory::class))
            ->addArgument(new Reference(SamlUserResolverInterface::class))
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(SamlLoginConfiguration::class))
            ->addArgument(new Reference(SamlSessionManager::class))
            ->addTag('security.authenticator');

        // Entry Point
        $builder->register(SamlEntryPoint::class)
            ->addArgument(new Reference(SamlAuthFactory::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class));

        // SAML Session Manager
        $builder->register(SamlSessionManager::class);

        // Logout Handler
        $builder->register(SamlLogoutHandler::class)
            ->addArgument(new Reference(SamlAuthFactory::class))
            ->addArgument(new Reference(AuthenticationSession::class));

        // Logout Listener (wp_logout → SAML SLO)
        $builder->register(SamlLogoutListener::class)
            ->addArgument(new Reference(SamlLogoutHandler::class))
            ->addArgument(new Reference(SamlSessionManager::class));

        // SP Metadata Exporter
        $builder->register(SpMetadataExporter::class)
            ->addArgument(new Reference(SamlConfiguration::class));

        // Metadata Controller
        $builder->register(SamlMetadataController::class)
            ->addArgument(new Reference(SpMetadataExporter::class));

        // ACS Controller
        $builder->register(SamlAcsController::class)
            ->addArgument(new Reference(AuthenticationManagerInterface::class));

        // SLO Controller
        $builder->register(SamlSloController::class)
            ->addArgument(new Reference(SamlLogoutHandler::class))
            ->addArgument(new Reference(SamlSessionManager::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class));

        // Login Form (mixed mode)
        $builder->register(SamlLoginForm::class)
            ->addArgument(new Reference(SamlEntryPoint::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class));

        // Route Registry
        if (!$builder->hasDefinition(RouteRegistry::class)) {
            $builder->register(RouteRegistry::class)
                ->addArgument(new Reference(Request::class));
        }
    }

    public static function createIdpSettings(SamlLoginConfiguration $config): IdpSettings
    {
        return new IdpSettings(
            entityId: $config->idpEntityId,
            ssoUrl: $config->idpSsoUrl,
            sloUrl: $config->idpSloUrl,
            x509Cert: $config->idpX509Cert,
            certFingerprint: $config->idpCertFingerprint,
        );
    }

    public static function createSpSettings(SamlLoginConfiguration $config): SpSettings
    {
        return new SpSettings(
            entityId: $config->spEntityId !== '' ? $config->spEntityId : home_url(),
            acsUrl: $config->spAcsUrl !== '' ? $config->spAcsUrl : home_url('/saml/acs'),
            sloUrl: $config->spSloUrl !== '' ? $config->spSloUrl : home_url('/saml/slo'),
            nameIdFormat: $config->spNameIdFormat,
        );
    }

    public static function createSamlConfiguration(
        SamlLoginConfiguration $config,
        IdpSettings $idpSettings,
        SpSettings $spSettings,
    ): SamlConfiguration {
        return new SamlConfiguration(
            idpSettings: $idpSettings,
            spSettings: $spSettings,
            strict: $config->strict,
            debug: $config->debug,
            wantAssertionsSigned: $config->wantAssertionsSigned,
            allowRepeatAttributeName: $config->allowRepeatAttributeName,
        );
    }

    public static function createUserResolver(SamlLoginConfiguration $config): SamlUserResolver
    {
        return new SamlUserResolver(
            autoProvision: $config->autoProvision,
            defaultRole: $config->defaultRole,
            roleMapping: $config->roleMapping,
            roleAttribute: $config->roleAttribute,
        );
    }

    public static function createAuthenticator(
        SamlAuthFactory $authFactory,
        SamlUserResolverInterface $userResolver,
        EventDispatcherInterface $dispatcher,
        SamlLoginConfiguration $config,
        SamlSessionManager $sessionManager,
    ): SamlAuthenticator {
        return new SamlAuthenticator(
            authFactory: $authFactory,
            userResolver: $userResolver,
            dispatcher: $dispatcher,
            sessionManager: $sessionManager,
            addUserToBlog: $config->addUserToBlog,
        );
    }
}
