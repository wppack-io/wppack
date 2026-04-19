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

namespace WPPack\Plugin\SamlLoginPlugin\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Routing\RouteRegistry;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WPPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WPPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WPPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;
use WPPack\Component\Security\Bridge\SAML\SamlAcsController;
use WPPack\Component\Security\Bridge\SAML\SamlAuthenticator;
use WPPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WPPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WPPack\Component\Security\Bridge\SAML\SamlLogoutListener;
use WPPack\Component\Security\Bridge\SAML\SamlMetadataController;
use WPPack\Component\Security\Bridge\SAML\SamlSloController;
use WPPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;
use WPPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;
use WPPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Transient\TransientManager;
use WPPack\Component\User\UserRepositoryInterface;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsController;
use WPPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsPage;
use WPPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;
use WPPack\Plugin\SamlLoginPlugin\SamlLoginForm;

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

        // Sanitizer
        if (!$builder->hasDefinition(Sanitizer::class)) {
            $builder->register(Sanitizer::class);
        }

        // Blog Context
        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContextInterface::class, BlogContext::class);
        }

        // Transient Manager
        if (!$builder->hasDefinition(TransientManager::class)) {
            $builder->register(TransientManager::class);
        }

        // Admin Page Registry
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        // REST Registry
        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        // Configuration (constant > wp_options > env > default)
        $builder->register(SamlLoginConfiguration::class)
            ->setFactory([SamlLoginConfiguration::class, 'fromEnvironmentOrOptions']);

        // Admin Settings Page
        $builder->register(SamlLoginSettingsPage::class);

        // REST API Settings Controller
        $builder->register(SamlLoginSettingsController::class)
            ->addArgument(new Reference(SamlLoginConfiguration::class))
            ->addArgument(new Reference(Sanitizer::class))
            ->addArgument(new Reference(SpMetadataExporter::class));

        // Role Provider
        if (!$builder->hasDefinition(\WPPack\Component\Role\RoleProvider::class)) {
            $builder->register(\WPPack\Component\Role\RoleProvider::class);
        }

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
            ->addArgument(new Reference(SamlLoginConfiguration::class))
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(Sanitizer::class));
        $builder->setAlias(SamlUserResolverInterface::class, SamlUserResolver::class);

        // SamlAuthenticator (tagged for RegisterAuthenticatorsPass)
        $builder->register(SamlAuthenticator::class)
            ->setFactory([self::class, 'createAuthenticator'])
            ->addArgument(new Reference(SamlAuthFactory::class))
            ->addArgument(new Reference(SamlUserResolverInterface::class))
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(SamlLoginConfiguration::class))
            ->addArgument(new Reference(SamlSessionManager::class))
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addArgument(new Reference(TransientManager::class))
            ->addTag('security.authenticator');

        // Entry Point
        $builder->register(SamlEntryPoint::class)
            ->addArgument(new Reference(SamlAuthFactory::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class))
            ->addArgument(new Reference(TransientManager::class));

        // SAML Session Manager
        $builder->register(SamlSessionManager::class)
            ->addArgument(new Reference(UserRepositoryInterface::class));

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
            ->addArgument(new Reference(Request::class))
            ->addArgument(new Reference(BlogContextInterface::class));

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
        $blogId = is_multisite() ? get_main_site_id() : null;

        return new SpSettings(
            entityId: $config->spEntityId !== '' ? $config->spEntityId : get_home_url($blogId),
            acsUrl: get_home_url($blogId, $config->acsPath),
            sloUrl: get_home_url($blogId, $config->sloPath),
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

    public static function createUserResolver(
        SamlLoginConfiguration $config,
        EventDispatcherInterface $dispatcher,
        UserRepositoryInterface $userRepository,
        Sanitizer $sanitizer,
    ): SamlUserResolver {
        return new SamlUserResolver(
            userRepository: $userRepository,
            sanitizer: $sanitizer,
            autoProvision: $config->autoProvision,
            emailAttribute: $config->emailAttribute,
            firstNameAttribute: $config->firstNameAttribute,
            lastNameAttribute: $config->lastNameAttribute,
            displayNameAttribute: $config->displayNameAttribute,
            dispatcher: $dispatcher,
        );
    }

    public static function createAuthenticator(
        SamlAuthFactory $authFactory,
        SamlUserResolverInterface $userResolver,
        EventDispatcherInterface $dispatcher,
        SamlLoginConfiguration $config,
        SamlSessionManager $sessionManager,
        BlogContextInterface $blogContext,
        TransientManager $transientManager,
    ): SamlAuthenticator {
        $crossSiteRedirector = $blogContext->isMultisite()
            ? new CrossSiteRedirector(acsPath: $config->acsPath)
            : null;

        return new SamlAuthenticator(
            authFactory: $authFactory,
            userResolver: $userResolver,
            dispatcher: $dispatcher,
            blogContext: $blogContext,
            sessionManager: $sessionManager,
            acsPath: $config->acsPath,
            crossSiteRedirector: $crossSiteRedirector,
            transientManager: $transientManager,
        );
    }
}
