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

namespace WpPack\Plugin\RoleProvisioningPlugin\DependencyInjection;

use Psr\Log\LoggerInterface;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WpPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsPage;
use WpPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;
use WpPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

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
        $builder->register(RoleProvisioner::class)
            ->addArgument(new Reference(RoleProvisioningConfiguration::class))
            ->addArgument(new Reference(RoleProvider::class))
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addArgument(new Reference(LoggerInterface::class));
    }
}
