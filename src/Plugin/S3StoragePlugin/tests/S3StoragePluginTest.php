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

namespace WpPack\Plugin\S3StoragePlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\PluginInterface;
use WpPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsController;
use WpPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsPage;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\S3StoragePlugin;

#[CoversClass(S3StoragePlugin::class)]
final class S3StoragePluginTest extends TestCase
{
    private S3StoragePlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new S3StoragePlugin('/path/to/plugin.php');
    }

    protected function tearDown(): void
    {
        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

    #[Test]
    public function implementsPluginInterface(): void
    {
        self::assertInstanceOf(PluginInterface::class, $this->plugin);
    }

    #[Test]
    public function getCompilerPassesReturnsHookAndRestPasses(): void
    {
        $passes = $this->plugin->getCompilerPasses();

        self::assertCount(3, $passes);

        $classes = array_map(static fn(object $pass): string => $pass::class, $passes);

        self::assertContains(RegisterMessageHandlersPass::class, $classes);
        self::assertContains(RegisterHookSubscribersPass::class, $classes);
        self::assertContains(RegisterRestControllersPass::class, $classes);
    }

    #[Test]
    public function registerAlwaysRegistersAdminServices(): void
    {
        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        // Admin services are always registered regardless of configuration
        self::assertTrue($builder->hasDefinition(AdminPageRegistry::class));
        self::assertTrue($builder->hasDefinition(S3StorageSettingsPage::class));
        self::assertTrue($builder->hasDefinition(S3StorageSettingsController::class));
    }

    #[Test]
    public function registerDelegatesToServiceProviderWhenConfigured(): void
    {
        // Set up wp_options configuration so hasConfiguration() returns true
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'test-bucket', 'region' => 'us-east-1'],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]);

        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        // Verify key services are registered (proves delegation to ServiceProvider)
        self::assertTrue($builder->hasDefinition(\AsyncAws\S3\S3Client::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\S3StoragePlugin\Attachment\RegisterAttachmentController::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber::class));
    }

    #[Test]
    public function registerSkipsStorageServicesWithoutConfiguration(): void
    {
        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        // Without configuration, storage services should not be registered
        self::assertFalse($builder->hasDefinition(\AsyncAws\S3\S3Client::class));
    }

    #[Test]
    public function extendsAbstractPlugin(): void
    {
        self::assertInstanceOf(AbstractPlugin::class, $this->plugin);
    }

    #[Test]
    public function bootRegistersAdminPageAndRestController(): void
    {
        set_current_screen('dashboard');

        $settingsPage = new S3StorageSettingsPage();
        $settingsController = new S3StorageSettingsController();
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(S3StorageSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(S3StorageSettingsController::class, $settingsController);

        $container = new Container($symfonyContainer);

        $this->plugin->boot($container);

        self::assertNotFalse(has_action('admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        set_current_screen('front');
        remove_all_actions('admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }
}
