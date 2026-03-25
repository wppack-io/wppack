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
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\PluginInterface;
use WpPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Plugin\S3StoragePlugin\S3StoragePlugin;

#[CoversClass(S3StoragePlugin::class)]
final class S3StoragePluginTest extends TestCase
{
    private S3StoragePlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new S3StoragePlugin('/path/to/plugin.php');
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
    public function registerDelegatesToServiceProvider(): void
    {
        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        // Verify key services are registered (proves delegation to ServiceProvider)
        self::assertTrue($builder->hasDefinition(\AsyncAws\S3\S3Client::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\S3StoragePlugin\Attachment\RegisterAttachmentController::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber::class));
    }

    #[Test]
    public function extendsAbstractPlugin(): void
    {
        self::assertInstanceOf(AbstractPlugin::class, $this->plugin);
    }
}
