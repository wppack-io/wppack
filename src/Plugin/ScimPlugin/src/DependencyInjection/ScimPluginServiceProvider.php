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

namespace WpPack\Plugin\ScimPlugin\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Scim\Authentication\ScimBearerAuthenticator;
use WpPack\Component\Scim\Controller\GroupController;
use WpPack\Component\Scim\Controller\ResourceTypeController;
use WpPack\Component\Scim\Controller\SchemaController;
use WpPack\Component\Scim\Controller\ServiceProviderConfigController;
use WpPack\Component\Scim\Controller\UserController;
use WpPack\Component\Scim\DependencyInjection\ScimServiceProvider;
use WpPack\Component\Scim\Schema\ServiceProviderConfig;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Site\BlogSwitcher;
use WpPack\Component\Site\BlogSwitcherInterface;
use WpPack\Component\User\UserRepository;
use WpPack\Component\User\UserRepositoryInterface;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;

final class ScimPluginServiceProvider implements ServiceProviderInterface
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

        // Configuration
        $builder->register(ScimConfiguration::class)
            ->setFactory([ScimConfiguration::class, 'fromEnvironment']);

        // ServiceProviderConfig (with maxResults from config)
        $builder->register(ServiceProviderConfig::class)
            ->setFactory([self::class, 'createServiceProviderConfig'])
            ->addArgument(new Reference(ScimConfiguration::class));

        // Register SCIM component services
        (new ScimServiceProvider())->register($builder);

        // Override controller arguments with plugin-specific config
        $builder->findDefinition(UserController::class)
            ->setArgument('$maxResults', '%scim.max_results%')
            ->setArgument('$baseUrl', '%scim.base_url%')
            ->setArgument('$defaultRole', '%scim.default_role%')
            ->setArgument('$allowUserDeletion', '%scim.allow_user_deletion%')
            ->setArgument('$autoProvision', '%scim.auto_provision%')
            ->setArgument('$blogId', '%scim.blog_id%')
            ->setArgument('$blogSwitcher', new Reference(BlogSwitcherInterface::class));

        $builder->findDefinition(GroupController::class)
            ->setArgument('$maxResults', '%scim.max_results%')
            ->setArgument('$baseUrl', '%scim.base_url%')
            ->setArgument('$allowGroupManagement', '%scim.allow_group_management%')
            ->setArgument('$blogId', '%scim.blog_id%')
            ->setArgument('$blogSwitcher', new Reference(BlogSwitcherInterface::class));

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
