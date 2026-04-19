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

namespace WPPack\Plugin\S3StoragePlugin;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WPPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsController;
use WPPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsPage;
use WPPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WPPack\Plugin\S3StoragePlugin\DependencyInjection\S3StoragePluginServiceProvider;

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
        /** @var AdminPageRegistry $pageRegistry */
        $pageRegistry = $container->get(AdminPageRegistry::class);
        /** @var S3StorageSettingsPage $settingsPage */
        $settingsPage = $container->get(S3StorageSettingsPage::class);
        $settingsPage->setPluginFile($this->getFile());
        $pageRegistry->register($settingsPage, $this->isNetworkActivated());

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(S3StorageSettingsController::class));
    }
}
