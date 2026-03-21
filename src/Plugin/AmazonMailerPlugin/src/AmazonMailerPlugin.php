<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\PluginInterface;
use WpPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WpPack\Component\Mailer\Mailer;
use WpPack\Plugin\AmazonMailerPlugin\DependencyInjection\AmazonMailerPluginServiceProvider;

final class AmazonMailerPlugin implements PluginInterface
{
    private readonly AmazonMailerPluginServiceProvider $serviceProvider;

    public function __construct()
    {
        $this->serviceProvider = new AmazonMailerPluginServiceProvider();
    }

    public function getPluginFile(): string
    {
        return \dirname(__DIR__) . '/amazon-mailer-plugin.php';
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
            new RegisterTransportFactoriesPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var Mailer $mailer */
        $mailer = $container->get(Mailer::class);
        $mailer->boot();
    }

    public function onActivate(): void {}

    public function onDeactivate(): void {}
}
