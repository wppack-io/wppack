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

namespace WpPack\Component\Scim\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Scim\Controller\GroupController;
use WpPack\Component\Scim\Controller\ResourceTypeController;
use WpPack\Component\Scim\Controller\SchemaController;
use WpPack\Component\Scim\Controller\ServiceProviderConfigController;
use WpPack\Component\Scim\Controller\UserController;
use WpPack\Component\Scim\Filter\FilterParser;
use WpPack\Component\Scim\Filter\WpUserQueryAdapter;
use WpPack\Component\Scim\Mapping\GroupMapper;
use WpPack\Component\Scim\Mapping\GroupMapperInterface;
use WpPack\Component\Scim\Authentication\ScimUserStatusChecker;
use WpPack\Component\Scim\Mapping\UserAttributeMapper;
use WpPack\Component\Scim\Mapping\UserAttributeMapperInterface;
use WpPack\Component\Scim\Patch\PatchProcessor;
use WpPack\Component\Scim\Repository\ScimGroupRepository;
use WpPack\Component\Scim\Repository\ScimUserRepository;
use WpPack\Component\Scim\Schema\ServiceProviderConfig;
use WpPack\Component\Scim\Serialization\ScimGroupSerializer;
use WpPack\Component\Scim\Serialization\ScimUserSerializer;
use WpPack\Component\User\UserRepositoryInterface;

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
