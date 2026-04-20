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

namespace WPPack\Plugin\ScimPlugin\DependencyInjection;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Scim\Authentication\ScimBearerAuthenticator;
use WPPack\Component\Scim\Controller\GroupController;
use WPPack\Component\Scim\Controller\ResourceTypeController;
use WPPack\Component\Scim\Controller\SchemaController;
use WPPack\Component\Scim\Controller\ServiceProviderConfigController;
use WPPack\Component\Scim\Controller\UserController;
use WPPack\Component\Scim\DependencyInjection\ScimServiceProvider;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Repository\ScimUserRepository;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Site\BlogSwitcher;
use WPPack\Component\Site\BlogSwitcherInterface;
use WPPack\Component\Site\SiteRepository;
use WPPack\Component\Site\SiteRepositoryInterface;
use WPPack\Component\User\UserRepository;
use WPPack\Component\User\UserRepositoryInterface;
use WPPack\Plugin\ScimPlugin\Admin\ScimSettingsController;
use WPPack\Plugin\ScimPlugin\Admin\ScimSettingsPage;
use WPPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;

final class ScimPluginServiceProvider implements ServiceProviderInterface
{
    /**
     * Register admin/settings services (always, even without SCIM token).
     */
    public function registerAdmin(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        if (!$builder->hasDefinition(RoleProvider::class)) {
            $builder->register(RoleProvider::class);
        }

        $builder->register(ScimSettingsPage::class);

        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContext::class);
            $builder->setAlias(BlogContextInterface::class, BlogContext::class);
        }

        if (!$builder->hasDefinition(OptionManager::class)) {
            $builder->register(OptionManager::class);
        }

        $builder->register(ScimSettingsController::class)
            ->addArgument(new Reference(RoleProvider::class))
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addArgument(new Reference(OptionManager::class));
    }

    public function register(ContainerBuilder $builder): void
    {
        // Auto-register dependent service providers
        if (!$builder->hasDefinition(EventDispatcher::class)) {
            (new EventDispatcherServiceProvider())->register($builder);
        }

        if (!$builder->hasDefinition(AuthenticationManager::class)) {
            (new SecurityServiceProvider())->register($builder);
        }

        // User Repository
        if (!$builder->hasDefinition(UserRepository::class)) {
            $builder->register(UserRepository::class);
            $builder->setAlias(UserRepositoryInterface::class, UserRepository::class);
        }

        // REST Registry
        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        // Site component services
        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContextInterface::class, BlogContext::class);
        }
        if (!$builder->hasDefinition(BlogSwitcherInterface::class)) {
            $builder->register(BlogSwitcherInterface::class, BlogSwitcher::class)
                ->addArgument(new Reference(BlogContextInterface::class));
        }
        if (!$builder->hasDefinition(SiteRepositoryInterface::class)) {
            $builder->register(SiteRepositoryInterface::class, SiteRepository::class);
        }

        // Configuration
        $builder->register(ScimConfiguration::class)
            ->setFactory([ScimConfiguration::class, 'fromEnvironment']);

        // ServiceProviderConfig (with maxResults from config)
        $builder->register(ServiceProviderConfig::class)
            ->setFactory([self::class, 'createServiceProviderConfig'])
            ->addArgument(new Reference(ScimConfiguration::class));

        // Register SCIM component services
        (new ScimServiceProvider())->register($builder);

        // Override repository Site dependencies for multisite support
        $builder->findDefinition(ScimUserRepository::class)
            ->setArgument('$blogSwitcher', new Reference(BlogSwitcherInterface::class))
            ->setArgument('$siteRepository', new Reference(SiteRepositoryInterface::class));

        $builder->findDefinition(ScimGroupRepository::class)
            ->setArgument('$blogSwitcher', new Reference(BlogSwitcherInterface::class))
            ->setArgument('$siteRepository', new Reference(SiteRepositoryInterface::class));

        // Override controller arguments with plugin-specific config
        $builder->findDefinition(UserController::class)
            ->setArgument('$maxResults', '%scim.max_results%')
            ->setArgument('$baseUrl', '%scim.base_url%')
            ->setArgument('$defaultRole', '%scim.default_role%')
            ->setArgument('$allowUserDeletion', '%scim.allow_user_deletion%')
            ->setArgument('$autoProvision', '%scim.auto_provision%');

        $builder->findDefinition(GroupController::class)
            ->setArgument('$maxResults', '%scim.max_results%')
            ->setArgument('$baseUrl', '%scim.base_url%')
            ->setArgument('$allowGroupManagement', '%scim.allow_group_management%');

        $builder->findDefinition(ServiceProviderConfigController::class)
            ->setArgument('$baseUrl', '%scim.base_url%');

        $builder->findDefinition(SchemaController::class)
            ->setArgument('$baseUrl', '%scim.base_url%');

        $builder->findDefinition(ResourceTypeController::class)
            ->setArgument('$baseUrl', '%scim.base_url%');

        // SCIM Bearer Authenticator
        $builder->register(ScimBearerAuthenticator::class)
            ->setFactory([self::class, 'createAuthenticator'])
            ->addArgument(new Reference(ScimConfiguration::class))
            ->addTag('security.authenticator');
    }

    public static function createServiceProviderConfig(ScimConfiguration $config): ServiceProviderConfig
    {
        return new ServiceProviderConfig(maxResults: $config->maxResults);
    }

    public static function createAuthenticator(ScimConfiguration $config): ScimBearerAuthenticator
    {
        return new ScimBearerAuthenticator(
            bearerToken: $config->bearerToken,
        );
    }
}
