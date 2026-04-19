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

namespace WPPack\Plugin\RoleProvisioningPlugin\DependencyInjection;

use Psr\Log\LoggerInterface;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsPage;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;
use WPPack\Component\User\UserRepositoryInterface;
use WPPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

final class RoleProvisioningPluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Request
        if (!$builder->hasDefinition(Request::class)) {
            $builder->register(Request::class)
                ->setFactory([Request::class, 'createFromGlobals']);
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

        // Role Provider
        if (!$builder->hasDefinition(RoleProvider::class)) {
            $builder->register(RoleProvider::class);
        }

        // Blog Context
        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContextInterface::class, BlogContext::class);
        }

        // Logger
        if (!$builder->hasDefinition(LoggerInterface::class)) {
            (new LoggerServiceProvider())->register($builder);
        }

        // Configuration
        $builder->register(RoleProvisioningConfiguration::class)
            ->setFactory([RoleProvisioningConfiguration::class, 'fromOption']);

        // Admin Settings Page
        $builder->register(RoleProvisioningSettingsPage::class);

        // REST API Settings Controller
        $builder->register(RoleProvisioningSettingsController::class)
            ->addArgument(new Reference(RoleProvider::class));

        // Role Provisioner
        // User Repository
        if (!$builder->hasDefinition(UserRepositoryInterface::class)) {
            $builder->register(UserRepositoryInterface::class, \WPPack\Component\User\UserRepository::class);
        }

        $builder->register(RoleProvisioner::class)
            ->addArgument(new Reference(RoleProvisioningConfiguration::class))
            ->addArgument(new Reference(RoleProvider::class))
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(LoggerInterface::class));
    }
}
