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

namespace WpPack\Plugin\SamlLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Option\OptionManager;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsController;
use WpPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsPage;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginForm;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginPlugin;

#[CoversClass(SamlLoginPlugin::class)]
final class SamlLoginPluginTest extends TestCase
{
    #[Test]
    public function onActivateFlushesRewriteRules(): void
    {
        $router = new RouteRegistry(optionManager: new OptionManager());

        $plugin = new SamlLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        // flush_rewrite_rules() internally calls flush() which updates the rewrite_rules option
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

        $plugin = new SamlLoginPlugin(__FILE__);

        $reflection = new \ReflectionProperty($plugin, 'router');
        $reflection->setValue($plugin, $router);

        $plugin->onDeactivate();

        self::assertFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function onActivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new SamlLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $plugin->onActivate();

        self::assertTrue(true);
    }

    #[Test]
    public function onDeactivateDoesNothingWhenRouterIsNull(): void
    {
        $plugin = new SamlLoginPlugin(__FILE__);

        // Should not throw when router is null (boot() not called)
        $plugin->onDeactivate();

        self::assertTrue(true);
    }

    #[Test]
    public function bootRegistersAdminPageAndRestEndpoint(): void
    {
        set_current_screen('dashboard');

        // Create a config without SAML configured (empty idpEntityId)
        $config = new SamlLoginConfiguration(
            idpEntityId: '',
            idpSsoUrl: '',
            idpX509Cert: '',
        );

        $settingsPage = new SamlLoginSettingsPage();
        $settingsController = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(SamlLoginConfiguration::class, $config);
        $symfonyContainer->set(SamlLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(SamlLoginSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new SamlLoginPlugin(__FILE__);
        $plugin->boot($container);

        // Admin hooks should be registered
        self::assertNotFalse(has_action('admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        set_current_screen('front');
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function bootSkipsSamlWhenNotConfigured(): void
    {
        $config = new SamlLoginConfiguration(
            idpEntityId: '',
            idpSsoUrl: '',
            idpX509Cert: '',
        );

        $settingsPage = new SamlLoginSettingsPage();
        $settingsController = new SamlLoginSettingsController(
            $config,
            new Sanitizer(),
            new RoleProvider(),
        );
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(SamlLoginConfiguration::class, $config);
        $symfonyContainer->set(SamlLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(SamlLoginSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new SamlLoginPlugin(__FILE__);
        $plugin->boot($container);

        // Router should not be set (SAML not configured)
        $reflection = new \ReflectionProperty($plugin, 'router');
        self::assertNull($reflection->getValue($plugin));

        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function getCompilerPassesReturnsAuthAndEventPasses(): void
    {
        $plugin = new SamlLoginPlugin(__FILE__);

        $passes = $plugin->getCompilerPasses();

        self::assertCount(2, $passes);

        $classes = array_map(static fn(object $p): string => $p::class, $passes);
        self::assertContains(\WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass::class, $classes);
        self::assertContains(\WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass::class, $classes);
    }
}
