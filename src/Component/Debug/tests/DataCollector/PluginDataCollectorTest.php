<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\PluginDataCollector;

final class PluginDataCollectorTest extends TestCase
{
    private PluginDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new PluginDataCollector();
    }

    #[Test]
    public function getNameReturnsPlugin(): void
    {
        self::assertSame('plugin', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsPlugins(): void
    {
        self::assertSame('Plugins', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['plugins']);
        self::assertSame(0, $data['total_plugins']);
        self::assertSame([], $data['mu_plugins']);
        self::assertSame([], $data['dropins']);
        self::assertSame([], $data['load_order']);
        self::assertSame('', $data['slowest_plugin']);
        self::assertSame(0.0, $data['total_hook_time']);
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoPlugins(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenBelowTwenty(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_plugins' => 5]);

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowWhenBelowForty(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_plugins' => 25]);

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedWhenAtOrAboveForty(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_plugins' => 40]);

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function capturePluginLoadedRecordsLoadOrder(): void
    {
        $this->collector->capturePluginLoaded('akismet/akismet.php');
        $this->collector->capturePluginLoaded('woocommerce/woocommerce.php');

        $reflection = new \ReflectionProperty($this->collector, 'loadOrder');
        $loadOrder = $reflection->getValue($this->collector);

        self::assertSame(['akismet/akismet.php', 'woocommerce/woocommerce.php'], $loadOrder);
    }

    #[Test]
    public function capturePluginLoadedRecordsTimings(): void
    {
        $this->collector->capturePluginLoaded('first/first.php');
        usleep(1000); // 1ms
        $this->collector->capturePluginLoaded('second/second.php');

        $reflection = new \ReflectionProperty($this->collector, 'pluginLoadTimes');
        $loadTimes = $reflection->getValue($this->collector);

        // First plugin's load time should be recorded when second starts
        self::assertArrayHasKey('first/first.php', $loadTimes);
        self::assertGreaterThan(0.0, $loadTimes['first/first.php']);
    }

    #[Test]
    public function capturePluginsLoadedFinalizesLastPlugin(): void
    {
        $this->collector->capturePluginLoaded('only/only.php');
        usleep(1000);
        $this->collector->capturePluginsLoaded();

        $reflection = new \ReflectionProperty($this->collector, 'pluginLoadTimes');
        $loadTimes = $reflection->getValue($this->collector);

        self::assertArrayHasKey('only/only.php', $loadTimes);
        self::assertGreaterThan(0.0, $loadTimes['only/only.php']);
    }

    #[Test]
    public function captureMuPluginLoadedRecordsLoadOrder(): void
    {
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/loader.php');
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/custom.php');

        $reflection = new \ReflectionProperty($this->collector, 'loadOrder');
        $loadOrder = $reflection->getValue($this->collector);

        self::assertSame(['loader.php', 'custom.php'], $loadOrder);
    }

    #[Test]
    public function captureMuPluginLoadedRecordsTimings(): void
    {
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/first.php');
        usleep(1000); // 1ms
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/second.php');

        $reflection = new \ReflectionProperty($this->collector, 'pluginLoadTimes');
        $loadTimes = $reflection->getValue($this->collector);

        // First MU plugin's load time should be recorded when second starts
        self::assertArrayHasKey('first.php', $loadTimes);
        self::assertGreaterThan(0.0, $loadTimes['first.php']);
    }

    #[Test]
    public function captureMuPluginsLoadedFinalizesLastMuPlugin(): void
    {
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/only.php');
        usleep(1000);
        $this->collector->captureMuPluginsLoaded();

        $reflection = new \ReflectionProperty($this->collector, 'pluginLoadTimes');
        $loadTimes = $reflection->getValue($this->collector);

        self::assertArrayHasKey('only.php', $loadTimes);
        self::assertGreaterThan(0.0, $loadTimes['only.php']);
    }

    #[Test]
    public function captureMuThenRegularPluginsRecordsSeparately(): void
    {
        // Simulate MU plugin loading
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/loader.php');
        usleep(1000);
        $this->collector->captureMuPluginsLoaded();

        // Simulate regular plugin loading
        $this->collector->capturePluginLoaded('akismet/akismet.php');
        usleep(1000);
        $this->collector->capturePluginsLoaded();

        $reflection = new \ReflectionProperty($this->collector, 'pluginLoadTimes');
        $loadTimes = $reflection->getValue($this->collector);

        self::assertArrayHasKey('loader.php', $loadTimes);
        self::assertArrayHasKey('akismet/akismet.php', $loadTimes);
        self::assertGreaterThan(0.0, $loadTimes['loader.php']);
        self::assertGreaterThan(0.0, $loadTimes['akismet/akismet.php']);
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->capturePluginLoaded('test/test.php');
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();
        self::assertEmpty($this->collector->getData());

        // After reset, loading state should also be cleared
        $loadOrderRef = new \ReflectionProperty($this->collector, 'loadOrder');
        self::assertSame([], $loadOrderRef->getValue($this->collector));
    }
}
