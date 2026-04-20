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

namespace WPPack\Plugin\MonitoringPlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Transient\TransientManager;
use WPPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;

#[CoversClass(MonitoringDashboardPage::class)]
final class MonitoringDashboardPageTest extends TestCase
{
    private string $buildDir;

    protected function setUp(): void
    {
        $this->buildDir = sys_get_temp_dir() . '/wppack-monitoring-dash-' . uniqid() . '/js/build';
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
        foreach (['wppack-monitoring-dashboard', 'wppack-monitoring-vendor'] as $handle) {
            wp_dequeue_script($handle);
            wp_dequeue_style($handle);
        }
    }

    private function collector(): MonitoringCollector
    {
        return new MonitoringCollector(new MonitoringRegistry(), [], new TransientManager());
    }

    #[Test]
    public function extendsAbstractAdminPage(): void
    {
        self::assertInstanceOf(AbstractAdminPage::class, new MonitoringDashboardPage());
    }

    #[Test]
    public function hasAdminPageAttributeWithExpectedSlug(): void
    {
        $ref = new \ReflectionClass(MonitoringDashboardPage::class);
        $attr = $ref->getAttributes(AsAdminPage::class)[0] ?? null;

        self::assertNotNull($attr);
        $instance = $attr->newInstance();
        self::assertSame('wppack-monitoring', $instance->slug);
        self::assertSame(90, $instance->position);
        self::assertSame('dashicons-chart-area', $instance->icon);
    }

    #[Test]
    public function invokeReturnsReactMountMarkup(): void
    {
        self::assertSame('<div id="wppack-monitoring-dashboard"></div>', (new MonitoringDashboardPage())());
    }

    #[Test]
    public function enqueueRegistersScriptAndStyles(): void
    {
        file_put_contents(
            $this->buildDir . '/dashboard.asset.php',
            '<?php return ["dependencies" => [], "version" => "v1"];',
        );

        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new MonitoringDashboardPage();
        $page->setPluginFile($pluginFile);
        $page->setCollector($this->collector());

        (new \ReflectionMethod($page, 'enqueue'))->invoke($page);

        self::assertTrue(wp_script_is('wppack-monitoring-dashboard', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-monitoring-vendor', 'enqueued'));
        self::assertTrue(wp_style_is('wppack-monitoring-dashboard', 'enqueued'));
    }

    #[Test]
    public function enqueueNoOpsWhenAssetFileMissing(): void
    {
        $pluginFile = \dirname($this->buildDir, 2) . '/plugin.php';
        touch($pluginFile);

        $page = new MonitoringDashboardPage();
        $page->setPluginFile($pluginFile);
        $page->setCollector($this->collector());

        (new \ReflectionMethod($page, 'enqueue'))->invoke($page);

        self::assertFalse(wp_script_is('wppack-monitoring-dashboard', 'enqueued'));
    }
}
