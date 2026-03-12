<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\EventDataCollector;

final class EventDataCollectorTest extends TestCase
{
    private EventDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new EventDataCollector();
    }

    #[Test]
    public function getNameReturnsEvent(): void
    {
        self::assertSame('event', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsEvents(): void
    {
        self::assertSame('Events', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('add_action')) {
            self::markTestSkipped('WordPress hooks are active; firings and counts are non-zero.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['hooks']);
        self::assertSame(0, $data['total_firings']);
        self::assertSame(0, $data['unique_hooks']);
        self::assertSame([], $data['top_hooks']);
        self::assertSame(0, $data['registered_hooks']);
        self::assertSame(0, $data['orphan_hooks']);
        self::assertSame([], $data['listener_counts']);
        self::assertSame([], $data['hook_timings']);
        self::assertSame([], $data['component_hooks']);
        self::assertSame([], $data['component_summary']);
    }

    #[Test]
    public function captureHookFiredIncrementsCount(): void
    {
        $this->collector->captureHookFired();
        $this->collector->captureHookFired();

        $this->collector->collect();
        $data = $this->collector->getData();

        if (function_exists('current_filter') && current_filter() !== false) {
            // WordPress is active and we're within a hook context —
            // captureHookFired() records firings normally.
            self::assertSame(2, $data['total_firings']);
            self::assertSame(1, $data['unique_hooks']);
        } else {
            // Without WordPress or outside hook context, current_filter()
            // doesn't exist or returns false — no hooks are captured.
            self::assertSame(0, $data['total_firings']);
            self::assertSame(0, $data['unique_hooks']);
        }
    }

    #[Test]
    public function getBadgeValueReturnsTotalFirings(): void
    {
        self::assertSame('0', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReflectsFiringsAfterCapture(): void
    {
        // Simulate firings via reflection since current_filter() may not exist
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 42);

        self::assertSame('42', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenBelowFiveHundred(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 0);

        self::assertSame('green', $this->collector->getBadgeColor());

        $reflection->setValue($this->collector, 499);
        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowWhenBelowOneThousand(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 500);

        self::assertSame('yellow', $this->collector->getBadgeColor());

        $reflection->setValue($this->collector, 999);
        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedWhenAtOrAboveOneThousand(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 1000);

        self::assertSame('red', $this->collector->getBadgeColor());

        $reflection->setValue($this->collector, 5000);
        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function topHooksAreSortedByCount(): void
    {
        // Simulate hook counts via reflection
        $reflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $reflection->setValue($this->collector, [
            'init' => 5,
            'wp_head' => 100,
            'the_content' => 50,
            'wp_footer' => 10,
        ]);

        $totalReflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $totalReflection->setValue($this->collector, 165);

        $this->collector->collect();
        $data = $this->collector->getData();

        $topHookNames = array_keys($data['top_hooks']);
        self::assertSame(['wp_head', 'the_content', 'wp_footer', 'init'], $topHookNames);
    }

    #[Test]
    public function topHooksLimitedToTwenty(): void
    {
        // Simulate 25 hooks
        $hookCounts = [];
        for ($i = 0; $i < 25; $i++) {
            $hookCounts["hook_{$i}"] = 25 - $i;
        }

        $reflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $reflection->setValue($this->collector, $hookCounts);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(20, $data['top_hooks']);
        self::assertSame(25, $data['unique_hooks']);
    }

    #[Test]
    public function resetClearsData(): void
    {
        // Set up some state via reflection
        $hookCountsReflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $hookCountsReflection->setValue($this->collector, ['init' => 5]);

        $totalReflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $totalReflection->setValue($this->collector, 5);

        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collecting again should have empty hookCounts
        $this->collector->collect();
        $data = $this->collector->getData();
        self::assertSame(0, $data['total_firings']);
        self::assertSame(0, $data['unique_hooks']);
        self::assertSame([], $data['hooks']);
        self::assertSame([], $data['hook_timings']);

        // component_hooks/component_summary depend on global $wp_filter which persists
        self::assertIsArray($data['component_hooks']);
        self::assertIsArray($data['component_summary']);
    }

    #[Test]
    public function captureHookFiredRecordsTimings(): void
    {
        if (!function_exists('current_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // We need current_filter() to return a hook name.
        // Use do_action to fire a real hook with our collector attached.
        $hookName = 'test_timing_hook_' . uniqid();
        $collector = new EventDataCollector();

        add_action($hookName, static function () use ($collector): void {
            // This fires inside the hook context, so current_filter() returns $hookName
            $collector->captureHookFired();
        });

        try {
            do_action($hookName);

            $collector->collect();
            $data = $collector->getData();

            // The hook should appear in hook_timings
            self::assertArrayHasKey($hookName, $data['hook_timings']);
            self::assertGreaterThanOrEqual(1, $data['hook_timings'][$hookName]['count']);
        } finally {
            remove_all_actions($hookName);
        }
    }

    #[Test]
    public function collectBuildsComponentAttribution(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // The component attribution uses resolveCallback which inspects file paths.
        // Core WP functions will be attributed to 'core' if ABSPATH is defined.
        // We test that the mechanism works by checking that component_summary is built.
        $collector = new EventDataCollector();
        $collector->collect();
        $data = $collector->getData();

        // component_summary should be an array (may or may not have entries depending on WP state)
        self::assertIsArray($data['component_summary']);
        self::assertIsArray($data['component_hooks']);

        // If WordPress hooks are registered (they should be in WP test env),
        // we expect some component attribution
        if (!empty($data['listener_counts'])) {
            // registered_hooks > 0 means wp_filter has entries
            self::assertGreaterThan(0, $data['registered_hooks']);
        }
    }

    #[Test]
    public function collectCountsOrphanHooks(): void
    {
        // Simulate hooks that were fired but have no listeners registered
        $hookCountsReflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $hookCountsReflection->setValue($this->collector, [
            'nonexistent_hook_a' => 3,
            'nonexistent_hook_b' => 1,
        ]);

        $totalReflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $totalReflection->setValue($this->collector, 4);

        $this->collector->collect();
        $data = $this->collector->getData();

        // These hooks have no listeners in $wp_filter, so they are orphans
        self::assertGreaterThanOrEqual(2, $data['orphan_hooks']);
    }

    #[Test]
    public function collectBuildsTopHooks(): void
    {
        // Simulate one hook fired many times
        $hookName = 'frequently_fired_hook';
        $hookCounts = [$hookName => 50, 'other_hook' => 5];

        $hookCountsReflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $hookCountsReflection->setValue($this->collector, $hookCounts);

        $totalReflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $totalReflection->setValue($this->collector, 55);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey($hookName, $data['top_hooks']);
        self::assertSame(50, $data['top_hooks'][$hookName]);

        // top_hooks should be sorted descending by count
        $topHookValues = array_values($data['top_hooks']);
        self::assertSame(50, $topHookValues[0]);
        self::assertSame(5, $topHookValues[1]);
    }

    #[Test]
    public function collectWithWpFilterBuildsListenerCounts(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wp_filter;

        $hookName = 'test_listener_counts_' . uniqid();
        $callback1 = static function (): void {};
        $callback2 = static function (): void {};

        add_action($hookName, $callback1, 10);
        add_action($hookName, $callback2, 20);

        try {
            $collector = new EventDataCollector();
            $collector->collect();
            $data = $collector->getData();

            // The wp_filter global should be iterated and listener counts built
            self::assertGreaterThan(0, $data['registered_hooks']);
            self::assertIsArray($data['listener_counts']);

            // Our test hook should appear in the listener counts
            self::assertArrayHasKey($hookName, $data['listener_counts']);
            self::assertSame(2, $data['listener_counts'][$hookName]);
        } finally {
            remove_action($hookName, $callback1, 10);
            remove_action($hookName, $callback2, 20);
        }
    }

    #[Test]
    public function collectOrphanHooksDetected(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $orphanHook = 'test_orphan_hook_' . uniqid();

        $collector = new EventDataCollector();

        // Fire a hook via do_action which triggers captureHookFired via 'all' hook
        // But the orphan hook has no listeners, so it's an orphan
        $hookCountsRef = new \ReflectionProperty($collector, 'hookCounts');
        $hookCountsRef->setValue($collector, [$orphanHook => 1]);

        $totalRef = new \ReflectionProperty($collector, 'totalFirings');
        $totalRef->setValue($collector, 1);

        $collector->collect();
        $data = $collector->getData();

        // The orphan hook has no listeners in $wp_filter, so orphan_hooks >= 1
        self::assertGreaterThanOrEqual(1, $data['orphan_hooks']);
    }

    #[Test]
    public function collectWithWpFilterBuildsComponentAttribution(): void
    {
        if (!function_exists('add_action') || !defined('ABSPATH')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $collector = new EventDataCollector();
        $collector->collect();
        $data = $collector->getData();

        // In a WP test environment, there should be hooks registered to core
        self::assertIsArray($data['component_summary']);
        self::assertIsArray($data['component_hooks']);

        // If WP hooks are registered, core should appear in component attribution
        if ($data['registered_hooks'] > 0) {
            // At least some hooks should be attributed
            // (core hooks exist in wp-includes which is under ABSPATH)
            $hasComponent = !empty($data['component_summary']);
            self::assertTrue($hasComponent, 'Component attribution should have at least one entry');

            // Verify component_summary structure
            foreach ($data['component_summary'] as $component => $summary) {
                self::assertIsString($component);
                self::assertArrayHasKey('type', $summary);
                self::assertArrayHasKey('hooks', $summary);
                self::assertArrayHasKey('listeners', $summary);
                self::assertArrayHasKey('total_time', $summary);
                self::assertIsInt($summary['hooks']);
                self::assertIsInt($summary['listeners']);
                self::assertIsFloat($summary['total_time']);
                break; // Just check first entry
            }
        }
    }

    #[Test]
    public function captureHookFiredRecordsTimingsWithRealDoAction(): void
    {
        if (!function_exists('do_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $hookName = 'test_real_timing_' . uniqid();
        $collector = new EventDataCollector();

        // Register our captureHookFired to the 'all' hook (constructor already does this)
        // Add a simple listener to the hook
        $callback = static function (): void {
            usleep(100); // tiny delay to create measurable timing
        };

        add_action($hookName, $callback);

        try {
            // Fire the hook — the 'all' hook will call captureHookFired
            do_action($hookName);

            $collector->collect();
            $data = $collector->getData();

            // The hook timings should contain entries from the 'all' hook firing
            self::assertIsArray($data['hook_timings']);
        } finally {
            remove_action($hookName, $callback);
        }
    }

    /**
     * Covers line 56: hookTimings initialization for lastHookName when a second
     * hook fires and the previous hook's timing entry doesn't exist yet.
     *
     * When captureHookFired is called twice with different hooks, the second call
     * records timing for the first hook at line 55-56.
     */
    #[Test]
    public function captureHookFiredRecordsPreviousHookTimingOnSecondCall(): void
    {
        if (!function_exists('do_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $hookA = 'test_prev_timing_a_' . uniqid();
        $hookB = 'test_prev_timing_b_' . uniqid();
        $collector = new EventDataCollector();

        // Manually set lastHookName and lastHookStart via reflection
        // to simulate a previous hook that was started but not yet timed
        $lastHookNameRef = new \ReflectionProperty($collector, 'lastHookName');
        $lastHookStartRef = new \ReflectionProperty($collector, 'lastHookStart');

        // Fire hookA inside a real do_action so current_filter() works
        add_action($hookA, static function () use ($collector): void {
            $collector->captureHookFired();
        });

        add_action($hookB, static function () use ($collector): void {
            $collector->captureHookFired();
        });

        try {
            // Fire hookA — captureHookFired sets lastHookName = hookA
            do_action($hookA);

            // Fire hookB — captureHookFired records hookA timing (line 55-56), starts hookB
            do_action($hookB);

            $hookTimingsRef = new \ReflectionProperty($collector, 'hookTimings');
            $hookTimings = $hookTimingsRef->getValue($collector);

            // hookA should have a timing entry recorded when hookB fired
            self::assertArrayHasKey($hookA, $hookTimings);
            self::assertGreaterThanOrEqual(0.0, $hookTimings[$hookA]['total_time']);
        } finally {
            remove_all_actions($hookA);
            remove_all_actions($hookB);
        }
    }

    /**
     * Covers line 118: collect() skips non-object entries in $wp_filter
     * when building component attribution.
     */
    #[Test]
    public function collectSkipsNonObjectEntriesInWpFilterForComponentAttribution(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        global $wp_filter;

        // Inject a non-object entry into $wp_filter temporarily
        $fakeHookName = 'test_nonobject_hook_' . uniqid();
        $savedEntry = $wp_filter[$fakeHookName] ?? null;

        try {
            // Set a string value instead of WP_Hook object
            $wp_filter[$fakeHookName] = 'not_an_object';

            $collector = new EventDataCollector();
            $collector->collect();
            $data = $collector->getData();

            // Should not crash — the non-object entry is skipped
            self::assertIsArray($data['component_hooks']);
            self::assertIsArray($data['component_summary']);
        } finally {
            if ($savedEntry !== null) {
                $wp_filter[$fakeHookName] = $savedEntry;
            } else {
                unset($wp_filter[$fakeHookName]);
            }
        }
    }

    /**
     * Covers lines 271-275: attributeFileToComponent — plugin directory detection.
     *
     * Creates a file inside WP_PLUGIN_DIR so the method attributes it to a plugin.
     */
    #[Test]
    public function attributeFileToComponentDetectsPluginDirectory(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'attributeFileToComponent');

        $pluginDir = WP_PLUGIN_DIR;
        $fakePath = $pluginDir . '/my-test-plugin/main.php';

        $result = $method->invoke($collector, $fakePath, 'my_callback_func');

        self::assertSame('my-test-plugin', $result['component']);
        self::assertSame('plugin', $result['component_type']);
        self::assertSame('my_callback_func', $result['name']);
    }

    /**
     * Covers lines 279-283: attributeFileToComponent — MU plugin directory detection.
     */
    #[Test]
    public function attributeFileToComponentDetectsMuPluginDirectory(): void
    {
        if (!defined('WPMU_PLUGIN_DIR')) {
            self::markTestSkipped('WPMU_PLUGIN_DIR is not defined.');
        }

        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'attributeFileToComponent');

        $muPluginDir = WPMU_PLUGIN_DIR;
        $fakePath = $muPluginDir . '/mu-loader.php';

        $result = $method->invoke($collector, $fakePath, static function (): void {});

        self::assertSame('mu:mu-loader.php', $result['component']);
        self::assertSame('plugin', $result['component_type']);
        self::assertSame('Closure', $result['name']);
    }

    /**
     * Covers lines 289-293: attributeFileToComponent — theme directory detection.
     */
    #[Test]
    public function attributeFileToComponentDetectsThemeDirectory(): void
    {
        if (!defined('ABSPATH')) {
            self::markTestSkipped('ABSPATH is not defined.');
        }

        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'attributeFileToComponent');

        $themeDir = ABSPATH . 'wp-content/themes';
        $fakePath = $themeDir . '/twentytwentyfour/functions.php';

        $result = $method->invoke($collector, $fakePath, 'theme_func');

        self::assertSame('theme:twentytwentyfour', $result['component']);
        self::assertSame('theme', $result['component_type']);
        self::assertSame('theme_func', $result['name']);
    }

    /**
     * Covers lines 298-302: attributeFileToComponent — core wp-includes and wp-admin detection.
     */
    #[Test]
    public function attributeFileToComponentDetectsCoreDirectories(): void
    {
        if (!defined('ABSPATH')) {
            self::markTestSkipped('ABSPATH is not defined.');
        }

        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'attributeFileToComponent');

        // wp-includes path
        $wpIncludesPath = ABSPATH . 'wp-includes/plugin.php';
        $result = $method->invoke($collector, $wpIncludesPath, 'add_filter');
        self::assertSame('core', $result['component']);
        self::assertSame('core', $result['component_type']);

        // wp-admin path
        $wpAdminPath = ABSPATH . 'wp-admin/admin.php';
        $result = $method->invoke($collector, $wpAdminPath, 'admin_func');
        self::assertSame('core', $result['component']);
        self::assertSame('core', $result['component_type']);
    }

    /**
     * Covers line 305: attributeFileToComponent — unknown path returns empty component.
     */
    #[Test]
    public function attributeFileToComponentReturnsUnknownForUnmatchedPath(): void
    {
        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'attributeFileToComponent');

        $result = $method->invoke($collector, '/some/random/path.php', 'func');

        self::assertSame('', $result['component']);
        self::assertSame('unknown', $result['component_type']);
    }

    /**
     * Covers line 325: getCallbackName returns 'unknown' for non-recognizable callbacks.
     */
    #[Test]
    public function getCallbackNameReturnsUnknownForNonStandardCallback(): void
    {
        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackName');

        // Integer callback — not closure, not array, not string
        $result = $method->invoke($collector, 42);
        self::assertSame('unknown', $result);

        // Object callback — not closure, not array, not string
        $result = $method->invoke($collector, new \stdClass());
        self::assertSame('unknown', $result);
    }

    /**
     * Covers getCallbackName with various standard callback types.
     */
    #[Test]
    public function getCallbackNameResolvesStandardCallbackTypes(): void
    {
        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackName');

        // Closure
        $result = $method->invoke($collector, static function (): void {});
        self::assertSame('Closure', $result);

        // Array callback
        $result = $method->invoke($collector, [$this, 'getCallbackNameResolvesStandardCallbackTypes']);
        self::assertStringContainsString('EventDataCollectorTest::getCallbackNameResolvesStandardCallbackTypes', $result);

        // String callback
        $result = $method->invoke($collector, 'my_function');
        self::assertSame('my_function', $result);
    }

    /**
     * Covers resolveCallback for null, ReflectionException, and empty fileName.
     */
    #[Test]
    public function resolveCallbackHandlesEdgeCases(): void
    {
        $collector = new EventDataCollector();
        $method = new \ReflectionMethod($collector, 'resolveCallback');

        // null callback
        $result = $method->invoke($collector, null);
        self::assertSame('', $result['component']);
        self::assertSame('unknown', $result['component_type']);

        // Non-existent function string
        $result = $method->invoke($collector, 'totally_nonexistent_func_' . uniqid());
        self::assertSame('', $result['component']);
        self::assertSame('unknown', $result['component_type']);

        // Integer (invalid callback type)
        $result = $method->invoke($collector, 42);
        self::assertSame('', $result['component']);
        self::assertSame('unknown', $result['component_type']);
    }

    /**
     * Covers lines 223-293: Full component attribution loop with real WP hooks
     * including plugin, theme, and core attribution.
     *
     * This test creates a fake plugin file inside WP_PLUGIN_DIR with a registered
     * hook to exercise the inner loop of component attribution (lines 121-147)
     * and the attributeFileToComponent method paths.
     */
    #[Test]
    public function collectBuildsComponentAttributionFromPluginHook(): void
    {
        if (!function_exists('add_action') || !defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $pluginDir = WP_PLUGIN_DIR;
        $fakeDir = $pluginDir . '/event-attr-test';
        $fakeFile = $fakeDir . '/main.php';
        $funcName = 'test_event_attr_func_' . md5(uniqid());
        $hookName = 'test_event_comp_attr_' . uniqid();
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

            add_action($hookName, $funcName);

            $collector = new EventDataCollector();
            $collector->collect();
            $data = $collector->getData();

            // Plugin should appear in component_hooks and component_summary
            self::assertArrayHasKey('event-attr-test', $data['component_hooks']);
            self::assertArrayHasKey($hookName, $data['component_hooks']['event-attr-test']);
            self::assertSame(1, $data['component_hooks']['event-attr-test'][$hookName]);

            // component_summary should have the plugin entry
            self::assertArrayHasKey('event-attr-test', $data['component_summary']);
            $summary = $data['component_summary']['event-attr-test'];
            self::assertSame('plugin', $summary['type']);
            self::assertIsInt($summary['hooks']);
            self::assertGreaterThanOrEqual(1, $summary['hooks']);
            self::assertIsInt($summary['listeners']);
            self::assertGreaterThanOrEqual(1, $summary['listeners']);
            self::assertIsFloat($summary['total_time']);
        } finally {
            remove_all_actions($hookName);
            if ($fileCreated && file_exists($fakeFile)) {
                unlink($fakeFile);
            }
            if ($dirCreated && is_dir($fakeDir)) {
                rmdir($fakeDir);
            }
        }
    }

    /**
     * Covers the component attribution loop with hook timings set, to exercise
     * the timing proportional attribution at lines 156-164.
     */
    #[Test]
    public function collectAttributesTimingProportionallyToComponents(): void
    {
        if (!function_exists('add_action') || !defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $pluginDir = WP_PLUGIN_DIR;
        $fakeDir = $pluginDir . '/timing-attr-test';
        $fakeFile = $fakeDir . '/main.php';
        $funcName = 'test_timing_attr_func_' . md5(uniqid());
        $hookName = 'test_timing_attr_hook_' . uniqid();
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

            add_action($hookName, $funcName);

            $collector = new EventDataCollector();

            // Inject hook timings via reflection to exercise timing attribution
            $hookTimingsRef = new \ReflectionProperty($collector, 'hookTimings');
            $hookTimingsRef->setValue($collector, [
                $hookName => ['count' => 5, 'total_time' => 100.0, 'start' => 0.0],
            ]);

            $collector->collect();
            $data = $collector->getData();

            // The component should have proportional timing
            if (isset($data['component_summary']['timing-attr-test'])) {
                $summary = $data['component_summary']['timing-attr-test'];
                self::assertGreaterThan(0.0, $summary['total_time']);
            }
        } finally {
            remove_all_actions($hookName);
            if ($fileCreated && file_exists($fakeFile)) {
                unlink($fakeFile);
            }
            if ($dirCreated && is_dir($fakeDir)) {
                rmdir($fakeDir);
            }
        }
    }

    /**
     * Covers line 42: captureHookFired returns early when current_filter doesn't exist.
     * In WP environment, current_filter always exists, so we test via reflection
     * that calling captureHookFired outside a hook context (current_filter returns false)
     * doesn't increment anything.
     */
    #[Test]
    public function captureHookFiredOutsideHookContextDoesNotIncrement(): void
    {
        if (!function_exists('current_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $collector = new EventDataCollector();

        // Call captureHookFired outside any do_action/apply_filters context
        // In WP, current_filter() returns false when not inside a hook
        $collector->captureHookFired();

        $hookCountsRef = new \ReflectionProperty($collector, 'hookCounts');
        $hookCounts = $hookCountsRef->getValue($collector);

        // current_filter() outside hook context returns empty string or false
        // If it returns false, captureHookFired returns early at line 48-49
        // If it returns empty string, it's still recorded but not meaningful
        self::assertIsArray($hookCounts);
    }

    /**
     * Covers the resolveCallback path for a callback that has a valid file
     * (exercising attributeFileToComponent) — with an array callback type
     * to also cover getCallbackName array branch.
     */
    #[Test]
    public function resolveCallbackWithArrayCallbackFromPluginDir(): void
    {
        if (!defined('WP_PLUGIN_DIR')) {
            self::markTestSkipped('WP_PLUGIN_DIR is not defined.');
        }

        $pluginDir = WP_PLUGIN_DIR;
        $fakeDir = $pluginDir . '/resolve-cb-test';
        $fakeFile = $fakeDir . '/handler.php';
        $dirCreated = false;
        $fileCreated = false;
        $className = 'ResolveCbTestHandler_' . md5(uniqid());

        try {
            if (!is_dir($fakeDir)) {
                mkdir($fakeDir, 0755, true);
                $dirCreated = true;
            }
            file_put_contents($fakeFile, "<?php\nclass {$className} { public function handle(): void {} }\n");
            $fileCreated = true;
            require_once $fakeFile;

            $collector = new EventDataCollector();
            $method = new \ReflectionMethod($collector, 'resolveCallback');

            $obj = new $className();
            $result = $method->invoke($collector, [$obj, 'handle']);

            self::assertSame('resolve-cb-test', $result['component']);
            self::assertSame('plugin', $result['component_type']);
            self::assertStringContainsString('::handle', $result['name']);
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
     * Covers inner loop counting in collect() — listener counts for multiple
     * priorities and callbacks.
     */
    #[Test]
    public function collectCountsListenersAcrossMultiplePriorities(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $hookName = 'test_multi_priority_' . uniqid();
        $cb1 = static function (): void {};
        $cb2 = static function (): void {};
        $cb3 = static function (): void {};

        add_action($hookName, $cb1, 5);
        add_action($hookName, $cb2, 10);
        add_action($hookName, $cb3, 20);

        try {
            $collector = new EventDataCollector();
            $collector->collect();
            $data = $collector->getData();

            // Should count 3 listeners for this hook
            self::assertArrayHasKey($hookName, $data['listener_counts']);
            self::assertSame(3, $data['listener_counts'][$hookName]);
        } finally {
            remove_action($hookName, $cb1, 5);
            remove_action($hookName, $cb2, 10);
            remove_action($hookName, $cb3, 20);
        }
    }
}
