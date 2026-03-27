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

namespace WpPack\Plugin\ScimPlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Scim\Controller\GroupController;
use WpPack\Component\Scim\Controller\ResourceTypeController;
use WpPack\Component\Scim\Controller\SchemaController;
use WpPack\Component\Scim\Controller\ServiceProviderConfigController;
use WpPack\Component\Scim\Controller\UserController;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\ScimPlugin\DependencyInjection\ScimPluginServiceProvider;

final class ScimPlugin extends AbstractPlugin
{
    private readonly ScimPluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new ScimPluginServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->setParameter('scim.max_results', 100);
        $builder->setParameter('scim.base_url', '');

        $this->serviceProvider->register($builder);
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return [
            new RegisterAuthenticatorsPass(),
            new RegisterEventListenersPass(),
            new RegisterRestControllersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var AuthenticationManager $authManager */
        $authManager = $container->get(AuthenticationManager::class);
        $authManager->register();

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(UserController::class));
        $restRegistry->register($container->get(GroupController::class));
        $restRegistry->register($container->get(ServiceProviderConfigController::class));
        $restRegistry->register($container->get(SchemaController::class));
        $restRegistry->register($container->get(ResourceTypeController::class));
    }
}
