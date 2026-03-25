<?php

declare(strict_types=1);

namespace WpPack\Plugin\DebugPlugin;

use WpPack\Component\Debug\DependencyInjection\InjectContainerSnapshotPass;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WpPack\Plugin\DebugPlugin\DependencyInjection\DebugPluginServiceProvider;

final class DebugPlugin extends AbstractPlugin
{
    private readonly DebugPluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new DebugPluginServiceProvider();
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
            new RegisterLoggerPass(),
            new RegisterDataCollectorsPass(),
            new RegisterPanelRenderersPass(),
            new RegisterHookSubscribersPass(),
            new InjectContainerSnapshotPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var ToolbarSubscriber $toolbar */
        $toolbar = $container->get(ToolbarSubscriber::class);
        $toolbar->register();

        /** @var ExceptionHandler $exceptionHandler */
        $exceptionHandler = $container->get(ExceptionHandler::class);
        $exceptionHandler->register();

        /** @var WpDieHandler $wpDieHandler */
        $wpDieHandler = $container->get(WpDieHandler::class);
        $wpDieHandler->register();
    }
}
