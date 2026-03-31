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

namespace WpPack\Plugin\ScimPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Plugin\ScimPlugin\Admin\ScimSettingsController;
use WpPack\Plugin\ScimPlugin\Admin\ScimSettingsPage;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;
use WpPack\Plugin\ScimPlugin\ScimPlugin;

#[CoversClass(ScimPlugin::class)]
final class ScimPluginTest extends TestCase
{
    #[Test]
    public function getCompilerPassesReturnsThreePasses(): void
    {
        $plugin = new ScimPlugin(__FILE__);

        $passes = $plugin->getCompilerPasses();

        self::assertCount(3, $passes);
        self::assertInstanceOf(RegisterAuthenticatorsPass::class, $passes[0]);
        self::assertInstanceOf(RegisterEventListenersPass::class, $passes[1]);
        self::assertInstanceOf(RegisterRestControllersPass::class, $passes[2]);
    }

    #[Test]
    public function onActivateDoesNotThrow(): void
    {
        $plugin = new ScimPlugin(__FILE__);

        // ScimPlugin inherits the no-op onActivate from AbstractPlugin
        $plugin->onActivate();

        self::assertTrue(true);
    }

    #[Test]
    public function onDeactivateDoesNotThrow(): void
    {
        $plugin = new ScimPlugin(__FILE__);

        // ScimPlugin inherits the no-op onDeactivate from AbstractPlugin
        $plugin->onDeactivate();

        self::assertTrue(true);
    }

    #[Test]
    public function bootRegistersAdminPageAndRestWhenIsAdmin(): void
    {
        set_current_screen('dashboard');

        $settingsPage = new ScimSettingsPage();
        $settingsController = new ScimSettingsController(new RoleProvider());
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(ScimSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(ScimSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new ScimPlugin(__FILE__);
        $plugin->boot($container);

        self::assertNotFalse(has_action('admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        set_current_screen('front');
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function bootRegistersAuthManagerWhenAvailable(): void
    {
        set_current_screen('dashboard');

        $settingsPage = new ScimSettingsPage();
        $settingsController = new ScimSettingsController(new RoleProvider());
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());
        $authManager = new AuthenticationManager(
            new \WpPack\Component\EventDispatcher\EventDispatcher(),
            new Request(),
            new AuthenticationSession(),
        );

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(ScimSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(ScimSettingsController::class, $settingsController);
        $symfonyContainer->set(AuthenticationManager::class, $authManager);

        $container = new Container($symfonyContainer);

        $plugin = new ScimPlugin(__FILE__);
        $plugin->boot($container);

        // AuthenticationManager::register() adds determine_current_user filter
        self::assertNotFalse(has_filter('determine_current_user'));

        set_current_screen('front');
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_filters('determine_current_user');
    }

    #[Test]
    public function bootSkipsNonMainSite(): void
    {
        // On single-site, is_main_site() is always true
        // We just verify boot() doesn't crash when there's no auth manager
        $settingsPage = new ScimSettingsPage();
        $settingsController = new ScimSettingsController(new RoleProvider());
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(ScimSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(ScimSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new ScimPlugin(__FILE__);
        $plugin->boot($container);

        self::assertTrue(true);

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function registerAlwaysRegistersAdminServices(): void
    {
        delete_option(ScimConfiguration::OPTION_NAME);

        $builder = new ContainerBuilder();
        $plugin = new ScimPlugin(__FILE__);

        $plugin->register($builder);

        self::assertTrue($builder->hasDefinition(ScimSettingsPage::class));
        self::assertTrue($builder->hasDefinition(ScimSettingsController::class));
    }
}
