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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsPage;

#[CoversClass(PasskeyLoginSettingsPage::class)]
final class PasskeyLoginSettingsPageTest extends TestCase
{
    private string $buildDir;

    protected function setUp(): void
    {
        $this->buildDir = sys_get_temp_dir() . '/wppack-pkey-settings-' . uniqid() . '/js/build';
        mkdir($this->buildDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $base = \dirname($this->buildDir, 2);
        if (is_dir($base)) {
            foreach (glob($this->buildDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->buildDir);
            @rmdir(\dirname($this->buildDir));
            @rmdir($base);
        }
        foreach (['wppack-passkey-login-settings', 'wppack-passkey-login-vendor'] as $handle) {
            wp_dequeue_script($handle);
            wp_dequeue_style($handle);
        }
    }

    #[Test]
    public function extendsAbstractAdminPage(): void
    {
        self::assertInstanceOf(AbstractAdminPage::class, new PasskeyLoginSettingsPage());
    }

    #[Test]
    public function hasAdminPageAttributeWithExpectedSlug(): void
    {
        $ref = new \ReflectionClass(PasskeyLoginSettingsPage::class);
        $attr = $ref->getAttributes(AsAdminPage::class)[0] ?? null;

        self::assertNotNull($attr);
        $instance = $attr->newInstance();
        self::assertSame('wppack-passkey-login', $instance->slug);
        self::assertSame(203, $instance->position);
    }

    #[Test]
    public function invokeReturnsReactMountMarkup(): void
    {
        self::assertSame('<div id="wppack-passkey-login-settings"></div>', (new PasskeyLoginSettingsPage())());
    }

    #[Test]
    public function enqueueRegistersScriptsAndStyles(): void
    {
        file_put_contents(
            $this->buildDir . '/settings.asset.php',
            '<?php return ["dependencies" => [], "version" => "v1"];',
        );

        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new PasskeyLoginSettingsPage();
        $page->setPluginFile($pluginFile);

        (new \ReflectionMethod($page, 'enqueue'))->invoke($page);

        self::assertTrue(wp_script_is('wppack-passkey-login-settings', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-passkey-login-vendor', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-passkey-login-settings', 'enqueued'));
    }

    #[Test]
    public function enqueueIsNoOpWhenAssetFileMissing(): void
    {
        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new PasskeyLoginSettingsPage();
        $page->setPluginFile($pluginFile);

        (new \ReflectionMethod($page, 'enqueue'))->invoke($page);

        self::assertFalse(wp_script_is('wppack-passkey-login-settings', 'enqueued'));
    }
}
