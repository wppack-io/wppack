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

namespace WpPack\Plugin\OAuthLoginPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsPage;

#[CoversClass(OAuthLoginSettingsPage::class)]
final class OAuthLoginSettingsPageTest extends TestCase
{
    private string $buildDir;

    protected function setUp(): void
    {
        $this->buildDir = sys_get_temp_dir() . '/wppack-oauth-test-' . uniqid() . '/js/build';
        mkdir($this->buildDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $base = \dirname($this->buildDir, 2);
        if (is_dir($base)) {
            array_map('unlink', glob($this->buildDir . '/*') ?: []);
            @rmdir($this->buildDir);
            @rmdir(\dirname($this->buildDir));
            @rmdir($base);
        }
        wp_dequeue_script('wppack-oauth-login-settings');
        wp_dequeue_style('wppack-oauth-login-settings');
    }

    #[Test]
    public function extendsAbstractAdminPage(): void
    {
        $page = new OAuthLoginSettingsPage();

        self::assertInstanceOf(AbstractAdminPage::class, $page);
    }

    #[Test]
    public function invokeReturnsMountDiv(): void
    {
        $page = new OAuthLoginSettingsPage();

        $html = $page();

        self::assertStringContainsString('wppack-oauth-login-settings', $html);
        self::assertStringContainsString('<div id="wppack-oauth-login-settings"></div>', $html);
    }

    #[Test]
    public function enqueueRegistersScriptAndStyle(): void
    {
        file_put_contents($this->buildDir . '/settings.asset.php', '<?php return ["dependencies" => [], "version" => "test1"];');

        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new OAuthLoginSettingsPage();
        $page->setPluginFile($pluginFile);

        // Call enqueue via reflection (protected method)
        $method = new \ReflectionMethod($page, 'enqueue');
        $method->invoke($page);

        self::assertTrue(wp_script_is('wppack-oauth-login-settings', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-oauth-login-settings', 'enqueued'));
    }

    #[Test]
    public function enqueueDoesNothingWhenAssetFileMissing(): void
    {
        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new OAuthLoginSettingsPage();
        $page->setPluginFile($pluginFile);

        // Remove asset file
        @unlink($this->buildDir . '/settings.asset.php');

        $method = new \ReflectionMethod($page, 'enqueue');
        $method->invoke($page);

        self::assertFalse(wp_script_is('wppack-oauth-login-settings', 'enqueued'));
    }
}
