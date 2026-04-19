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

namespace WPPack\Plugin\RoleProvisioningPlugin;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WPPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsPage;
use WPPack\Plugin\RoleProvisioningPlugin\DependencyInjection\RoleProvisioningPluginServiceProvider;
use WPPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

#[TextDomain(domain: 'wppack-role-provisioning')]
final class RoleProvisioningPlugin extends AbstractPlugin
{
    private readonly RoleProvisioningPluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new RoleProvisioningPluginServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->serviceProvider->register($builder);
    }

    public function boot(Container $container): void
    {
        /** @var AdminPageRegistry $pageRegistry */
        $pageRegistry = $container->get(AdminPageRegistry::class);
        /** @var RoleProvisioningSettingsPage $settingsPage */
        $settingsPage = $container->get(RoleProvisioningSettingsPage::class);
        $settingsPage->setPluginFile($this->getFile());
        $pageRegistry->register($settingsPage, $this->isNetworkActivated());

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(RoleProvisioningSettingsController::class));

        /** @var RoleProvisioner $provisioner */
        $provisioner = $container->get(RoleProvisioner::class);
        $provisioner->register();
    }
}
