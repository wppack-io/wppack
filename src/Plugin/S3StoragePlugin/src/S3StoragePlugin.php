<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Plugin\S3StoragePlugin\DependencyInjection\S3StoragePluginServiceProvider;

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
}
