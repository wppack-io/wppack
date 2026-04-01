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

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\ScimPlugin\Admin\ScimSettingsController;
use WpPack\Plugin\ScimPlugin\Admin\ScimSettingsPage;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;
use WpPack\Plugin\ScimPlugin\DependencyInjection\ScimPluginServiceProvider;

#[TextDomain(domain: 'wppack-scim')]
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
        if (!is_main_site()) {
            return;
        }

        // Always register admin/settings services
        $this->serviceProvider->registerAdmin($builder);

        // Skip SCIM services if no token configured
        if (!ScimConfiguration::hasToken()) {
            return;
        }

        $config = ScimConfiguration::fromEnvironmentOrOptions();

        $builder->setParameter('scim.max_results', $config->maxResults);
        $builder->setParameter('scim.base_url', rtrim(rest_url(), '/'));
        $builder->setParameter('scim.default_role', $config->defaultRole);
        $builder->setParameter('scim.allow_group_management', $config->allowGroupManagement);
        $builder->setParameter('scim.allow_user_deletion', $config->allowUserDeletion);
        $builder->setParameter('scim.auto_provision', $config->autoProvision);

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
        if (!is_main_site()) {
            return;
        }

        // Admin settings page
        if (is_admin() || is_network_admin()) {
            /** @var AdminPageRegistry $pageRegistry */
            $pageRegistry = $container->get(AdminPageRegistry::class);
            /** @var ScimSettingsPage $settingsPage */
            $settingsPage = $container->get(ScimSettingsPage::class);
            $settingsPage->setPluginFile($this->getFile());
            $pageRegistry->register($settingsPage, $this->isNetworkActivated());

            /** @var RestRegistry $restRegistry */
            $restRegistry = $container->get(RestRegistry::class);
            $restRegistry->register($container->get(ScimSettingsController::class));
        }

        // SCIM services (only when token is configured)
        if (!$container->has(AuthenticationManager::class)) {
            return;
        }

        /** @var AuthenticationManager $authManager */
        $authManager = $container->get(AuthenticationManager::class);
        $authManager->register();
    }
}
