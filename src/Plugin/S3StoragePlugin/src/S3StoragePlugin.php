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

namespace WpPack\Plugin\S3StoragePlugin;

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsController;
use WpPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsPage;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\DependencyInjection\S3StoragePluginServiceProvider;

#[TextDomain(domain: 'wppack-storage')]
final class S3StoragePlugin extends AbstractPlugin
{
    private readonly S3StoragePluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new S3StoragePluginServiceProvider($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->serviceProvider->registerAdmin($builder);

        if (!S3StorageConfiguration::hasConfiguration()) {
            return;
        }

        $this->serviceProvider->register($builder);
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return [
            new RegisterMessageHandlersPass(),
            new RegisterHookSubscribersPass(),
            new RegisterRestControllersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        if (is_admin() || is_network_admin()) {
            /** @var AdminPageRegistry $pageRegistry */
            $pageRegistry = $container->get(AdminPageRegistry::class);
            /** @var S3StorageSettingsPage $settingsPage */
            $settingsPage = $container->get(S3StorageSettingsPage::class);
            $settingsPage->setPluginFile($this->getFile());
            $pageRegistry->register($settingsPage);

            /** @var RestRegistry $restRegistry */
            $restRegistry = $container->get(RestRegistry::class);
            $restRegistry->register($container->get(S3StorageSettingsController::class));
        }
    }
}
