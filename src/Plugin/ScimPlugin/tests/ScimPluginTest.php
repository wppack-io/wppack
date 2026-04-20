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

namespace WPPack\Plugin\ScimPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Plugin\ScimPlugin\Admin\ScimSettingsController;
use WPPack\Plugin\ScimPlugin\Admin\ScimSettingsPage;
use WPPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;
use WPPack\Plugin\ScimPlugin\ScimPlugin;

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

        // ScimSettingsPage has scope=Network, so it registers on network_admin_menu
        self::assertNotFalse(has_action('network_admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        set_current_screen('front');
        remove_all_actions('network_admin_menu');
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
            new \WPPack\Component\EventDispatcher\EventDispatcher(),
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

    #[Test]
    public function registerSkipsEverythingOnNonMainSite(): void
    {
        $builder = new ContainerBuilder();
        $plugin = new ScimPlugin(__FILE__, $this->makeBlogContext(isMainSite: false));

        $plugin->register($builder);

        // Non-main-site: the plugin bails before registering any service,
        // including the admin settings page.
        self::assertFalse($builder->hasDefinition(ScimSettingsPage::class));
        self::assertFalse($builder->hasDefinition(ScimSettingsController::class));
    }

    #[Test]
    public function registerWiresScimServicesWhenTokenIsConfigured(): void
    {
        delete_option(ScimConfiguration::OPTION_NAME);
        update_option(ScimConfiguration::OPTION_NAME, ['bearerToken' => 'test-token-12345678']);

        try {
            $builder = new ContainerBuilder();
            $plugin = new ScimPlugin(__FILE__);

            $plugin->register($builder);

            // Token present: SCIM parameters get populated on the builder.
            self::assertTrue($builder->hasParameter('scim.max_results'));
            self::assertTrue($builder->hasParameter('scim.default_role'));
            self::assertTrue($builder->hasParameter('scim.base_url'));
        } finally {
            delete_option(ScimConfiguration::OPTION_NAME);
        }
    }

    #[Test]
    public function bootSkipsEverythingOnNonMainSite(): void
    {
        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $container = new Container($symfonyContainer);

        $plugin = new ScimPlugin(__FILE__, $this->makeBlogContext(isMainSite: false));

        // On a non-main site, boot() returns before looking up any
        // container service — the empty container is never queried.
        $plugin->boot($container);

        self::assertFalse(has_action('admin_menu'));
        self::assertFalse(has_action('rest_api_init'));
    }

    private function makeBlogContext(bool $isMainSite): BlogContextInterface
    {
        return new class ($isMainSite) implements BlogContextInterface {
            public function __construct(private readonly bool $isMainSite) {}

            public function getCurrentBlogId(): int
            {
                return $this->isMainSite ? 1 : 2;
            }

            public function isMultisite(): bool
            {
                return true;
            }

            public function getMainSiteId(): int
            {
                return 1;
            }

            public function isSwitched(): bool
            {
                return false;
            }

            public function isMainSite(): bool
            {
                return $this->isMainSite;
            }

            public function isSubdomainInstall(): bool
            {
                return false;
            }
        };
    }
}
