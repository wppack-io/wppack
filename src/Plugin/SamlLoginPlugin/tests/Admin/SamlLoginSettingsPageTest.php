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

namespace WpPack\Plugin\SamlLoginPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Plugin\SamlLoginPlugin\Admin\SamlLoginSettingsPage;

#[CoversClass(SamlLoginSettingsPage::class)]
final class SamlLoginSettingsPageTest extends TestCase
{
    private string $buildDir;

    protected function setUp(): void
    {
        $this->buildDir = sys_get_temp_dir() . '/wppack-saml-test-' . uniqid() . '/js/build';
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
        wp_dequeue_script('wppack-saml-login-settings');
        wp_dequeue_style('wppack-saml-login-settings');
    }

    #[Test]
    public function extendsAbstractAdminPage(): void
    {
        self::assertInstanceOf(AbstractAdminPage::class, new SamlLoginSettingsPage());
    }

    #[Test]
    public function invokeReturnsMountDiv(): void
    {
        $page = new SamlLoginSettingsPage();

        self::assertStringContainsString('wppack-saml-login-settings', $page());
        self::assertStringContainsString('<div id="wppack-saml-login-settings"></div>', $page());
    }

    #[Test]
    public function enqueueRegistersScriptAndStyle(): void
    {
        file_put_contents($this->buildDir . '/settings.asset.php', '<?php return ["dependencies" => [], "version" => "test1"];');

        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new SamlLoginSettingsPage();
        $page->setPluginFile($pluginFile);

        $method = new \ReflectionMethod($page, 'enqueue');
        $method->invoke($page);

        self::assertTrue(wp_script_is('wppack-saml-login-settings', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-saml-login-settings', 'enqueued'));
    }

    #[Test]
    public function enqueueDoesNothingWhenAssetFileMissing(): void
    {
        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new SamlLoginSettingsPage();
        $page->setPluginFile($pluginFile);

        $method = new \ReflectionMethod($page, 'enqueue');
        $method->invoke($page);

        self::assertFalse(wp_script_is('wppack-saml-login-settings', 'enqueued'));
    }
}
