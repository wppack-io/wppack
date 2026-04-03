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

namespace WpPack\Plugin\SamlLoginPlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Bridge\SAML\SamlAcsController;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutListener;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;
use WpPack\Component\Security\Bridge\SAML\SamlSloController;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsController;
use WpPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsPage;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;
use WpPack\Plugin\SamlLoginPlugin\DependencyInjection\SamlLoginPluginServiceProvider;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginForm;

#[TextDomain(domain: 'wppack-saml-login')]
final class SamlLoginPlugin extends AbstractPlugin
{
    private readonly SamlLoginPluginServiceProvider $serviceProvider;
    private ?RouteRegistry $router = null;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new SamlLoginPluginServiceProvider();
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
            new RegisterAuthenticatorsPass(),
            new RegisterEventListenersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var SamlLoginConfiguration $config */
        $config = $container->get(SamlLoginConfiguration::class);

        // SAML authentication requires at minimum an IdP Entity ID
        $samlConfigured = $config->idpEntityId !== '' && $config->idpSsoUrl !== '';

        if ($samlConfigured) {
            /** @var AuthenticationManager $authManager */
            $authManager = $container->get(AuthenticationManager::class);
            $authManager->register();
        }

        if ($samlConfigured) {
            /** @var SamlLoginForm $loginForm */
            $loginForm = $container->get(SamlLoginForm::class);

            if ($config->ssoOnly) {
                /** @var SamlEntryPoint $entryPoint */
                $entryPoint = $container->get(SamlEntryPoint::class);
                $entryPoint->register();
            }

            // Always register login form for SSO buttons (needed for interim-login modal)
            $loginForm->register(ssoOnly: $config->ssoOnly);

            /** @var RouteRegistry $router */
            $router = $container->get(RouteRegistry::class);
            $this->router = $router;
            $router->addRoute($config->metadataPath, $container->get(SamlMetadataController::class), name: 'saml_metadata', methods: ['GET']);
            $router->addRoute($config->acsPath, $container->get(SamlAcsController::class), name: 'saml_acs', methods: ['POST']);
            $router->addRoute($config->sloPath, $container->get(SamlSloController::class), name: 'saml_slo');

            /** @var SamlLogoutListener $logoutListener */
            $logoutListener = $container->get(SamlLogoutListener::class);
            $logoutListener->register();
        }

        // Admin Settings Page
        if (is_admin() || is_network_admin()) {
            /** @var SamlLoginSettingsPage $settingsPage */
            $settingsPage = $container->get(SamlLoginSettingsPage::class);
            $settingsPage->setPluginFile($this->getFile());

            /** @var AdminPageRegistry $adminRegistry */
            $adminRegistry = $container->get(AdminPageRegistry::class);
            $adminRegistry->register($settingsPage, $this->isNetworkActivated());
        }

        // REST API Settings Endpoint
        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(SamlLoginSettingsController::class));
    }

    public function onActivate(): void
    {
        $this->router?->flush();
    }

    public function onDeactivate(): void
    {
        $this->router?->invalidate();
    }
}
