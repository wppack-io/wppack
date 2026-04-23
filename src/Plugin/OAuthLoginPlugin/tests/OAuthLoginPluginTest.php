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

namespace WPPack\Plugin\OAuthLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Routing\RouteRegistry;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\OAuth\OAuthCallbackController;
use WPPack\Component\Security\Bridge\OAuth\OAuthVerifyController;
use WPPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WPPack\Component\Site\BlogContext;
use WPPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsController;
use WPPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsPage;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\OAuthLoginForm;
use WPPack\Plugin\OAuthLoginPlugin\OAuthLoginPlugin;

#[CoversClass(OAuthLoginPlugin::class)]
final class OAuthLoginPluginTest extends TestCase
{
    #[Test]
    public function getCompilerPassesReturnsCorrectPasses(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);
        $passes = $plugin->getCompilerPasses();

        self::assertCount(2, $passes);
        self::assertInstanceOf(RegisterAuthenticatorsPass::class, $passes[0]);
        self::assertInstanceOf(RegisterEventListenersPass::class, $passes[1]);

        foreach ($passes as $pass) {
            self::assertInstanceOf(CompilerPassInterface::class, $pass);
        }
    }

    #[Test]
    public function onActivateFlushesRewriteRules(): void
    {
        $router = new RouteRegistry(optionManager: new OptionManager());

        $plugin = new OAuthLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        $plugin->onActivate();

        // After flush, rewrite_rules option should exist (non-false)
        self::assertNotFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function onDeactivateInvalidatesRewriteRules(): void
    {
        // Ensure rewrite_rules option exists first
        update_option('rewrite_rules', ['dummy' => 'rule']);

        $router = new RouteRegistry(optionManager: new OptionManager());

        $plugin = new OAuthLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        $plugin->onDeactivate();

        self::assertFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function onActivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $this->expectNotToPerformAssertions();
        $plugin->onActivate();
    }

    #[Test]
    public function onDeactivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $this->expectNotToPerformAssertions();
        $plugin->onDeactivate();
    }

    #[Test]
    public function bootRegistersAdminPageAndRestEndpoint(): void
    {
        set_current_screen('dashboard');

        $config = new OAuthLoginConfiguration(providers: []);
        $settingsPage = new OAuthLoginSettingsPage();
        $settingsController = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
            new BlogContext(),
        );
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(OAuthLoginConfiguration::class, $config);
        $symfonyContainer->set(OAuthLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(OAuthLoginSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new OAuthLoginPlugin(__FILE__);
        $plugin->boot($container);

        self::assertNotFalse(has_action('admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        set_current_screen('front');
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function bootSkipsOAuthWhenNoProvidersConfigured(): void
    {
        $config = new OAuthLoginConfiguration(providers: []);
        $settingsPage = new OAuthLoginSettingsPage();
        $settingsController = new OAuthLoginSettingsController(
            $config,
            new Sanitizer(),
            new BlogContext(),
        );
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(OAuthLoginConfiguration::class, $config);
        $symfonyContainer->set(OAuthLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(OAuthLoginSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new OAuthLoginPlugin(__FILE__);
        $plugin->boot($container);

        // No router set when providers is empty
        $reflection = new \ReflectionProperty($plugin, 'router');
        self::assertNull($reflection->getValue($plugin));

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function bootWithProvidersRegistersAuthAndRoutes(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'gid',
            clientSecret: 'gsecret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(providers: ['google' => $google]);
        $request = new Request();
        $authSession = new AuthenticationSession();
        $loginForm = new OAuthLoginForm([$google], $config, $authSession, $request);
        $authManager = new AuthenticationManager(new EventDispatcher(), $request, $authSession);
        $router = new RouteRegistry(optionManager: new OptionManager());
        $callbackController = new OAuthCallbackController($authManager);
        $verifyController = new OAuthVerifyController($authManager);
        $settingsPage = new OAuthLoginSettingsPage();
        $settingsController = new OAuthLoginSettingsController($config, new Sanitizer(), new BlogContext());
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry($request);

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(OAuthLoginConfiguration::class, $config);
        $symfonyContainer->set(OAuthLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(OAuthLoginSettingsController::class, $settingsController);
        $symfonyContainer->set(AuthenticationManager::class, $authManager);
        $symfonyContainer->set(Request::class, $request);
        $symfonyContainer->set(RouteRegistry::class, $router);
        $symfonyContainer->set(OAuthLoginForm::class, $loginForm);
        $symfonyContainer->set(OAuthCallbackController::class, $callbackController);
        $symfonyContainer->set(OAuthVerifyController::class, $verifyController);
        // No OAuthEntryPoint for 'google' — simulates provider without entry point

        $container = new Container($symfonyContainer);

        $plugin = new OAuthLoginPlugin(__FILE__);
        $plugin->boot($container);

        // Router should be set (providers exist)
        $reflection = new \ReflectionProperty($plugin, 'router');
        self::assertNotNull($reflection->getValue($plugin));

        // Login form register() should have been called
        self::assertNotFalse(has_action('login_footer'));

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_actions('login_footer');
        remove_all_actions('login_init');
        remove_all_filters('wp_login_errors');
        remove_all_filters('determine_current_user');
    }

    #[Test]
    public function bootWithSsoOnlySingleProviderSkipsEntryPointWhenNotAvailable(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'gid',
            clientSecret: 'gsecret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(providers: ['google' => $google], ssoOnly: true);
        $request = new Request();
        $authSession = new AuthenticationSession();
        $authManager = new AuthenticationManager(new EventDispatcher(), $request, $authSession);
        $router = new RouteRegistry(optionManager: new OptionManager());
        $callbackController = new OAuthCallbackController($authManager);
        $verifyController = new OAuthVerifyController($authManager);
        $loginForm = new OAuthLoginForm([$google], $config, $authSession, $request);
        $settingsPage = new OAuthLoginSettingsPage();
        $settingsController = new OAuthLoginSettingsController($config, new Sanitizer(), new BlogContext());
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry($request);

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(OAuthLoginConfiguration::class, $config);
        $symfonyContainer->set(OAuthLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(OAuthLoginSettingsController::class, $settingsController);
        $symfonyContainer->set(AuthenticationManager::class, $authManager);
        $symfonyContainer->set(Request::class, $request);
        $symfonyContainer->set(RouteRegistry::class, $router);
        $symfonyContainer->set(OAuthLoginForm::class, $loginForm);
        $symfonyContainer->set(OAuthCallbackController::class, $callbackController);
        $symfonyContainer->set(OAuthVerifyController::class, $verifyController);

        $container = new Container($symfonyContainer);

        $plugin = new OAuthLoginPlugin(__FILE__);
        $plugin->boot($container);

        // With ssoOnly=true and one provider, no entry point in container,
        // the foreach skips it → entryPoints empty → else branch → loginForm->register()
        $reflection = new \ReflectionProperty($plugin, 'router');
        self::assertNotNull($reflection->getValue($plugin));

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_filters('determine_current_user');
    }

    #[Test]
    public function registerDelegatesToServiceProvider(): void
    {
        $plugin = new OAuthLoginPlugin(__FILE__);
        $builder = new ContainerBuilder();

        $plugin->register($builder);

        // ServiceProvider wires at minimum the settings controller
        self::assertTrue($builder->hasDefinition(OAuthLoginSettingsController::class));
    }

    #[Test]
    public function bootRegistersPerProviderRoutesWhenEntryPointIsAvailable(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'gid',
            clientSecret: 'gsecret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(providers: ['google' => $google]);
        $request = new Request();
        $authSession = new AuthenticationSession();
        $authManager = new AuthenticationManager(new EventDispatcher(), $request, $authSession);
        $router = new RouteRegistry(optionManager: new OptionManager());
        $settingsPage = new OAuthLoginSettingsPage();
        $settingsController = new OAuthLoginSettingsController($config, new Sanitizer(), new BlogContext());

        // Real OAuthEntryPoint wired for the google provider id
        $oauthConfig = new \WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration(
            clientId: 'gid',
            clientSecret: 'gsecret',
            redirectUri: 'https://example.test/callback',
        );
        $provider = new \WPPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider($oauthConfig);
        $transients = new \WPPack\Component\Transient\TransientManager();
        $stateStore = new \WPPack\Component\Security\Bridge\OAuth\State\OAuthStateStore($transients);
        $entryPoint = new \WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint(
            $provider,
            $oauthConfig,
            $stateStore,
            $authSession,
            $request,
        );

        $loginForm = new OAuthLoginForm([$google], $config, $authSession, $request);
        $callbackController = new OAuthCallbackController($authManager);
        $verifyController = new OAuthVerifyController($authManager);

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(OAuthLoginConfiguration::class, $config);
        $symfonyContainer->set(OAuthLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(RestRegistry::class, new RestRegistry($request));
        $symfonyContainer->set(OAuthLoginSettingsController::class, $settingsController);
        $symfonyContainer->set(AuthenticationManager::class, $authManager);
        $symfonyContainer->set(Request::class, $request);
        $symfonyContainer->set(RouteRegistry::class, $router);
        $symfonyContainer->set(OAuthLoginForm::class, $loginForm);
        $symfonyContainer->set(OAuthCallbackController::class, $callbackController);
        $symfonyContainer->set(OAuthVerifyController::class, $verifyController);
        $symfonyContainer->set(\WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint::class . '.google', $entryPoint);

        $container = new Container($symfonyContainer);

        $plugin = new OAuthLoginPlugin(__FILE__);
        $plugin->boot($container);

        // With a real entry point in the container, the provider branch
        // registers authorize/callback/verify routes under the provider id.
        self::assertTrue($router->has('oauth_google_authorize'));
        self::assertTrue($router->has('oauth_google_callback'));
        self::assertTrue($router->has('oauth_google_verify'));

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_actions('login_footer');
        remove_all_filters('determine_current_user');
    }

    #[Test]
    public function bootSsoOnlyWithSingleProviderRegistersEntryPointOnly(): void
    {
        $google = new ProviderConfiguration(
            name: 'google',
            type: 'google',
            clientId: 'gid',
            clientSecret: 'gsecret',
            label: 'Google',
        );

        $config = new OAuthLoginConfiguration(providers: ['google' => $google], ssoOnly: true);
        $request = new Request();
        $authSession = new AuthenticationSession();
        $authManager = new AuthenticationManager(new EventDispatcher(), $request, $authSession);
        $router = new RouteRegistry(optionManager: new OptionManager());
        $settingsPage = new OAuthLoginSettingsPage();
        $settingsController = new OAuthLoginSettingsController($config, new Sanitizer(), new BlogContext());

        $oauthConfig = new \WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration(
            clientId: 'gid',
            clientSecret: 'gsecret',
            redirectUri: 'https://example.test/callback',
        );
        $provider = new \WPPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider($oauthConfig);
        $transients = new \WPPack\Component\Transient\TransientManager();
        $stateStore = new \WPPack\Component\Security\Bridge\OAuth\State\OAuthStateStore($transients);
        $entryPoint = new \WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint(
            $provider,
            $oauthConfig,
            $stateStore,
            $authSession,
            $request,
        );

        $loginForm = new OAuthLoginForm([$google], $config, $authSession, $request);
        $callbackController = new OAuthCallbackController($authManager);
        $verifyController = new OAuthVerifyController($authManager);

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(OAuthLoginConfiguration::class, $config);
        $symfonyContainer->set(OAuthLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(RestRegistry::class, new RestRegistry($request));
        $symfonyContainer->set(OAuthLoginSettingsController::class, $settingsController);
        $symfonyContainer->set(AuthenticationManager::class, $authManager);
        $symfonyContainer->set(Request::class, $request);
        $symfonyContainer->set(RouteRegistry::class, $router);
        $symfonyContainer->set(OAuthLoginForm::class, $loginForm);
        $symfonyContainer->set(OAuthCallbackController::class, $callbackController);
        $symfonyContainer->set(OAuthVerifyController::class, $verifyController);
        $symfonyContainer->set(\WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint::class . '.google', $entryPoint);

        $container = new Container($symfonyContainer);

        $plugin = new OAuthLoginPlugin(__FILE__);
        $plugin->boot($container);

        // ssoOnly + single provider + entry point available →
        // entryPoint->register() attaches login_init / login_url.
        self::assertNotFalse(has_action('login_init'));
        self::assertNotFalse(has_filter('login_url'));

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_actions('login_footer');
        remove_all_actions('login_init');
        remove_all_filters('login_url');
        remove_all_filters('determine_current_user');
    }
}
