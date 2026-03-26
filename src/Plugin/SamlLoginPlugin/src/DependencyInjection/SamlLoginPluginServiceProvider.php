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
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlAuthenticator;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;
use WpPack\Plugin\SamlLoginPlugin\Route\SamlRouteRegistrar;

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
            ->addTag('security.authenticator');

        // Entry Point
        $builder->register(SamlEntryPoint::class)
            ->addArgument(new Reference(SamlAuthFactory::class));

        // Logout Handler
        $builder->register(SamlLogoutHandler::class)
            ->addArgument(new Reference(SamlAuthFactory::class));

        // Metadata Controller
        $builder->register(SamlMetadataController::class)
            ->addArgument(new Reference(SamlConfiguration::class));

        // Route Registrar
        $builder->register(SamlRouteRegistrar::class)
            ->addArgument(new Reference(SamlMetadataController::class))
            ->addArgument(new Reference(SamlLogoutHandler::class));
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
    ): SamlAuthenticator {
        return new SamlAuthenticator(
            authFactory: $authFactory,
            userResolver: $userResolver,
            dispatcher: $dispatcher,
            addUserToBlog: $config->addUserToBlog,
        );
    }
}
