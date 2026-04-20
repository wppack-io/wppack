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

namespace WPPack\Plugin\OAuthLoginPlugin;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Routing\RouteRegistry;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\Bridge\OAuth\OAuthAuthorizeController;
use WPPack\Component\Security\Bridge\OAuth\OAuthCallbackController;
use WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;
use WPPack\Component\Security\Bridge\OAuth\OAuthVerifyController;
use WPPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WPPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsController;
use WPPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsPage;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\DependencyInjection\OAuthLoginPluginServiceProvider;

#[TextDomain(domain: 'wppack-oauth-login')]
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
        if (is_admin() || is_network_admin()) {
            /** @var OAuthLoginSettingsPage $settingsPage */
            $settingsPage = $container->get(OAuthLoginSettingsPage::class);
            $settingsPage->setPluginFile($this->getFile());

            /** @var AdminPageRegistry $adminRegistry */
            $adminRegistry = $container->get(AdminPageRegistry::class);
            $adminRegistry->register($settingsPage, $this->isNetworkActivated());
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
            if (!$container->has($entryPointId)) {
                continue;
            }
            /** @var OAuthEntryPoint $entryPoint */
            $entryPoint = $container->get($entryPointId);
            $entryPoints[$name] = $entryPoint;

            $authorizeController = new OAuthAuthorizeController($entryPoint, $request);

            $router->addRoute($config->getAuthorizePath($name), $authorizeController, name: 'oauth_' . $name . '_authorize', methods: ['GET']);
            $router->addRoute($config->getCallbackPath($name), $container->get(OAuthCallbackController::class), name: 'oauth_' . $name . '_callback', methods: ['GET']);
            $router->addRoute($config->getVerifyPath($name), $container->get(OAuthVerifyController::class), name: 'oauth_' . $name . '_verify', methods: ['POST']);
        }

        /** @var OAuthLoginForm $loginForm */
        $loginForm = $container->get(OAuthLoginForm::class);

        // Register login form or SSO-only entry point
        if ($config->ssoOnly) {
            // In SSO-only mode with a single provider, redirect directly
            if (\count($entryPoints) === 1) {
                $singleEntryPoint = reset($entryPoints);
                $singleEntryPoint->register();
            }
        }

        // Always register login form for SSO buttons (needed for interim-login modal)
        $loginForm->register(ssoOnly: $config->ssoOnly);
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
