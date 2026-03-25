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

namespace WpPack\Plugin\AmazonMailerPlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WpPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WpPack\Component\Mailer\Mailer;
use WpPack\Plugin\AmazonMailerPlugin\DependencyInjection\AmazonMailerPluginServiceProvider;

final class AmazonMailerPlugin extends AbstractPlugin
{
    private readonly AmazonMailerPluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new AmazonMailerPluginServiceProvider();
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
            new RegisterTransportFactoriesPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var Mailer $mailer */
        $mailer = $container->get(Mailer::class);
        $mailer->boot();
    }
}
