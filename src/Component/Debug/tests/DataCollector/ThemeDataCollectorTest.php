<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\ThemeDataCollector;

final class ThemeDataCollectorTest extends TestCase
{
    private ThemeDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new ThemeDataCollector();
    }

    #[Test]
    public function getNameReturnsTheme(): void
    {
        self::assertSame('theme', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsTheme(): void
    {
        self::assertSame('Theme', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('', $data['name']);
        self::assertSame('', $data['version']);
        self::assertFalse($data['is_child_theme']);
        self::assertFalse($data['is_block_theme']);
        self::assertSame('', $data['template_file']);
        self::assertSame([], $data['template_parts']);
        self::assertSame([], $data['body_classes']);
        self::assertSame([], $data['conditional_tags']);
        self::assertSame([], $data['enqueued_styles']);
        self::assertSame([], $data['enqueued_scripts']);
        self::assertSame(0.0, $data['setup_time']);
        self::assertSame(0.0, $data['render_time']);
        self::assertSame(0, $data['hook_count']);
        self::assertSame(0, $data['listener_count']);
        self::assertSame(0.0, $data['hook_time']);
        self::assertSame([], $data['hooks']);
    }

    #[Test]
    public function getBadgeValueReturnsEmptyString(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['name' => 'Twenty Twenty-Four']);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoData(): void
    {
        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function captureSetupTimeMeasuresDuration(): void
    {
        $this->collector->captureSetupStart();
        usleep(1000); // 1ms
        $this->collector->captureSetupEnd();

        $reflection = new \ReflectionProperty($this->collector, 'setupTime');
        $setupTime = $reflection->getValue($this->collector);

        self::assertGreaterThan(0.0, $setupTime);
    }

    #[Test]
    public function captureRenderTimeMeasuresDuration(): void
    {
        $this->collector->captureRenderStart();
        usleep(1000); // 1ms
        $this->collector->captureRenderEnd();

        $reflection = new \ReflectionProperty($this->collector, 'renderTime');
        $renderTime = $reflection->getValue($this->collector);

        self::assertGreaterThan(0.0, $renderTime);
    }

    #[Test]
    public function captureTemplateIncludeStoresFileAndReturnsIt(): void
    {
        $template = '/var/www/html/wp-content/themes/flavor/single.php';
        $result = $this->collector->captureTemplateInclude($template);

        self::assertSame($template, $result);

        $reflection = new \ReflectionProperty($this->collector, 'templateFile');
        self::assertSame($template, $reflection->getValue($this->collector));
    }

    #[Test]
    public function captureTemplatePartAppends(): void
    {
        $this->collector->captureTemplatePart('header');
        $this->collector->captureTemplatePart('footer');

        $reflection = new \ReflectionProperty($this->collector, 'templateParts');
        self::assertSame(['header', 'footer'], $reflection->getValue($this->collector));
    }

    #[Test]
    public function captureBodyClassStoresAndReturnsClasses(): void
    {
        $classes = ['single', 'single-post', 'logged-in'];
        $result = $this->collector->captureBodyClass($classes);

        self::assertSame($classes, $result);

        $reflection = new \ReflectionProperty($this->collector, 'bodyClasses');
        self::assertSame($classes, $reflection->getValue($this->collector));
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->captureTemplatePart('header');
        $this->collector->captureSetupStart();
        $this->collector->captureSetupEnd();
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();
        self::assertEmpty($this->collector->getData());

        // Internal state should be cleared
        $templatePartsRef = new \ReflectionProperty($this->collector, 'templateParts');
        self::assertSame([], $templatePartsRef->getValue($this->collector));

        $setupTimeRef = new \ReflectionProperty($this->collector, 'setupTime');
        self::assertSame(0.0, $setupTimeRef->getValue($this->collector));
    }

    #[Test]
    public function collectWithWordPressGathersThemeData(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertNotEmpty($data['name']);
        self::assertIsBool($data['is_child_theme']);
        self::assertIsBool($data['is_block_theme']);
        self::assertIsArray($data['conditional_tags']);
        self::assertIsArray($data['enqueued_styles']);
        self::assertIsArray($data['enqueued_scripts']);
    }

    #[Test]
    public function collectIncludesConditionalTags(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('is_single', $data['conditional_tags']);
        self::assertArrayHasKey('is_page', $data['conditional_tags']);
        self::assertArrayHasKey('is_archive', $data['conditional_tags']);
        self::assertArrayHasKey('is_home', $data['conditional_tags']);
        self::assertArrayHasKey('is_front_page', $data['conditional_tags']);
        self::assertArrayHasKey('is_admin', $data['conditional_tags']);
        self::assertArrayHasKey('is_search', $data['conditional_tags']);
        self::assertArrayHasKey('is_404', $data['conditional_tags']);

        foreach ($data['conditional_tags'] as $tag => $value) {
            self::assertIsBool($value, "conditional_tags[$tag] should be bool");
        }
    }

    #[Test]
    public function collectIncludesThemeHookAttribution(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsInt($data['hook_count']);
        self::assertIsInt($data['listener_count']);
        self::assertIsFloat($data['hook_time']);
        self::assertIsArray($data['hooks']);

        foreach ($data['hooks'] as $hook) {
            self::assertArrayHasKey('hook', $hook);
            self::assertArrayHasKey('listeners', $hook);
            self::assertArrayHasKey('time', $hook);
        }
    }

    #[Test]
    public function collectWithSetupTimingIncludesTimingData(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->captureSetupStart();
        usleep(1000);
        $this->collector->captureSetupEnd();

        $this->collector->captureRenderStart();
        usleep(1000);
        $this->collector->captureRenderEnd();

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertGreaterThan(0.0, $data['setup_time']);
        self::assertGreaterThan(0.0, $data['render_time']);
    }

    #[Test]
    public function collectWithTemplatePartsIncludesTemplateData(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->captureTemplateInclude('/path/to/single.php');
        $this->collector->captureTemplatePart('header');
        $this->collector->captureTemplatePart('content');
        $this->collector->captureBodyClass(['single', 'logged-in']);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('/path/to/single.php', $data['template_file']);
        self::assertSame(['header', 'content'], $data['template_parts']);
        self::assertSame(['single', 'logged-in'], $data['body_classes']);
    }

    #[Test]
    public function collectGathersThemeNameAndVersion(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $theme = wp_get_theme();
        self::assertSame($theme->get('Name'), $data['name']);
        self::assertSame($theme->get('Version'), $data['version']);
    }

    #[Test]
    public function collectChildThemeDetection(): void
    {
        if (!function_exists('wp_get_theme') || !function_exists('is_child_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $isChild = is_child_theme();
        self::assertSame($isChild, $data['is_child_theme']);

        if ($isChild) {
            self::assertNotEmpty($data['child_theme']);
            self::assertNotEmpty($data['parent_theme']);
        } else {
            self::assertSame('', $data['child_theme']);
            self::assertSame('', $data['parent_theme']);
        }
    }

    #[Test]
    public function collectBlockThemeDetection(): void
    {
        if (!function_exists('wp_get_theme') || !function_exists('wp_is_block_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(wp_is_block_theme(), $data['is_block_theme']);
    }

    #[Test]
    public function collectEnqueuedAssetsWithWordPress(): void
    {
        if (!function_exists('wp_get_theme') || !function_exists('wp_enqueue_script')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        wp_enqueue_script('test-theme-script', '/js/theme-test.js', [], '1.0', true);
        wp_enqueue_style('test-theme-style', '/css/theme-test.css', [], '1.0');

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertIsArray($data['enqueued_scripts']);
            self::assertIsArray($data['enqueued_styles']);
            self::assertContains('test-theme-script', $data['enqueued_scripts']);
            self::assertContains('test-theme-style', $data['enqueued_styles']);
        } finally {
            wp_dequeue_script('test-theme-script');
            wp_deregister_script('test-theme-script');
            wp_dequeue_style('test-theme-style');
            wp_deregister_style('test-theme-style');
        }
    }

    #[Test]
    public function captureSetupEndWithoutStartDoesNothing(): void
    {
        // captureSetupEnd without captureSetupStart should not set setupTime
        $this->collector->captureSetupEnd();

        $reflection = new \ReflectionProperty($this->collector, 'setupTime');
        self::assertSame(0.0, $reflection->getValue($this->collector));
    }

    #[Test]
    public function captureRenderEndWithoutStartDoesNothing(): void
    {
        $this->collector->captureRenderEnd();

        $reflection = new \ReflectionProperty($this->collector, 'renderTime');
        self::assertSame(0.0, $reflection->getValue($this->collector));
    }

    /**
     * Covers lines 101-122: collect() early-return path when wp_get_theme()
     * is not available. In a WP environment this path is unreachable, so we
     * verify the default data structure indirectly.
     *
     * Instead, this test verifies that when WP IS loaded, collect() produces
     * complete theme data including name, version, setup_time, render_time,
     * and hook attribution — exercising lines 125-182.
     */
    #[Test]
    public function collectWithWordPressProducesCompleteThemeData(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // Set up timing data to exercise lines 176 (setup_time) and 177 (render_time)
        $this->collector->captureSetupStart();
        usleep(500);
        $this->collector->captureSetupEnd();
        $this->collector->captureRenderStart();
        usleep(500);
        $this->collector->captureRenderEnd();

        // Set template data to exercise template_file, template_parts, body_classes
        $this->collector->captureTemplateInclude('/path/to/theme/single.php');
        $this->collector->captureTemplatePart('header');
        $this->collector->captureBodyClass(['single', 'logged-in']);

        $this->collector->collect();
        $data = $this->collector->getData();

        // Verify all fields are populated
        self::assertNotEmpty($data['name']);
        // Version may be false in test environments where no real theme is installed
        self::assertTrue(is_string($data['version']) || $data['version'] === false);
        self::assertIsBool($data['is_child_theme']);
        self::assertIsBool($data['is_block_theme']);
        self::assertSame('/path/to/theme/single.php', $data['template_file']);
        self::assertSame(['header'], $data['template_parts']);
        self::assertSame(['single', 'logged-in'], $data['body_classes']);
        self::assertGreaterThan(0.0, $data['setup_time']);
        self::assertGreaterThan(0.0, $data['render_time']);
        self::assertIsInt($data['hook_count']);
        self::assertIsInt($data['listener_count']);
        self::assertIsFloat($data['hook_time']);
        self::assertIsArray($data['hooks']);
        self::assertIsArray($data['conditional_tags']);
        self::assertIsArray($data['enqueued_styles']);
        self::assertIsArray($data['enqueued_scripts']);
    }

    /**
     * Covers lines 135-136: The hook time/listener accumulation loop iterating
     * over themeHooks.
     *
     * When theme hooks exist, the loop at lines 134-137 sums hookTime and listenerCount.
     */
    #[Test]
    public function collectAccumulatesThemeHookTimeAndListeners(): void
    {
        if (!function_exists('wp_get_theme') || !defined('ABSPATH')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // Register a hook from the theme directory to ensure buildThemeHookAttribution
        // finds at least one theme hook. We create a temp file inside the themes dir.
        $themeDir = ABSPATH . 'wp-content/themes';
        $currentTheme = function_exists('get_stylesheet') ? get_stylesheet() : 'default';
        $themeSubDir = $themeDir . '/' . $currentTheme;
        $fakeFile = $themeSubDir . '/test-coverage-func.php';
        $funcName = 'test_theme_hook_coverage_' . md5(uniqid());
        $hookName = 'test_theme_hook_accum_' . uniqid();
        $fileCreated = false;

        try {
            if (is_dir($themeSubDir)) {
                file_put_contents($fakeFile, "<?php\nfunction {$funcName}() {}\n");
                $fileCreated = true;
                require_once $fakeFile;

                add_action($hookName, $funcName);
            }

            $this->collector->collect();
            $data = $this->collector->getData();

            // hook_count and listener_count should be integers >= 0
            self::assertIsInt($data['hook_count']);
            self::assertIsInt($data['listener_count']);
            self::assertIsFloat($data['hook_time']);

            // If we successfully registered a theme hook, verify it was found
            if ($fileCreated) {
                self::assertGreaterThanOrEqual(1, $data['hook_count']);
                self::assertGreaterThanOrEqual(1, $data['listener_count']);
            }
        } finally {
            if (function_exists('remove_all_actions')) {
                remove_all_actions($hookName);
            }
            if ($fileCreated && file_exists($fakeFile)) {
                unlink($fakeFile);
            }
        }
    }

    /**
     * Covers lines 217, 224: buildThemeHookAttribution — themeDir empty returns [],
     * and non-object entries in $wp_filter are skipped.
     */
    #[Test]
    public function buildThemeHookAttributionSkipsNonObjectEntries(): void
    {
        if (!defined('ABSPATH')) {
            self::markTestSkipped('ABSPATH is not defined.');
        }

        $collector = new ThemeDataCollector();
        $method = new \ReflectionMethod($collector, 'buildThemeHookAttribution');

        // Pass an array with non-object and invalid entries
        $wpFilter = [
            'hook_a' => 'not_an_object',
            'hook_b' => null,
            'hook_c' => 42,
            'hook_d' => (object) [],  // object without callbacks property
        ];

        $result = $method->invoke($collector, $wpFilter);

        // All entries should be skipped
        self::assertSame([], $result);
    }

    /**
     * Covers lines 233, 239-243: buildThemeHookAttribution detects theme listeners
     * from callbacks inside the themes directory and builds hook entries.
     */
    #[Test]
    public function buildThemeHookAttributionDetectsThemeCallbacks(): void
    {
        if (!defined('ABSPATH')) {
            self::markTestSkipped('ABSPATH is not defined.');
        }

        $themeDir = ABSPATH . 'wp-content/themes';
        $testThemeDir = $themeDir . '/test-coverage-theme';
        $testFile = $testThemeDir . '/functions.php';
        $funcName = 'test_theme_attr_func_' . md5(uniqid());
        $dirCreated = false;
        $fileCreated = false;

        try {
            if (!is_dir($testThemeDir)) {
                mkdir($testThemeDir, 0755, true);
                $dirCreated = true;
            }
            file_put_contents($testFile, "<?php\nfunction {$funcName}() {}\n");
            $fileCreated = true;
            require_once $testFile;

            $collector = new ThemeDataCollector();
            $method = new \ReflectionMethod($collector, 'buildThemeHookAttribution');

            $hookObj = new \stdClass();
            $hookObj->callbacks = [
                10 => [
                    'cb1' => [
                        'function' => $funcName,
                        'accepted_args' => 0,
                    ],
                ],
            ];

            $result = $method->invoke($collector, ['my_theme_hook' => $hookObj]);

            // Should find the theme listener
            self::assertCount(1, $result);
            self::assertSame('my_theme_hook', $result[0]['hook']);
            self::assertSame(1, $result[0]['listeners']);
            self::assertSame(0.0, $result[0]['time']);
        } finally {
            if ($fileCreated && file_exists($testFile)) {
                unlink($testFile);
            }
            if ($dirCreated && is_dir($testThemeDir)) {
                rmdir($testThemeDir);
            }
        }
    }

    /**
     * Covers line 280: getCallbackFileName returns null for a non-function string callback.
     * Also covers line 274 (the null return at the end of getCallbackFileName).
     */
    #[Test]
    public function getCallbackFileNameReturnsNullForVariousInputs(): void
    {
        $collector = new ThemeDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackFileName');

        // Non-existent function name
        $result = $method->invoke($collector, 'nonexistent_function_' . uniqid());
        self::assertNull($result);

        // Integer
        $result = $method->invoke($collector, 42);
        self::assertNull($result);

        // Null
        $result = $method->invoke($collector, null);
        self::assertNull($result);

        // Empty array
        $result = $method->invoke($collector, []);
        self::assertNull($result);
    }

    /**
     * Covers getCallbackFileName with closure, array, and string callbacks.
     */
    #[Test]
    public function getCallbackFileNameResolvesVariousCallbackTypes(): void
    {
        $collector = new ThemeDataCollector();
        $method = new \ReflectionMethod($collector, 'getCallbackFileName');

        // Closure
        $closure = static function (): void {};
        $result = $method->invoke($collector, $closure);
        self::assertSame(__FILE__, $result);

        // Array callback (object + method)
        $result = $method->invoke($collector, [$this, 'getCallbackFileNameResolvesVariousCallbackTypes']);
        self::assertSame(__FILE__, $result);

        // Built-in function (internal — returns null because getFileName is false)
        $result = $method->invoke($collector, 'phpinfo');
        self::assertNull($result);
    }

    /**
     * Covers buildThemeHookAttribution with multiple theme listeners
     * to verify sorting by listeners descending.
     */
    #[Test]
    public function buildThemeHookAttributionSortsByListenerCount(): void
    {
        if (!defined('ABSPATH')) {
            self::markTestSkipped('ABSPATH is not defined.');
        }

        $themeDir = ABSPATH . 'wp-content/themes';
        $testThemeDir = $themeDir . '/sort-test-theme';
        $testFile = $testThemeDir . '/functions.php';
        $funcName1 = 'test_sort_attr_func1_' . md5(uniqid());
        $funcName2 = 'test_sort_attr_func2_' . md5(uniqid());
        $dirCreated = false;
        $fileCreated = false;

        try {
            if (!is_dir($testThemeDir)) {
                mkdir($testThemeDir, 0755, true);
                $dirCreated = true;
            }
            file_put_contents($testFile, "<?php\nfunction {$funcName1}() {}\nfunction {$funcName2}() {}\n");
            $fileCreated = true;
            require_once $testFile;

            $collector = new ThemeDataCollector();
            $method = new \ReflectionMethod($collector, 'buildThemeHookAttribution');

            // Hook A: 1 theme listener, Hook B: 3 theme listeners
            $hookObjA = new \stdClass();
            $hookObjA->callbacks = [
                10 => [
                    'cb1' => ['function' => $funcName1, 'accepted_args' => 0],
                ],
            ];

            $hookObjB = new \stdClass();
            $hookObjB->callbacks = [
                10 => [
                    'cb1' => ['function' => $funcName1, 'accepted_args' => 0],
                    'cb2' => ['function' => $funcName2, 'accepted_args' => 0],
                ],
                20 => [
                    'cb3' => ['function' => $funcName1, 'accepted_args' => 0],
                ],
            ];

            $result = $method->invoke($collector, [
                'hook_few_listeners' => $hookObjA,
                'hook_many_listeners' => $hookObjB,
            ]);

            // Should be sorted by listeners descending
            self::assertCount(2, $result);
            self::assertSame('hook_many_listeners', $result[0]['hook']);
            self::assertSame(3, $result[0]['listeners']);
            self::assertSame('hook_few_listeners', $result[1]['hook']);
            self::assertSame(1, $result[1]['listeners']);
        } finally {
            if ($fileCreated && file_exists($testFile)) {
                unlink($testFile);
            }
            if ($dirCreated && is_dir($testThemeDir)) {
                rmdir($testThemeDir);
            }
        }
    }
}
