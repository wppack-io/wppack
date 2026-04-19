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

namespace WPPack\Plugin\AmazonMailerPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\PluginInterface;
use WPPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WPPack\Component\Mailer\Mailer;
use WPPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsController;
use WPPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsPage;
use WPPack\Plugin\AmazonMailerPlugin\AmazonMailerPlugin;
use WPPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;

#[CoversClass(AmazonMailerPlugin::class)]
final class AmazonMailerPluginTest extends TestCase
{
    private AmazonMailerPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new AmazonMailerPlugin('/path/to/plugin.php');
    }

    #[Test]
    public function implementsPluginInterface(): void
    {
        self::assertInstanceOf(PluginInterface::class, $this->plugin);
    }

    #[Test]
    public function getCompilerPassesReturnsHookAndTransportPasses(): void
    {
        $passes = $this->plugin->getCompilerPasses();

        self::assertCount(3, $passes);

        $classes = array_map(static fn(object $pass): string => $pass::class, $passes);

        self::assertContains(RegisterMessageHandlersPass::class, $classes);
        self::assertContains(RegisterHookSubscribersPass::class, $classes);
        self::assertContains(RegisterTransportFactoriesPass::class, $classes);
    }

    #[Test]
    public function registerDelegatesToServiceProvider(): void
    {
        update_option('wppack_mailer', ['dsn' => 'native://default']);

        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        self::assertTrue($builder->hasDefinition(Mailer::class));
        self::assertTrue($builder->hasDefinition(\WPPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration::class));
        self::assertTrue($builder->hasDefinition(\WPPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler::class));
        self::assertTrue($builder->hasDefinition(\WPPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler::class));
    }

    #[Test]
    public function bootCallsMailerBoot(): void
    {
        Mailer::reset();

        $mailer = new Mailer('null://default');

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(Mailer::class, $mailer);
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(AmazonMailerSettingsPage::class, new AmazonMailerSettingsPage());
        $symfonyContainer->set(RestRegistry::class, new RestRegistry(new Request()));
        $symfonyContainer->set(AmazonMailerSettingsController::class, new AmazonMailerSettingsController());
        $container = new \WPPack\Component\DependencyInjection\Container($symfonyContainer);

        $this->plugin->boot($container);

        // Verify that the wp_mail filter was registered by boot()
        self::assertNotFalse(has_filter('wp_mail', [$mailer, 'onWpMail']));

        Mailer::reset();
    }

    #[Test]
    public function extendsAbstractPlugin(): void
    {
        self::assertInstanceOf(AbstractPlugin::class, $this->plugin);
    }

    #[Test]
    public function bootRegistersAdminPageAndRestWhenIsAdmin(): void
    {
        Mailer::reset();
        set_current_screen('dashboard');

        $settingsPage = new AmazonMailerSettingsPage();
        $settingsController = new AmazonMailerSettingsController();
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());
        $mailer = new Mailer('null://default');

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(AmazonMailerSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(AmazonMailerSettingsController::class, $settingsController);
        $symfonyContainer->set(Mailer::class, $mailer);

        $container = new Container($symfonyContainer);

        $this->plugin->boot($container);

        self::assertNotFalse(has_action('admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));
        self::assertNotFalse(has_filter('wp_mail', [$mailer, 'onWpMail']));

        set_current_screen('front');
        Mailer::reset();
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }

    #[Test]
    public function bootSkipsMailerWhenNotAvailable(): void
    {
        Mailer::reset();

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(AmazonMailerSettingsPage::class, new AmazonMailerSettingsPage());
        $symfonyContainer->set(RestRegistry::class, new RestRegistry(new Request()));
        $symfonyContainer->set(AmazonMailerSettingsController::class, new AmazonMailerSettingsController());
        $container = new Container($symfonyContainer);

        // boot() without Mailer in container should not throw
        $this->plugin->boot($container);

        self::assertTrue(true);

        Mailer::reset();
    }

    #[Test]
    public function registerSkipsServicesWithoutConfig(): void
    {
        delete_option(AmazonMailerConfiguration::OPTION_NAME);

        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        // Admin services should always be registered
        self::assertTrue($builder->hasDefinition(AmazonMailerSettingsPage::class));
        self::assertTrue($builder->hasDefinition(AmazonMailerSettingsController::class));
    }
}
