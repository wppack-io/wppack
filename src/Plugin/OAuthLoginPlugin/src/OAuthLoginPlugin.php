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

namespace WpPack\Plugin\OAuthLoginPlugin;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsController;
use WpPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsPage;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WpPack\Component\Security\Bridge\OAuth\OAuthAuthorizeController;
use WpPack\Component\Security\Bridge\OAuth\OAuthCallbackController;
use WpPack\Component\Security\Bridge\OAuth\OAuthVerifyController;
use WpPack\Plugin\OAuthLoginPlugin\DependencyInjection\OAuthLoginPluginServiceProvider;

class OAuthLoginPlugin extends AbstractPlugin
{
    private readonly OAuthLoginPluginServiceProvider $serviceProvider;
    private ?RouteRegistry $router = null;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new OAuthLoginPluginServiceProvider();
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
        /** @var OAuthLoginConfiguration $config */
        $config = $container->get(OAuthLoginConfiguration::class);

        // Admin Settings Page
        if (is_admin()) {
            /** @var OAuthLoginSettingsPage $settingsPage */
            $settingsPage = $container->get(OAuthLoginSettingsPage::class);
            $settingsPage->setPluginFile($this->getFile());

            /** @var AdminPageRegistry $adminRegistry */
            $adminRegistry = $container->get(AdminPageRegistry::class);
            $adminRegistry->register($settingsPage);
        }

        // REST API Settings Endpoint
        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(OAuthLoginSettingsController::class));

        // Skip OAuth functionality if no providers configured
        if ($config->providers === []) {
            return;
        }

        /** @var AuthenticationManager $authManager */
        $authManager = $container->get(AuthenticationManager::class);
        $authManager->register();

        /** @var Request $request */
        $request = $container->get(Request::class);

        // Register routes
        /** @var RouteRegistry $router */
        $router = $container->get(RouteRegistry::class);
        $this->router = $router;

        // Build entry point map and register per-provider routes
        $entryPoints = [];

        foreach ($config->providers as $name => $providerConfig) {
            $entryPointId = OAuthEntryPoint::class . '.' . $name;
            /** @var OAuthEntryPoint $entryPoint */
            $entryPoint = $container->get($entryPointId);
            $entryPoints[$name] = $entryPoint;

            $authorizeController = new OAuthAuthorizeController($entryPoint, $request);

            $router->addRoute($config->getAuthorizePath($name), $authorizeController, name: 'oauth_' . $name . '_authorize', methods: ['GET']);
            $router->addRoute($config->getCallbackPath($name), $container->get(OAuthCallbackController::class), name: 'oauth_' . $name . '_callback', methods: ['GET']);
            $router->addRoute($config->getVerifyPath($name), $container->get(OAuthVerifyController::class), name: 'oauth_' . $name . '_verify', methods: ['POST']);
        }

        // Register login form or SSO-only entry point
        if ($config->ssoOnly) {
            // In SSO-only mode with a single provider, redirect directly
            if (\count($entryPoints) === 1) {
                $singleEntryPoint = reset($entryPoints);
                $singleEntryPoint->register();
            }
            // With multiple providers, show a provider selection page on login
            // by registering the login form (no WP form, just buttons)
            else {
                /** @var OAuthLoginForm $loginForm */
                $loginForm = $container->get(OAuthLoginForm::class);
                $loginForm->register();
            }
        } else {
            /** @var OAuthLoginForm $loginForm */
            $loginForm = $container->get(OAuthLoginForm::class);
            $loginForm->register();
        }
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
