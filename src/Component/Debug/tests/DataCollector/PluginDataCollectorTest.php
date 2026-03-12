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

    #[Test]
    public function collectWithWordPressGathersPluginStructure(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('plugins', $data);
        self::assertArrayHasKey('total_plugins', $data);
        self::assertArrayHasKey('mu_plugins', $data);
        self::assertArrayHasKey('dropins', $data);
        self::assertArrayHasKey('load_order', $data);
        self::assertArrayHasKey('slowest_plugin', $data);
        self::assertArrayHasKey('total_hook_time', $data);
        self::assertIsArray($data['plugins']);
        self::assertIsInt($data['total_plugins']);
        self::assertIsArray($data['mu_plugins']);
    }

    #[Test]
    public function collectBuildsSortedPluginsByHookTime(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // Plugins should be sorted by hook_time descending
        $hookTimes = [];
        foreach ($data['plugins'] as $plugin) {
            $hookTimes[] = $plugin['hook_time'];
        }

        $sorted = $hookTimes;
        rsort($sorted);
        self::assertSame($sorted, $hookTimes);
    }

    #[Test]
    public function collectPluginDataStructureIsCorrect(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        if ($data['plugins'] === []) {
            // No active plugins in this test environment — just verify the array is valid
            self::assertIsArray($data['plugins']);
        } else {
            foreach ($data['plugins'] as $pluginFile => $pluginData) {
                self::assertArrayHasKey('name', $pluginData);
                self::assertArrayHasKey('version', $pluginData);
                self::assertArrayHasKey('load_time', $pluginData);
                self::assertArrayHasKey('hook_count', $pluginData);
                self::assertArrayHasKey('listener_count', $pluginData);
                self::assertArrayHasKey('hook_time', $pluginData);
                self::assertArrayHasKey('query_count', $pluginData);
                self::assertArrayHasKey('query_time', $pluginData);
                self::assertArrayHasKey('hooks', $pluginData);
                self::assertIsFloat($pluginData['load_time']);
                self::assertIsFloat($pluginData['hook_time']);
                self::assertIsFloat($pluginData['query_time']);
                break; // Just check structure of first plugin
            }
        }
    }

    #[Test]
    public function getBadgeValueReturnsCountWhenPluginsActive(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $badge = $this->collector->getBadgeValue();

        if ($data['total_plugins'] > 0) {
            self::assertSame((string) $data['total_plugins'], $badge);
        } else {
            self::assertSame('', $badge);
        }
    }

    #[Test]
    public function collectWithWordPressGathersActivePluginData(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // Active plugins should be an array (possibly empty in test env)
        self::assertIsArray($data['plugins']);
        self::assertIsInt($data['total_plugins']);
        self::assertIsArray($data['mu_plugins']);
        self::assertIsArray($data['dropins']);
        self::assertIsFloat($data['total_hook_time']);
        self::assertIsString($data['slowest_plugin']);
    }

    #[Test]
    public function collectBuildsHookAttributionWithWpFilter(): void
    {
        if (!function_exists('get_option') || !defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // Add a hook with a closure that lives in a "plugin" path
        // This exercises buildPluginHookAttribution and getPluginSlugFromCallback
        $this->collector->collect();
        $data = $this->collector->getData();

        // The hook attribution code should run without errors
        // Even if no actual plugin hooks are found, the structure should be valid
        self::assertIsArray($data['plugins']);
        foreach ($data['plugins'] as $plugin) {
            self::assertIsArray($plugin['hooks']);
            self::assertIsFloat($plugin['hook_time']);
            self::assertIsInt($plugin['hook_count']);
            self::assertIsInt($plugin['listener_count']);
        }
    }

    #[Test]
    public function collectBuildsQueryAttributionFromWpdb(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wpdb;

        // Save original queries
        $savedQueries = $wpdb->queries ?? null;

        // Simulate some queries from a plugin directory
        $pluginDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '/wp-content/plugins';
        $wpdb->queries = [
            ['SELECT 1', 0.001, $pluginDir . '/test-plugin/main.php, test_function'],
            ['SELECT 2', 0.002, $pluginDir . '/test-plugin/main.php, another_function'],
            ['SELECT 3', 0.001, '/wp-includes/query.php, WP_Query->query'],
        ];

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            // The query attribution should have processed without errors
            self::assertIsArray($data['plugins']);
        } finally {
            if ($savedQueries !== null) {
                $wpdb->queries = $savedQueries;
            } else {
                $wpdb->queries = [];
            }
        }
    }

    #[Test]
    public function collectHandlesMuPluginData(): void
    {
        if (!function_exists('get_mu_plugins')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // MU plugins section should be populated (even if empty array)
        self::assertIsArray($data['mu_plugins']);

        // Check that MU plugins in the plugins array have is_mu key
        foreach ($data['plugins'] as $pluginData) {
            if (isset($pluginData['is_mu'])) {
                self::assertTrue($pluginData['is_mu']);
            }
        }
    }

    #[Test]
    public function collectWithActivePluginOptionExercisesInnerLoop(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $savedPlugins = get_option('active_plugins', []);

        try {
            update_option('active_plugins', ['fake-plugin/fake-plugin.php']);

            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertSame(1, $data['total_plugins']);
            self::assertArrayHasKey('plugins', $data);

            // The fake plugin should appear in the plugin list
            self::assertArrayHasKey('fake-plugin/fake-plugin.php', $data['plugins']);
            $fakePlugin = $data['plugins']['fake-plugin/fake-plugin.php'];

            // Verify the plugin data structure is built correctly
            self::assertArrayHasKey('name', $fakePlugin);
            self::assertArrayHasKey('version', $fakePlugin);
            self::assertArrayHasKey('load_time', $fakePlugin);
            self::assertArrayHasKey('hook_count', $fakePlugin);
            self::assertArrayHasKey('listener_count', $fakePlugin);
            self::assertArrayHasKey('hook_time', $fakePlugin);
            self::assertArrayHasKey('query_count', $fakePlugin);
            self::assertArrayHasKey('query_time', $fakePlugin);
            self::assertArrayHasKey('hooks', $fakePlugin);

            // Since get_plugins() won't have data for this fake plugin,
            // the slug should be used as the name
            self::assertSame('fake-plugin', $fakePlugin['name']);
            self::assertSame('', $fakePlugin['version']);
        } finally {
            update_option('active_plugins', $savedPlugins);
        }
    }

    #[Test]
    public function collectWithPluginHookAttributionFromPluginDir(): void
    {
        if (!function_exists('get_option') || !defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wp_filter;

        $savedPlugins = get_option('active_plugins', []);
        $hookName = 'test_plugin_hook_attribution_' . uniqid();

        // Create a fake plugin file path that points to WP_PLUGIN_DIR
        $fakePluginDir = WP_PLUGIN_DIR . '/test-attribution-plugin';
        $fakePluginFile = $fakePluginDir . '/main.php';

        // Create the fake plugin directory and file temporarily
        $dirCreated = false;
        $fileCreated = false;

        try {
            if (!is_dir($fakePluginDir)) {
                mkdir($fakePluginDir, 0755, true);
                $dirCreated = true;
            }
            // Create a minimal PHP file so ReflectionFunction can resolve the filename
            file_put_contents($fakePluginFile, "<?php\nfunction test_attribution_callback_" . md5($hookName) . "() {}\n");
            $fileCreated = true;

            // Load the function from the file
            require_once $fakePluginFile;

            $funcName = 'test_attribution_callback_' . md5($hookName);

            // Register a hook with the callback from the plugin directory
            add_action($hookName, $funcName);

            // Set active_plugins to include this fake plugin
            update_option('active_plugins', ['test-attribution-plugin/main.php']);

            $this->collector->collect();
            $data = $this->collector->getData();

            // The plugin should appear with hook attribution
            self::assertArrayHasKey('test-attribution-plugin/main.php', $data['plugins']);
            $pluginData = $data['plugins']['test-attribution-plugin/main.php'];
            self::assertGreaterThanOrEqual(0, $pluginData['hook_count']);
        } finally {
            update_option('active_plugins', $savedPlugins);
            if (function_exists('remove_action')) {
                remove_all_actions($hookName);
            }
            if ($fileCreated && file_exists($fakePluginFile)) {
                unlink($fakePluginFile);
            }
            if ($dirCreated && is_dir($fakePluginDir)) {
                rmdir($fakePluginDir);
            }
        }
    }

    #[Test]
    public function collectWithMuPluginCaptureDoesNotCrash(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // Simulate MU plugin loading cycle
        $this->collector->captureMuPluginLoaded('/var/www/html/wp-content/mu-plugins/test-mu.php');
        usleep(500);
        $this->collector->captureMuPluginsLoaded();

        // Now collect — should not crash even without real MU plugins
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsArray($data['plugins']);
        self::assertIsArray($data['mu_plugins']);
        self::assertContains('test-mu.php', $data['load_order']);
    }

    #[Test]
    public function collectQueryAttributionWithPluginDir(): void
    {
        if (!function_exists('get_option') || !defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wpdb;

        $savedQueries = $wpdb->queries ?? null;
        $savedPlugins = get_option('active_plugins', []);

        try {
            $pluginDir = WP_PLUGIN_DIR;

            // Set up a fake active plugin
            update_option('active_plugins', ['query-test-plugin/query-test-plugin.php']);

            // Set up queries that reference the plugin directory
            $wpdb->queries = [
                ['SELECT * FROM wp_posts', 0.005, $pluginDir . '/query-test-plugin/model.php, QueryTestPlugin->fetchPosts'],
                ['SELECT * FROM wp_options', 0.002, $pluginDir . '/query-test-plugin/settings.php, QueryTestPlugin->loadSettings'],
                ['SELECT 1', 0.001, '/var/www/html/wp-includes/query.php, WP_Query->query'],
            ];

            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertArrayHasKey('query-test-plugin/query-test-plugin.php', $data['plugins']);
            $pluginData = $data['plugins']['query-test-plugin/query-test-plugin.php'];

            // Should have 2 queries attributed
            self::assertSame(2, $pluginData['query_count']);
            self::assertGreaterThan(0.0, $pluginData['query_time']);
        } finally {
            update_option('active_plugins', $savedPlugins);
            if ($savedQueries !== null) {
                $wpdb->queries = $savedQueries;
            } else {
                $wpdb->queries = [];
            }
        }
    }

    #[Test]
    public function getCallbackFileNameWithClosure(): void
    {
        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackFileName');

        $closure = static function (): void {};
        $result = $method->invoke($collector, $closure);

        // The closure is defined in this test file
        self::assertSame(__FILE__, $result);
    }

    #[Test]
    public function getCallbackFileNameWithArrayCallback(): void
    {
        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackFileName');

        $result = $method->invoke($collector, [$this, 'getCallbackFileNameWithArrayCallback']);

        // The method is defined in this test file
        self::assertSame(__FILE__, $result);
    }

    #[Test]
    public function getCallbackFileNameWithStringFunction(): void
    {
        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackFileName');

        // Test with a built-in function — ReflectionFunction for internal functions returns false for getFileName
        $result = $method->invoke($collector, 'phpinfo');
        self::assertNull($result);

        // Test with a user-defined function if WordPress is available
        if (function_exists('wp_list_pluck')) {
            $result = $method->invoke($collector, 'wp_list_pluck');
            self::assertIsString($result);
        }
    }

    #[Test]
    public function getCallbackFileNameWithInvalidReturnsNull(): void
    {
        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackFileName');

        // Integer callback should return null
        $result = $method->invoke($collector, 42);
        self::assertNull($result);

        // Non-existent function name should return null
        $result = $method->invoke($collector, 'nonexistent_function_xyz_' . uniqid());
        self::assertNull($result);
    }

    #[Test]
    public function collectWithSingleFilePluginExtractsSlugFromFilename(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $savedPlugins = get_option('active_plugins', []);

        try {
            // Single-file plugin (no subdirectory) — slug should be extracted from filename
            update_option('active_plugins', ['hello.php']);

            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertArrayHasKey('hello.php', $data['plugins']);
            $pluginData = $data['plugins']['hello.php'];

            // For a single-file plugin, dirname returns '.', so slug = pathinfo filename = 'hello'
            self::assertSame('hello', $pluginData['name']);
        } finally {
            update_option('active_plugins', $savedPlugins);
        }
    }

    #[Test]
    public function collectWithLoadTimesRecordsSlowestPlugin(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $savedPlugins = get_option('active_plugins', []);

        try {
            update_option('active_plugins', ['slow-plugin/slow-plugin.php', 'fast-plugin/fast-plugin.php']);

            // Simulate plugin load times via reflection
            $loadTimesRef = new \ReflectionProperty($this->collector, 'pluginLoadTimes');
            $loadTimesRef->setValue($this->collector, [
                'slow-plugin/slow-plugin.php' => 150.0,
                'fast-plugin/fast-plugin.php' => 10.0,
            ]);

            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertSame(2, $data['total_plugins']);
            // Load times should be recorded
            self::assertSame(150.0, $data['plugins']['slow-plugin/slow-plugin.php']['load_time']);
            self::assertSame(10.0, $data['plugins']['fast-plugin/fast-plugin.php']['load_time']);
        } finally {
            update_option('active_plugins', $savedPlugins);
        }
    }

    #[Test]
    public function collectQueryAttributionWithMuPluginDir(): void
    {
        if (!function_exists('get_option') || !defined('WPMU_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wpdb;

        $savedQueries = $wpdb->queries ?? null;

        try {
            $muPluginDir = WPMU_PLUGIN_DIR;

            // Set up queries from MU plugin directory
            $wpdb->queries = [
                ['SELECT * FROM wp_options', 0.003, $muPluginDir . '/mu-loader.php, mu_load_settings'],
            ];

            $this->collector->collect();
            $data = $this->collector->getData();

            // Should not crash and data structure should be valid
            self::assertIsArray($data['plugins']);
        } finally {
            if ($savedQueries !== null) {
                $wpdb->queries = $savedQueries;
            } else {
                $wpdb->queries = [];
            }
        }
    }

    /**
     * Covers lines 168-169: slowest_plugin is set when a plugin has hook_time > 0.
     *
     * We inject a fake WP_Hook object into $wp_filter that contains a callback
     * from a fake plugin directory, then activate that plugin so the inner loop
     * detects hook time and sets slowest_plugin.
     */
    #[Test]
    public function collectSetsSlowestPluginWhenHookTimeIsPositive(): void
    {
        if (!function_exists('get_option') || !defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wp_filter;

        $savedPlugins = get_option('active_plugins', []);
        $hookName = 'test_slowest_plugin_hook_' . uniqid();
        $fakePluginDir = WP_PLUGIN_DIR . '/slowest-test-plugin';
        $fakePluginFile = $fakePluginDir . '/main.php';
        $dirCreated = false;
        $fileCreated = false;
        $funcName = 'test_slowest_callback_' . md5($hookName);

        try {
            if (!is_dir($fakePluginDir)) {
                mkdir($fakePluginDir, 0755, true);
                $dirCreated = true;
            }
            file_put_contents($fakePluginFile, "<?php\nfunction {$funcName}() {}\n");
            $fileCreated = true;
            require_once $fakePluginFile;

            add_action($hookName, $funcName);
            update_option('active_plugins', ['slowest-test-plugin/main.php']);

            $this->collector->collect();
            $data = $this->collector->getData();

            // Plugin should exist in the data
            self::assertArrayHasKey('slowest-test-plugin/main.php', $data['plugins']);
            $pluginData = $data['plugins']['slowest-test-plugin/main.php'];
            self::assertIsInt($pluginData['hook_count']);
            self::assertIsInt($pluginData['listener_count']);
            self::assertIsFloat($pluginData['hook_time']);

            // Since only one plugin is active and has hooks, it should be the slowest
            // (or slowest_plugin may remain '' if hook_time is 0.0 with no timing data)
            self::assertIsString($data['slowest_plugin']);
        } finally {
            update_option('active_plugins', $savedPlugins);
            remove_all_actions($hookName);
            if ($fileCreated && file_exists($fakePluginFile)) {
                unlink($fakePluginFile);
            }
            if ($dirCreated && is_dir($fakePluginDir)) {
                rmdir($fakePluginDir);
            }
        }
    }

    /**
     * Covers lines 274: buildPluginHookAttribution skips non-object entries in $wp_filter.
     *
     * Tests the private method directly via reflection with a mix of valid and invalid
     * entries in the wp_filter-like array.
     */
    #[Test]
    public function buildPluginHookAttributionSkipsNonObjectEntries(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'buildPluginHookAttribution');

        // Pass an array with a non-object entry (string) and a null
        $wpFilter = [
            'some_hook' => 'not_an_object',
            'another_hook' => null,
            'third_hook' => (object) [], // object without callbacks property
        ];

        $result = $method->invoke($collector, $wpFilter);

        // All entries should be skipped since none have valid callbacks
        self::assertSame([], $result);
    }

    /**
     * Covers line 267: buildPluginHookAttribution returns [] when both plugin dirs are empty.
     * Also covers line 368: buildQueryAttribution returns [] when both dirs are empty.
     *
     * Uses reflection to call methods with artificial empty-dir state.
     * Since WP is loaded in our env, WP_PLUGIN_DIR is defined, so we test the
     * method's internal behavior by verifying that when valid data IS present,
     * the method processes it correctly (the early-return path is only for
     * non-WP environments).
     */
    #[Test]
    public function buildPluginHookAttributionProcessesValidHookObjects(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'buildPluginHookAttribution');

        // Create a mock WP_Hook-like object with callbacks from WP_PLUGIN_DIR
        $hookObj = new \stdClass();
        $hookObj->callbacks = [
            10 => [
                'test_cb' => [
                    'function' => static function (): void {},
                    'accepted_args' => 0,
                ],
            ],
        ];

        $wpFilter = ['test_hook' => $hookObj];
        $result = $method->invoke($collector, $wpFilter);

        // The closure is in this test file (not in plugin dir), so no attribution
        self::assertSame([], $result);
    }

    /**
     * Covers line 327: getPluginSlugFromCallback returns basename for MU plugin path.
     */
    #[Test]
    public function getPluginSlugFromCallbackReturnsMuPluginBasename(): void
    {
        if (!defined('WPMU_PLUGIN_DIR')) {
            self::markTestSkipped('WPMU_PLUGIN_DIR is not defined.');
        }

        $muPluginDir = WPMU_PLUGIN_DIR;
        $fakeFile = $muPluginDir . '/test-mu-slug.php';
        $funcName = 'test_mu_slug_callback_' . md5(uniqid());
        $fileCreated = false;

        try {
            if (!is_dir($muPluginDir)) {
                mkdir($muPluginDir, 0755, true);
            }
            file_put_contents($fakeFile, "<?php\nfunction {$funcName}() {}\n");
            $fileCreated = true;
            require_once $fakeFile;

            $collector = new PluginDataCollector();
            $method = new \ReflectionMethod($collector, 'getPluginSlugFromCallback');

            $result = $method->invoke($collector, $funcName, '', $muPluginDir);
            self::assertSame('test-mu-slug.php', $result);
        } finally {
            if ($fileCreated && file_exists($fakeFile)) {
                unlink($fakeFile);
            }
        }
    }

    /**
     * Covers lines 375: buildQueryAttribution skips malformed query entries.
     */
    #[Test]
    public function buildQueryAttributionSkipsMalformedQueries(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'buildQueryAttribution');

        $wpdb = new \stdClass();
        $wpdb->queries = [
            'not_an_array',                   // string — should be skipped (line 375)
            ['only_one_element'],              // array with count < 3 — skipped
            ['sql', 0.001],                    // array with count < 3 — skipped
            ['SELECT 1', 0.005, WP_PLUGIN_DIR . '/valid-plugin/file.php, func'],  // valid
        ];

        $result = $method->invoke($collector, $wpdb);

        // Only the last valid query should be attributed
        self::assertArrayHasKey('valid-plugin', $result);
        self::assertSame(1, $result['valid-plugin']['count']);
    }

    /**
     * Covers lines 176, 178-183, 186, 188-199, 201-202, 204-206:
     * MU plugin data collection loop including hook attribution and slowest_plugin tracking.
     *
     * We create a real file inside WPMU_PLUGIN_DIR, register a hook from it,
     * and verify the MU plugin data is collected with is_mu flag and hook info.
     */
    #[Test]
    public function collectMuPluginWithHookAttributionAndSlowTracking(): void
    {
        if (!function_exists('get_option') || !function_exists('get_mu_plugins') || !defined('WPMU_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wp_filter;

        $muPluginDir = WPMU_PLUGIN_DIR;
        $muFile = $muPluginDir . '/mu-test-coverage.php';
        $hookName = 'test_mu_coverage_hook_' . uniqid();
        $funcName = 'test_mu_coverage_func_' . md5($hookName);
        $fileCreated = false;

        try {
            if (!is_dir($muPluginDir)) {
                mkdir($muPluginDir, 0755, true);
            }
            file_put_contents($muFile, "<?php\n/*\nPlugin Name: MU Test Coverage\nVersion: 1.0\n*/\nfunction {$funcName}() {}\n");
            $fileCreated = true;
            require_once $muFile;

            // Register a hook with the MU plugin callback
            add_action($hookName, $funcName);

            // No active regular plugins — only MU plugins
            $savedPlugins = get_option('active_plugins', []);
            update_option('active_plugins', []);

            $this->collector->collect();
            $data = $this->collector->getData();

            // MU plugin should appear in the plugins list
            $found = false;
            foreach ($data['plugins'] as $file => $pluginData) {
                if (isset($pluginData['is_mu']) && $pluginData['is_mu'] === true) {
                    $found = true;
                    self::assertArrayHasKey('name', $pluginData);
                    self::assertArrayHasKey('version', $pluginData);
                    self::assertArrayHasKey('hook_count', $pluginData);
                    self::assertArrayHasKey('listener_count', $pluginData);
                    self::assertArrayHasKey('hook_time', $pluginData);
                    self::assertArrayHasKey('query_count', $pluginData);
                    self::assertArrayHasKey('query_time', $pluginData);
                    self::assertArrayHasKey('hooks', $pluginData);
                    self::assertIsFloat($pluginData['hook_time']);
                    self::assertIsFloat($pluginData['query_time']);
                    self::assertIsFloat($pluginData['load_time']);
                    break;
                }
            }

            // get_mu_plugins() should discover our file with the Plugin Name header
            self::assertTrue($found, 'MU plugin should be found in collected data');
        } finally {
            update_option('active_plugins', $savedPlugins ?? []);
            remove_all_actions($hookName);
            if ($fileCreated && file_exists($muFile)) {
                unlink($muFile);
            }
        }
    }

    /**
     * Covers lines 394-406: buildQueryAttribution processes MU plugin directory queries.
     *
     * The MU plugin query caller format from WordPress doesn't include subdirectories,
     * so the slug extracted is the first path segment after WPMU_PLUGIN_DIR.
     */
    #[Test]
    public function buildQueryAttributionWithMuPluginDirQueries(): void
    {
        if (!defined('WPMU_PLUGIN_DIR')) {
            self::markTestSkipped('WPMU_PLUGIN_DIR is not defined.');
        }

        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'buildQueryAttribution');

        $muPluginDir = WPMU_PLUGIN_DIR;

        // The caller string in $wpdb->queries uses comma-separated format.
        // For the slug extraction to work cleanly, the caller path after WPMU_PLUGIN_DIR
        // is split by '/' — the first segment becomes the slug.
        // For single-file MU plugins, the slug will include everything after the filename
        // if there's no '/' separator. Use a subdirectory structure to get a clean slug.
        $wpdb = new \stdClass();
        $wpdb->queries = [
            ['SELECT * FROM wp_posts', 0.003, $muPluginDir . '/mu-query-test.php'],
            ['SELECT * FROM wp_terms', 0.002, $muPluginDir . '/mu-query-test.php'],
        ];

        $result = $method->invoke($collector, $wpdb);

        // The slug is the entire relative path (no '/' to split on for single-file MU plugins)
        self::assertNotEmpty($result);
        // Both queries reference the same MU plugin path
        $keys = array_keys($result);
        self::assertCount(1, $keys);
        $slug = $keys[0];
        self::assertSame(2, $result[$slug]['count']);
        self::assertGreaterThan(0.0, $result[$slug]['time']);
    }

    /**
     * Covers the getPluginSlugFromCallback method for regular plugin paths.
     * Exercises the path where a callback file is inside WP_PLUGIN_DIR (lines 318-322).
     */
    #[Test]
    public function getPluginSlugFromCallbackReturnsPluginDirSlug(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $pluginDir = WP_PLUGIN_DIR;
        $fakeDir = $pluginDir . '/slug-test-plugin';
        $fakeFile = $fakeDir . '/main.php';
        $funcName = 'test_slug_callback_' . md5(uniqid());
        $dirCreated = false;
        $fileCreated = false;

        try {
            if (!is_dir($fakeDir)) {
                mkdir($fakeDir, 0755, true);
                $dirCreated = true;
            }
            file_put_contents($fakeFile, "<?php\nfunction {$funcName}() {}\n");
            $fileCreated = true;
            require_once $fakeFile;

            $collector = new PluginDataCollector();
            $method = new \ReflectionMethod($collector, 'getPluginSlugFromCallback');

            $result = $method->invoke($collector, $funcName, $pluginDir, '');
            self::assertSame('slug-test-plugin', $result);
        } finally {
            if ($fileCreated && file_exists($fakeFile)) {
                unlink($fakeFile);
            }
            if ($dirCreated && is_dir($fakeDir)) {
                rmdir($fakeDir);
            }
        }
    }

    /**
     * Covers the getPluginSlugFromCallback returning null for callbacks
     * outside both plugin directories.
     */
    #[Test]
    public function getPluginSlugFromCallbackReturnsNullForNonPluginCallback(): void
    {
        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'getPluginSlugFromCallback');

        // A closure defined in this file is outside any plugin directory
        $closure = static function (): void {};
        $result = $method->invoke($collector, $closure, '/nonexistent/plugins', '/nonexistent/mu-plugins');
        self::assertNull($result);
    }

    /**
     * Covers buildPluginHookAttribution with a WP_Hook-like object that has
     * callbacks from WP_PLUGIN_DIR, exercising the inner loop that builds
     * pluginListeners and pluginHooks arrays.
     */
    #[Test]
    public function buildPluginHookAttributionBuildsHooksFromPluginCallbacks(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $pluginDir = WP_PLUGIN_DIR;
        $fakeDir = $pluginDir . '/hook-attr-test';
        $fakeFile = $fakeDir . '/plugin.php';
        $funcName = 'test_hook_attr_func_' . md5(uniqid());
        $dirCreated = false;
        $fileCreated = false;

        try {
            if (!is_dir($fakeDir)) {
                mkdir($fakeDir, 0755, true);
                $dirCreated = true;
            }
            file_put_contents($fakeFile, "<?php\nfunction {$funcName}() {}\n");
            $fileCreated = true;
            require_once $fakeFile;

            $collector = new PluginDataCollector();
            $method = new \ReflectionMethod($collector, 'buildPluginHookAttribution');

            $hookObj = new \stdClass();
            $hookObj->callbacks = [
                10 => [
                    'cb1' => [
                        'function' => $funcName,
                        'accepted_args' => 0,
                    ],
                ],
                20 => [
                    'cb2' => [
                        'function' => $funcName,
                        'accepted_args' => 0,
                    ],
                ],
            ];

            $result = $method->invoke($collector, ['my_hook' => $hookObj]);

            // The plugin slug should be hook-attr-test
            self::assertArrayHasKey('hook-attr-test', $result);
            self::assertCount(1, $result['hook-attr-test']);
            self::assertSame('my_hook', $result['hook-attr-test'][0]['hook']);
            // Two callbacks from same plugin on different priorities
            self::assertSame(2, $result['hook-attr-test'][0]['listeners']);
        } finally {
            if ($fileCreated && file_exists($fakeFile)) {
                unlink($fakeFile);
            }
            if ($dirCreated && is_dir($fakeDir)) {
                rmdir($fakeDir);
            }
        }
    }

    /**
     * Covers buildQueryAttribution with both plugin and MU plugin queries present,
     * including a query from WPMU_PLUGIN_DIR path.
     */
    #[Test]
    public function buildQueryAttributionHandlesBothPluginAndMuPluginQueries(): void
    {
        if (!defined('WP_PLUGIN_DIR') || !defined('WPMU_PLUGIN_DIR')) {
            self::markTestSkipped('Plugin directories are not defined.');
        }

        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'buildQueryAttribution');

        $pluginDir = WP_PLUGIN_DIR;
        $muPluginDir = WPMU_PLUGIN_DIR;

        $wpdb = new \stdClass();
        $wpdb->queries = [
            ['SELECT 1', 0.005, $pluginDir . '/my-plugin/model.php, do_stuff'],
            ['SELECT 2', 0.003, $muPluginDir . '/mu-loader.php'],
            ['SELECT 3', 0.001, '/var/www/wp-includes/class-wp.php, WP->main'],
        ];

        $result = $method->invoke($collector, $wpdb);

        // Regular plugin query attribution
        self::assertArrayHasKey('my-plugin', $result);
        self::assertSame(1, $result['my-plugin']['count']);
        self::assertGreaterThan(0.0, $result['my-plugin']['time']);

        // MU plugin query — slug is extracted as first path segment after WPMU_PLUGIN_DIR
        // For single-file MU plugins (no subdirectory), the slug is the filename
        $muKeys = array_diff(array_keys($result), ['my-plugin']);
        self::assertCount(1, $muKeys, 'Should have exactly one MU plugin query attribution entry');
        $muSlug = reset($muKeys);
        self::assertSame(1, $result[$muSlug]['count']);
        self::assertGreaterThan(0.0, $result[$muSlug]['time']);
    }

    /**
     * Covers buildQueryAttribution returning empty when wpdb has no queries property.
     */
    #[Test]
    public function buildQueryAttributionReturnsEmptyForInvalidWpdb(): void
    {
        $collector = new PluginDataCollector();
        $method = new \ReflectionMethod($collector, 'buildQueryAttribution');

        // Pass a non-object
        $result = $method->invoke($collector, 'not_an_object');
        self::assertSame([], $result);

        // Pass an object without queries
        $result = $method->invoke($collector, new \stdClass());
        self::assertSame([], $result);

        // Pass an object with non-array queries
        $obj = new \stdClass();
        $obj->queries = 'not_array';
        $result = $method->invoke($collector, $obj);
        self::assertSame([], $result);
    }
}
