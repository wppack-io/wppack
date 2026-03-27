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
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;
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
        $config = ScimConfiguration::fromEnvironment();

        $builder->setParameter('scim.max_results', $config->maxResults);
        $builder->setParameter('scim.base_url', rtrim(rest_url(), '/'));
        $builder->setParameter('scim.default_role', $config->defaultRole);
        $builder->setParameter('scim.allow_group_management', $config->allowGroupManagement);
        $builder->setParameter('scim.allow_user_deletion', $config->allowUserDeletion);

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
    }
}
