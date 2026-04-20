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

namespace WPPack\Component\Scim\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Scim\Authentication\ScimUserStatusChecker;
use WPPack\Component\Scim\Controller\GroupController;
use WPPack\Component\Scim\Controller\ResourceTypeController;
use WPPack\Component\Scim\Controller\SchemaController;
use WPPack\Component\Scim\Controller\ServiceProviderConfigController;
use WPPack\Component\Scim\Controller\UserController;
use WPPack\Component\Scim\Filter\FilterParser;
use WPPack\Component\Scim\Filter\WpUserQueryAdapter;
use WPPack\Component\Scim\Mapping\GroupMapper;
use WPPack\Component\Scim\Mapping\GroupMapperInterface;
use WPPack\Component\Scim\Mapping\UserAttributeMapper;
use WPPack\Component\Scim\Mapping\UserAttributeMapperInterface;
use WPPack\Component\Scim\Patch\PatchProcessor;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Repository\ScimUserRepository;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;
use WPPack\Component\Scim\Serialization\ScimGroupSerializer;
use WPPack\Component\Scim\Serialization\ScimUserSerializer;
use WPPack\Component\User\UserRepositoryInterface;

final class ScimServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Sanitizer
        if (!$builder->hasDefinition(Sanitizer::class)) {
            $builder->register(Sanitizer::class);
        }

        // RoleProvider
        if (!$builder->hasDefinition(RoleProvider::class)) {
            $builder->register(RoleProvider::class);
        }

        // Schema
        if (!$builder->hasDefinition(ServiceProviderConfig::class)) {
            $builder->register(ServiceProviderConfig::class);
        }

        // Mapping
        $builder->register(UserAttributeMapper::class)
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(Sanitizer::class))
            ->addArgument(new Reference(EventDispatcherInterface::class));
        $builder->setAlias(UserAttributeMapperInterface::class, UserAttributeMapper::class);

        $builder->register(GroupMapper::class);
        $builder->setAlias(GroupMapperInterface::class, GroupMapper::class);

        // Authentication
        $builder->register(ScimUserStatusChecker::class)
            ->addArgument(new Reference(UserRepositoryInterface::class));

        // Serialization
        $builder->register(ScimUserSerializer::class)
            ->addArgument(new Reference(UserAttributeMapperInterface::class))
            ->addArgument(new Reference(RoleProvider::class))
            ->addArgument(new Reference(ScimGroupRepository::class));

        $builder->register(ScimGroupSerializer::class)
            ->addArgument(new Reference(GroupMapperInterface::class));

        // Filter
        $builder->register(FilterParser::class);
        $builder->register(WpUserQueryAdapter::class)
            ->addArgument(new Reference(UserRepositoryInterface::class));

        // Patch
        $builder->register(PatchProcessor::class);

        // Repository
        $builder->register(ScimUserRepository::class)
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(WpUserQueryAdapter::class));

        $builder->register(ScimGroupRepository::class)
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(RoleProvider::class));

        // Controllers
        $builder->register(UserController::class)
            ->addArgument(new Reference(ScimUserRepository::class))
            ->addArgument(new Reference(UserAttributeMapperInterface::class))
            ->addArgument(new Reference(ScimUserSerializer::class))
            ->addArgument(new Reference(PatchProcessor::class))
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(FilterParser::class))
            ->addTag('rest.controller');

        $builder->register(GroupController::class)
            ->addArgument(new Reference(ScimGroupRepository::class))
            ->addArgument(new Reference(ScimGroupSerializer::class))
            ->addArgument(new Reference(PatchProcessor::class))
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(Sanitizer::class))
            ->addTag('rest.controller');

        $builder->register(ServiceProviderConfigController::class)
            ->addArgument(new Reference(ServiceProviderConfig::class))
            ->addTag('rest.controller');

        $builder->register(SchemaController::class)
            ->addTag('rest.controller');

        $builder->register(ResourceTypeController::class)
            ->addTag('rest.controller');
    }
}
