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

namespace WpPack\Component\Kernel\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\PluginInterface;

final class AbstractPluginTest extends TestCase
{
    #[Test]
    public function implementsPluginInterface(): void
    {
        $plugin = $this->createPlugin(WP_PLUGIN_DIR . '/test-plugin/test-plugin.php');

        self::assertInstanceOf(PluginInterface::class, $plugin);
    }

    #[Test]
    public function getFileReturnsPluginFile(): void
    {
        $file = WP_PLUGIN_DIR . '/test-plugin/test-plugin.php';
        $plugin = $this->createPlugin($file);

        self::assertSame($file, $plugin->getFile());
    }

    #[Test]
    public function getPathReturnsDirectoryWithTrailingSlash(): void
    {
        $plugin = $this->createPlugin(WP_PLUGIN_DIR . '/test-plugin/test-plugin.php');

        $path = $plugin->getPath();

        self::assertStringEndsWith('test-plugin/', $path);
        self::assertStringEndsWith(\DIRECTORY_SEPARATOR, $path);
    }

    #[Test]
    public function getUrlReturnsDirectoryUrl(): void
    {
        $plugin = $this->createPlugin(WP_PLUGIN_DIR . '/test-plugin/test-plugin.php');

        $url = $plugin->getUrl();

        self::assertStringContainsString('test-plugin/', $url);
        self::assertStringEndsWith('/', $url);
    }

    #[Test]
    public function getBasenameReturnsPluginBasename(): void
    {
        $plugin = $this->createPlugin(WP_PLUGIN_DIR . '/test-plugin/test-plugin.php');

        self::assertSame('test-plugin/test-plugin.php', $plugin->getBasename());
    }

    #[Test]
    public function getCompilerPassesReturnsEmptyArray(): void
    {
        $plugin = $this->createPlugin(__FILE__);

        self::assertSame([], $plugin->getCompilerPasses());
    }

    #[Test]
    public function bootDoesNotThrow(): void
    {
        $plugin = $this->createPlugin(__FILE__);
        $container = new \WpPack\Component\DependencyInjection\Container(new \Symfony\Component\DependencyInjection\Container());

        $plugin->boot($container);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function onActivateDoesNotThrow(): void
    {
        $plugin = $this->createPlugin(__FILE__);

        $plugin->onActivate();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function onDeactivateDoesNotThrow(): void
    {
        $plugin = $this->createPlugin(__FILE__);

        $plugin->onDeactivate();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function isNetworkActivatedReturnsFalseOnNonMultisite(): void
    {
        $plugin = $this->createPlugin(WP_PLUGIN_DIR . '/test-plugin/test-plugin.php');

        self::assertFalse($plugin->isNetworkActivated());
    }

    private function createPlugin(string $pluginFile): AbstractPlugin
    {
        return new class ($pluginFile) extends AbstractPlugin {
            public function register(\WpPack\Component\DependencyInjection\ContainerBuilder $builder): void {}
        };
    }
}
