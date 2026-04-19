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

namespace WPPack\Plugin\AmazonMailerPlugin;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WPPack\Component\Mailer\Mailer;
use WPPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsController;
use WPPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsPage;
use WPPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;
use WPPack\Plugin\AmazonMailerPlugin\DependencyInjection\AmazonMailerPluginServiceProvider;

#[TextDomain(domain: 'wppack-mailer')]
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
        $this->serviceProvider->registerAdmin($builder);

        if (!AmazonMailerConfiguration::hasConfiguration()) {
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
            new RegisterTransportFactoriesPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var AdminPageRegistry $pageRegistry */
        $pageRegistry = $container->get(AdminPageRegistry::class);
        /** @var AmazonMailerSettingsPage $settingsPage */
        $settingsPage = $container->get(AmazonMailerSettingsPage::class);
        $settingsPage->setPluginFile($this->getFile());
        $pageRegistry->register($settingsPage, $this->isNetworkActivated());

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(AmazonMailerSettingsController::class));

        if (!$container->has(Mailer::class)) {
            return;
        }

        /** @var Mailer $mailer */
        $mailer = $container->get(Mailer::class);
        $mailer->boot();
    }
}
