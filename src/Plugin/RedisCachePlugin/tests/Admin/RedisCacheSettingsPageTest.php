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

namespace WPPack\Plugin\RedisCachePlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsPage;

#[CoversClass(RedisCacheSettingsPage::class)]
final class RedisCacheSettingsPageTest extends TestCase
{
    use \WPPack\Component\Admin\Tests\Fixtures\BuildDirFixtureTrait;

    protected function setUp(): void
    {
        $this->createBuildDir('wppack-cache');
    }

    protected function tearDown(): void
    {
        $this->cleanupBuildDir();
        wp_dequeue_script('wppack-cache-settings');
        wp_dequeue_style('wppack-cache-settings');
    }

    #[Test]
    public function extendsAbstractAdminPage(): void
    {
        self::assertInstanceOf(AbstractAdminPage::class, new RedisCacheSettingsPage());
    }

    #[Test]
    public function invokeReturnsMountDiv(): void
    {
        $page = new RedisCacheSettingsPage();

        self::assertStringContainsString('wppack-cache-settings', $page());
        self::assertStringContainsString('<div id="wppack-cache-settings"></div>', $page());
    }

    #[Test]
    public function enqueueRegistersScriptAndStyle(): void
    {
        file_put_contents($this->buildDir . '/settings.asset.php', '<?php return ["dependencies" => [], "version" => "test1"];');

        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new RedisCacheSettingsPage();
        $page->setPluginFile($pluginFile);

        $method = new \ReflectionMethod($page, 'enqueue');
        $method->invoke($page);

        self::assertTrue(wp_script_is('wppack-cache-settings', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-cache-settings', 'enqueued'));
    }

    #[Test]
    public function enqueueDoesNothingWhenAssetFileMissing(): void
    {
        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new RedisCacheSettingsPage();
        $page->setPluginFile($pluginFile);

        $method = new \ReflectionMethod($page, 'enqueue');
        $method->invoke($page);

        self::assertFalse(wp_script_is('wppack-cache-settings', 'enqueued'));
    }
}
