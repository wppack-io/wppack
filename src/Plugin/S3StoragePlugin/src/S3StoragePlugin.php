<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\PluginInterface;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Plugin\S3StoragePlugin\DependencyInjection\S3StoragePluginServiceProvider;

final class S3StoragePlugin implements PluginInterface
{
    private readonly S3StoragePluginServiceProvider $serviceProvider;

    public function __construct()
    {
        $this->serviceProvider = new S3StoragePluginServiceProvider();
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
            new RegisterHookSubscribersPass(),
            new RegisterRestControllersPass(),
        ];
    }

    public function boot(Container $container): void {}

    public function onActivate(): void {}

    public function onDeactivate(): void {}
}
