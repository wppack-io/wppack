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

namespace WpPack\Plugin\RoleProvisioningPlugin;

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsController;
use WpPack\Plugin\RoleProvisioningPlugin\Admin\RoleProvisioningSettingsPage;
use WpPack\Plugin\RoleProvisioningPlugin\DependencyInjection\RoleProvisioningPluginServiceProvider;
use WpPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

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
