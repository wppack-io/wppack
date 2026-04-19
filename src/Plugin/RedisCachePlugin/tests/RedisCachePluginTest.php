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

namespace WPPack\Plugin\RedisCachePlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\ManagesDropin;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsController;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsPage;
use WPPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;
use WPPack\Plugin\RedisCachePlugin\RedisCachePlugin;

#[CoversClass(RedisCachePlugin::class)]
#[CoversClass(ManagesDropin::class)]
final class RedisCachePluginTest extends TestCase
{
    private string $dropinPath;

    protected function setUp(): void
    {
        $this->dropinPath = WP_CONTENT_DIR . '/object-cache.php';

        // Ensure wp_object_cache is initialized for wp_cache_flush()
        if (!isset($GLOBALS['wp_object_cache'])) {
            wp_cache_init();
        }

        // Ensure clean state
        if (file_exists($this->dropinPath) || is_link($this->dropinPath)) {
            unlink($this->dropinPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dropinPath) || is_link($this->dropinPath)) {
            unlink($this->dropinPath);
        }
    }

    #[Test]
    public function getCompilerPassesReturnsRegisterHookSubscribersPass(): void
    {
        $plugin = new RedisCachePlugin(__FILE__);
        $passes = $plugin->getCompilerPasses();

        self::assertCount(1, $passes);
        self::assertInstanceOf(RegisterHookSubscribersPass::class, $passes[0]);
    }

    #[Test]
    public function onActivateInstallsDropin(): void
    {
        $plugin = new RedisCachePlugin(__FILE__);

        $plugin->onActivate();

        self::assertTrue(
            file_exists($this->dropinPath) || is_link($this->dropinPath),
            'Drop-in file should be installed after activation.',
        );
    }

    #[Test]
    public function onActivateDoesNotOverwriteExistingDropin(): void
    {
        // Create an existing drop-in with different content
        file_put_contents($this->dropinPath, '<?php // Existing drop-in');

        $plugin = new RedisCachePlugin(__FILE__);
        $plugin->onActivate();

        self::assertSame(
            '<?php // Existing drop-in',
            file_get_contents($this->dropinPath),
            'Existing drop-in should not be overwritten.',
        );
    }

    #[Test]
    public function onDeactivateRemovesDropin(): void
    {
        $plugin = new RedisCachePlugin(__FILE__);

        // First activate to install the drop-in
        $plugin->onActivate();
        self::assertTrue(
            file_exists($this->dropinPath) || is_link($this->dropinPath),
            'Drop-in should exist after activation.',
        );

        $plugin->onDeactivate();

        self::assertFalse(
            file_exists($this->dropinPath) || is_link($this->dropinPath),
            'Drop-in file should be removed after deactivation.',
        );
    }

    #[Test]
    public function onDeactivateDoesNotRemoveForeignDropin(): void
    {
        // Create a drop-in that does not contain the WPPack signature
        file_put_contents($this->dropinPath, '<?php // Foreign object cache drop-in');

        $plugin = new RedisCachePlugin(__FILE__);
        $plugin->onDeactivate();

        self::assertTrue(
            file_exists($this->dropinPath),
            'Foreign drop-in should not be removed.',
        );
    }

    #[Test]
    public function onDeactivateHandlesMissingDropinGracefully(): void
    {
        $plugin = new RedisCachePlugin(__FILE__);

        // Should not throw when drop-in does not exist
        $plugin->onDeactivate();

        self::assertFalse(file_exists($this->dropinPath));
    }

    #[Test]
    public function bootRegistersAdminPageAndRestController(): void
    {
        set_current_screen('dashboard');

        $settingsPage = new RedisCacheSettingsPage();
        $settingsController = new RedisCacheSettingsController();
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(RedisCacheSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(RedisCacheSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $plugin = new RedisCachePlugin(__FILE__);
        $plugin->boot($container);

        // Verify admin_menu action was registered
        self::assertNotFalse(has_action('admin_menu'));
        // Verify rest_api_init was registered
        self::assertNotFalse(has_action('rest_api_init'));

        set_current_screen('front');
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function registerSkipsServiceRegistrationWithoutConfig(): void
    {
        delete_option(RedisCacheConfiguration::OPTION_NAME);

        $plugin = new RedisCachePlugin(__FILE__);
        $builder = new ContainerBuilder();

        $plugin->register($builder);

        // Admin services are always registered
        self::assertTrue($builder->hasDefinition(RedisCacheSettingsPage::class));
        self::assertTrue($builder->hasDefinition(RedisCacheSettingsController::class));
    }
}
