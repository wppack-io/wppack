<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EventPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\HttpClientPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MailPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PluginPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RouterPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SchedulerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ThemePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\StopwatchPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AdminPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AjaxPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AssetPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ContainerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\FeedPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SecurityPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ShortcodePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WidgetPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ToolbarRendererTest extends TestCase
{
    private ToolbarRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ToolbarRenderer();
        $this->renderer->addPanelRenderer(new DatabasePanelRenderer());
        $this->renderer->addPanelRenderer(new StopwatchPanelRenderer());
        $this->renderer->addPanelRenderer(new MemoryPanelRenderer());
        $this->renderer->addPanelRenderer(new RequestPanelRenderer());
        $this->renderer->addPanelRenderer(new CachePanelRenderer());
        $this->renderer->addPanelRenderer(new WordPressPanelRenderer());
        $this->renderer->addPanelRenderer(new SecurityPanelRenderer());
        $this->renderer->addPanelRenderer(new MailPanelRenderer());
        $this->renderer->addPanelRenderer(new EventPanelRenderer());
        $this->renderer->addPanelRenderer(new LoggerPanelRenderer());
        $this->renderer->addPanelRenderer(new RouterPanelRenderer());
        $this->renderer->addPanelRenderer(new HttpClientPanelRenderer());
        $this->renderer->addPanelRenderer(new TranslationPanelRenderer());
        $this->renderer->addPanelRenderer(new DumpPanelRenderer());
        $this->renderer->addPanelRenderer(new PluginPanelRenderer());
        $this->renderer->addPanelRenderer(new ThemePanelRenderer());
        $this->renderer->addPanelRenderer(new SchedulerPanelRenderer());
        $this->renderer->addPanelRenderer(new WidgetPanelRenderer());
        $this->renderer->addPanelRenderer(new ShortcodePanelRenderer());
        $this->renderer->addPanelRenderer(new AssetPanelRenderer());
        $this->renderer->addPanelRenderer(new RestPanelRenderer());
        $this->renderer->addPanelRenderer(new AjaxPanelRenderer());
        $this->renderer->addPanelRenderer(new AdminPanelRenderer());
        $this->renderer->addPanelRenderer(new ContainerPanelRenderer());
        $this->renderer->addPanelRenderer(new FeedPanelRenderer());
    }

    #[Test]
    public function renderOutputContainsWppackDebugDivId(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wppack-debug"', $html);
    }

    #[Test]
    public function renderOutputContainsStyleTagWithCss(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('</style>', $html);
        // Verify it contains actual CSS rules
        self::assertStringContainsString('#wppack-debug', $html);
    }

    #[Test]
    public function renderOutputContainsScriptTagWithJs(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('<script>', $html);
        self::assertStringContainsString('</script>', $html);
    }

    #[Test]
    public function renderOutputContainsBadgeForEachCollector(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '12.3 MB', 'green'));
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '150 ms', 'yellow'));
        $profile->addCollector($this->createCollector('database', 'Database', '25', 'default'));

        $html = $this->renderer->render($profile);

        // Each collector should have a badge button with data-panel attribute
        self::assertStringContainsString('data-panel="memory"', $html);
        self::assertStringContainsString('data-panel="stopwatch"', $html);
        self::assertStringContainsString('data-panel="database"', $html);
    }

    #[Test]
    public function renderPanelsContainCollectorLabels(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '8 MB', 'green'));
        $profile->addCollector($this->createCollector('request', 'Request', 'GET 200', 'default'));

        $html = $this->renderer->render($profile);

        // Panels should contain the collector labels
        self::assertStringContainsString('Memory', $html);
        self::assertStringContainsString('Request', $html);
    }

    #[Test]
    public function renderOutputIsProperlyEscaped(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector(
            'test',
            'Test <script>alert("xss")</script>',
            '<img onerror=alert(1)>',
            'default',
        ));

        $html = $this->renderer->render($profile);

        // Raw HTML tags should not appear - they should be escaped
        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringNotContainsString('<img onerror=alert(1)>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function renderOutputContainsBadgeValues(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow'));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('42.5 MB', $html);
    }

    #[Test]
    public function renderOutputContainsPanelIdsForEachCollector(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('cache', 'Cache', '95%', 'green'));
        $profile->addCollector($this->createCollector('wordpress', 'WordPress', '6.4', 'default'));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-pc-cache"', $html);
        self::assertStringContainsString('id="wpd-pc-wordpress"', $html);
    }

    #[Test]
    public function renderOutputContainsPerformanceBadge(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('data-panel="performance"', $html);
    }

    #[Test]
    public function renderOutputContainsPerformancePanel(): void
    {
        $profile = $this->createProfileWithCollectors();

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-pc-performance"', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsOverviewCards(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'phases' => ['muplugins_loaded' => 20.0, 'plugins_loaded' => 45.0, 'init' => 80.0, 'wp_loaded' => 120.0, 'template_redirect' => 198.0],
            'events' => [],
        ]));
        $profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow', [
            'peak' => 44564480,
            'limit' => 268435456,
            'usage_percentage' => 16.6,
        ]));
        $profile->addCollector($this->createCollector('database', 'Database', '24', 'default', [
            'total_count' => 24,
            'total_time' => 35.0,
            'queries' => [],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Total Time', $html);
        self::assertStringContainsString('Peak Memory', $html);
        self::assertStringContainsString('Database', $html);
        self::assertStringContainsString('24<span class="wpd-perf-card-unit">queries</span>', $html);
        self::assertStringContainsString('Cache Hit Rate', $html);
        self::assertStringContainsString('HTTP Client', $html);
        self::assertStringContainsString('Hook Firings', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsTimelineLabel(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'request_time_float' => microtime(true) - 0.198,
            'phases' => ['muplugins_loaded' => 20.0],
            'events' => [
                'my_event' => ['name' => 'my_event', 'category' => 'default', 'duration' => 10.0, 'memory' => 1024, 'start_time' => 0.0, 'end_time' => 10.0],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Timeline', $html);
        // Lifecycle phases and custom events appear in unified timeline
        self::assertStringContainsString('muplugins_loaded', $html);
        self::assertStringContainsString('my_event', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsDbAndCacheInTimeline(): void
    {
        $requestTimeFloat = microtime(true) - 0.198;
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'request_time_float' => $requestTimeFloat,
            'phases' => ['muplugins_loaded' => 20.0],
            'events' => [],
        ]));
        $profile->addCollector($this->createCollector('database', 'Database', '2', 'green', [
            'total_count' => 2,
            'total_time' => 3.0,
            'queries' => [
                ['sql' => 'SELECT * FROM wp_posts', 'time' => 1.5, 'caller' => 'test', 'start' => $requestTimeFloat + 0.030, 'data' => []],
            ],
        ]));
        $profile->addCollector($this->createCollector('cache', 'Cache', '90%', 'green', [
            'hit_rate' => 90.0,
            'transient_operations' => [
                ['name' => 'my_key', 'operation' => 'set', 'expiration' => 3600, 'caller' => 'test', 'time' => 95.0],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Timeline', $html);
        // DB queries aggregated into single row
        self::assertStringContainsString('Database (1 queries)', $html);
        // Transient operations aggregated into single row
        self::assertStringContainsString('Cache (1 ops)', $html);
    }

    #[Test]
    public function renderDatabasePanelShowsCallerGrouping(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('database', 'Database', '4', 'green', [
            'total_count' => 4,
            'total_time' => 10.0,
            'queries' => [
                ['sql' => 'SELECT 1', 'time' => 1.0, 'caller' => 'wp_load_alloptions'],
                ['sql' => 'SELECT 2', 'time' => 2.0, 'caller' => 'wp_load_alloptions'],
                ['sql' => 'SELECT 3', 'time' => 3.0, 'caller' => 'WP_Post::get_instance'],
                ['sql' => 'SELECT 4', 'time' => 4.0, 'caller' => 'get_terms'],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Queries by Caller', $html);
        self::assertStringContainsString('wp_load_alloptions', $html);
        self::assertStringContainsString('Avg Time', $html);
    }

    #[Test]
    public function renderCachePanelShowsTransientOperations(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('cache', 'Cache', '90.0%', 'green', [
            'hits' => 100,
            'misses' => 10,
            'hit_rate' => 90.0,
            'transient_sets' => 2,
            'transient_deletes' => 1,
            'object_cache_dropin' => 'Redis',
            'transient_operations' => [
                ['name' => 'my_cache', 'operation' => 'set', 'expiration' => 3600, 'caller' => 'MyPlugin::refresh'],
                ['name' => 'api_data', 'operation' => 'set', 'expiration' => 86400, 'caller' => 'fetch_api'],
                ['name' => 'old_key', 'operation' => 'delete', 'expiration' => 0, 'caller' => 'MyPlugin::clear'],
            ],
            'cache_groups' => ['options' => 50, 'posts' => 20],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Drop-in', $html);
        self::assertStringContainsString('Redis', $html);
        self::assertStringContainsString('my_cache', $html);
        self::assertStringContainsString('SET', $html);
        self::assertStringContainsString('DELETE', $html);
        self::assertStringContainsString('3600 s', $html);
        self::assertStringContainsString('Cache Groups', $html);
        self::assertStringContainsString('options', $html);
    }

    #[Test]
    public function renderCachePanelFallsBackToCountsWhenNoOperations(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('cache', 'Cache', '90.0%', 'green', [
            'hits' => 100,
            'misses' => 10,
            'hit_rate' => 90.0,
            'transient_sets' => 5,
            'transient_deletes' => 2,
            'transient_operations' => [],
            'cache_groups' => [],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Transient Sets', $html);
        self::assertStringContainsString('Transient Deletes', $html);
        self::assertStringNotContainsString('Cache Groups', $html);
    }

    #[Test]
    public function renderPerformancePanelHandlesMissingCollectors(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '50 ms', 'green', [
            'total_time' => 50.0,
            'phases' => [],
            'events' => [],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-pc-performance"', $html);
        self::assertStringContainsString('Total Time', $html);
        self::assertStringContainsString('N/A', $html);
    }

    #[Test]
    public function renderPluginPanelShowsPluginData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('plugin', 'Plugins', '3', 'green', [
            'total_plugins' => 3,
            'total_hook_time' => 35.8,
            'slowest_plugin' => 'woocommerce/woocommerce.php',
            'plugins' => [
                'woocommerce/woocommerce.php' => [
                    'name' => 'WooCommerce',
                    'version' => '8.5.0',
                    'load_time' => 12.5,
                    'hook_count' => 5,
                    'listener_count' => 13,
                    'hook_time' => 23.5,
                    'query_count' => 8,
                    'query_time' => 5.3,
                    'enqueued_styles' => ['woocommerce-layout'],
                    'enqueued_scripts' => ['wc-cart-fragments'],
                    'hooks' => [
                        ['hook' => 'init', 'listeners' => 3, 'time' => 8.2],
                    ],
                ],
                'loader.php' => [
                    'name' => 'Custom Loader',
                    'version' => '1.0.0',
                    'load_time' => 1.2,
                    'is_mu' => true,
                    'hook_count' => 1,
                    'listener_count' => 2,
                    'hook_time' => 0.8,
                    'query_count' => 0,
                    'query_time' => 0.0,
                    'enqueued_styles' => [],
                    'enqueued_scripts' => [],
                    'hooks' => [
                        ['hook' => 'muplugins_loaded', 'listeners' => 2, 'time' => 0.8],
                    ],
                ],
            ],
            'mu_plugins' => ['loader.php'],
            'dropins' => ['object-cache.php'],
            'load_order' => ['woocommerce/woocommerce.php'],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-pc-plugin"', $html);
        self::assertStringContainsString('data-panel="plugin"', $html);
        // List view
        self::assertStringContainsString('wpd-plugin-list', $html);
        self::assertStringContainsString('wpd-plugin-detail-link', $html);
        self::assertStringContainsString('WooCommerce', $html);
        // MU plugin in separate section
        self::assertStringContainsString('Custom Loader', $html);
        self::assertStringContainsString('Must-Use Plugins', $html);
        // Drop-ins
        self::assertStringContainsString('Drop-ins', $html);
        self::assertStringContainsString('object-cache.php', $html);
        // Detail view
        self::assertStringContainsString('wpd-plugin-detail', $html);
        self::assertStringContainsString('data-action="plugin-back"', $html);
        self::assertStringContainsString('Plugin Info', $html);
        self::assertStringContainsString('MU Plugin Info', $html);
        self::assertStringContainsString('Hook Breakdown', $html);
        self::assertStringContainsString('Enqueued Assets', $html);
        self::assertStringContainsString('woocommerce-layout', $html);
        self::assertStringContainsString('wc-cart-fragments', $html);
    }

    #[Test]
    public function renderThemePanelShowsThemeData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('theme', 'Theme', '', 'default', [
            'name' => 'Twenty Twenty-Four',
            'version' => '1.2',
            'is_child_theme' => false,
            'is_block_theme' => true,
            'setup_time' => 5.2,
            'render_time' => 35.0,
            'hook_count' => 4,
            'listener_count' => 12,
            'hook_time' => 12.0,
            'template_file' => '/var/www/html/wp-content/themes/flavor/single.php',
            'template_parts' => ['header', 'footer'],
            'body_classes' => ['single', 'logged-in'],
            'conditional_tags' => ['is_single' => true, 'is_page' => false],
            'enqueued_styles' => ['theme-style'],
            'enqueued_scripts' => ['jquery'],
            'hooks' => [
                ['hook' => 'wp_head', 'listeners' => 5, 'time' => 6.5],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-pc-theme"', $html);
        self::assertStringContainsString('data-panel="theme"', $html);
        self::assertStringContainsString('Twenty Twenty-Four', $html);
        self::assertStringContainsString('Setup Time', $html);
        self::assertStringContainsString('Render Time', $html);
        self::assertStringContainsString('Hook Breakdown', $html);
        self::assertStringContainsString('Conditional Tags', $html);
        self::assertStringContainsString('is_single', $html);
        self::assertStringContainsString('Enqueued Assets', $html);
        self::assertStringContainsString('theme-style', $html);
    }

    #[Test]
    public function renderSchedulerPanelShowsCronData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('scheduler', 'Scheduler', '3', 'green', [
            'cron_total' => 3,
            'cron_overdue' => 1,
            'action_scheduler_available' => true,
            'action_scheduler_version' => '3.7.0',
            'as_pending' => 5,
            'as_failed' => 1,
            'as_complete' => 120,
            'as_recent_actions' => [],
            'cron_disabled' => false,
            'alternate_cron' => false,
            'cron_events' => [
                ['hook' => 'wp_scheduled_delete', 'schedule' => 'daily', 'next_run' => time() + 3600, 'next_run_relative' => 'in 1 hour', 'is_overdue' => false, 'callbacks' => 1],
                ['hook' => 'expired_event', 'schedule' => 'hourly', 'next_run' => time() - 600, 'next_run_relative' => '10 minutes ago', 'is_overdue' => true, 'callbacks' => 1],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('id="wpd-pc-scheduler"', $html);
        self::assertStringContainsString('data-panel="scheduler"', $html);
        self::assertStringContainsString('WP-Cron Events', $html);
        self::assertStringContainsString('wp_scheduled_delete', $html);
        self::assertStringContainsString('OVERDUE', $html);
        self::assertStringContainsString('Action Scheduler', $html);
        self::assertStringContainsString('DISABLE_WP_CRON', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsPluginTimelineEntries(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'request_time_float' => microtime(true) - 0.198,
            'phases' => ['muplugins_loaded' => 20.0],
            'events' => [],
        ]));
        $profile->addCollector($this->createCollector('event', 'Event', '100', 'green', [
            'hook_timings' => [
                'init' => ['count' => 10, 'total_time' => 15.0, 'start' => 57.9],
            ],
        ]));
        $profile->addCollector($this->createCollector('plugin', 'Plugins', '1', 'green', [
            'plugins' => [
                'test/test.php' => [
                    'name' => 'TestPlugin',
                    'hook_time' => 10.0,
                    'hooks' => [
                        ['hook' => 'init', 'listeners' => 3, 'time' => 10.0],
                    ],
                ],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Plugins', $html);
        self::assertStringContainsString('TestPlugin', $html);
    }

    #[Test]
    public function renderLoggerPanelShowsFilterTabs(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('logger', 'Logs', '5', 'red', [
            'total_count' => 5,
            'error_count' => 1,
            'deprecation_count' => 2,
            'level_counts' => ['error' => 1, 'deprecation' => 2, 'info' => 1, 'debug' => 1],
            'logs' => [
                ['level' => 'error', 'message' => 'Error message', 'context' => ['key' => 'value'], 'channel' => 'app', 'file' => '/path/to/File.php', 'line' => 42],
                ['level' => 'deprecation', 'message' => 'Deprecated function', 'context' => [], 'channel' => 'php', 'file' => '/path/to/legacy.php', 'line' => 10],
                ['level' => 'deprecation', 'message' => 'Deprecated hook', 'context' => [], 'channel' => 'wordpress', 'file' => '', 'line' => 0],
                ['level' => 'info', 'message' => 'Info message', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'debug', 'message' => 'Debug message', 'context' => [], 'channel' => 'routing', 'file' => '', 'line' => 0],
            ],
        ]));

        $html = $this->renderer->render($profile);

        // Filter tabs
        self::assertStringContainsString('data-log-filter="all"', $html);
        self::assertStringContainsString('data-log-filter="error"', $html);
        self::assertStringContainsString('data-log-filter="deprecation"', $html);
        self::assertStringContainsString('data-log-filter="warning"', $html);
        self::assertStringContainsString('data-log-filter="info"', $html);
        self::assertStringContainsString('data-log-filter="debug"', $html);

        // data-log-level attributes on rows
        self::assertStringContainsString('data-log-level="error"', $html);
        self::assertStringContainsString('data-log-level="deprecation"', $html);
        self::assertStringContainsString('data-log-level="info"', $html);
        self::assertStringContainsString('data-log-level="debug"', $html);

        // File column
        self::assertStringContainsString('File.php:42', $html);

        // Context row (expandable)
        self::assertStringContainsString('wpd-log-context', $html);

        // Deprecation count in summary
        self::assertStringContainsString('Deprecations', $html);
    }

    #[Test]
    public function renderAdminPanelShowsAdminData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('admin', 'Admin', '5', 'default', [
            'is_admin' => true,
            'page_hook' => 'toplevel_page_my-plugin',
            'screen' => ['id' => 'toplevel_page_my-plugin', 'base' => 'toplevel_page_my-plugin', 'post_type' => '', 'taxonomy' => ''],
            'admin_menus' => [
                ['title' => 'Dashboard', 'slug' => 'index.php', 'capability' => 'read', 'submenu' => [
                    ['title' => 'Home', 'slug' => 'index.php'],
                ]],
            ],
            'admin_bar_nodes' => [
                ['id' => 'wp-logo', 'title' => 'About WordPress'],
            ],
            'total_menus' => 5,
            'total_submenus' => 12,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Current Screen', $html);
        self::assertStringContainsString('toplevel_page_my-plugin', $html);
        self::assertStringContainsString('Admin Menus', $html);
        self::assertStringContainsString('Dashboard', $html);
        self::assertStringContainsString('Home', $html);
        self::assertStringContainsString('Admin Bar Nodes', $html);
        self::assertStringContainsString('wp-logo', $html);
    }

    #[Test]
    public function renderAdminPanelShowsNonAdminMessage(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('admin', 'Admin', '-', 'default', [
            'is_admin' => false,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Not in admin context.', $html);
    }

    #[Test]
    public function renderAjaxPanelShowsActions(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('ajax', 'Ajax', '3', 'default', [
            'total_actions' => 3,
            'nopriv_count' => 1,
            'registered_actions' => [
                'heartbeat' => ['callback' => 'wp_ajax_heartbeat', 'nopriv' => false],
                'my_action' => ['callback' => 'MyPlugin::handle', 'nopriv' => true],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Registered Actions', $html);
        self::assertStringContainsString('heartbeat', $html);
        self::assertStringContainsString('wp_ajax_heartbeat', $html);
        self::assertStringContainsString('my_action', $html);
        self::assertStringContainsString('NoPriv', $html);
        self::assertStringContainsString('Client-Side Requests', $html);
    }

    #[Test]
    public function renderAssetPanelShowsScriptsAndStyles(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('asset', 'Assets', '5/3', 'default', [
            'enqueued_scripts' => 5,
            'enqueued_styles' => 3,
            'registered_scripts' => 20,
            'registered_styles' => 15,
            'scripts' => [
                'jquery-core' => ['enqueued' => true, 'src' => '/wp-includes/js/jquery/jquery.min.js', 'version' => '3.7.1', 'in_footer' => false],
            ],
            'styles' => [
                'wp-block-library' => ['enqueued' => true, 'src' => '/wp-includes/css/dist/block-library/style.min.css', 'version' => '6.4', 'media' => 'all'],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Enqueued Scripts', $html);
        self::assertStringContainsString('jquery-core', $html);
        self::assertStringContainsString('3.7.1', $html);
        self::assertStringContainsString('Enqueued Styles', $html);
        self::assertStringContainsString('wp-block-library', $html);
        self::assertStringContainsString('all', $html);
    }

    #[Test]
    public function renderContainerPanelShowsServices(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('container', 'Container', '10', 'default', [
            'service_count' => 10,
            'public_count' => 4,
            'private_count' => 6,
            'autowired_count' => 8,
            'lazy_count' => 2,
            'services' => [
                'app.mailer' => ['class' => 'App\\Mailer', 'public' => true, 'autowired' => true, 'lazy' => false],
            ],
            'compiler_passes' => ['App\\DI\\MyCompilerPass'],
            'tagged_services' => [
                'event.listener' => ['app.listener.foo', 'app.listener.bar'],
            ],
            'parameters' => ['app.debug' => true],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Services', $html);
        self::assertStringContainsString('app.mailer', $html);
        self::assertStringContainsString('public', $html);
        self::assertStringContainsString('autowired', $html);
        self::assertStringContainsString('Compiler Passes', $html);
        self::assertStringContainsString('MyCompilerPass', $html);
        self::assertStringContainsString('Tagged Services', $html);
        self::assertStringContainsString('event.listener', $html);
        self::assertStringContainsString('Parameters', $html);
    }

    #[Test]
    public function renderDumpPanelShowsDumps(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('dump', 'Dumps', '2', 'yellow', [
            'dumps' => [
                ['file' => '/app/src/Controller.php', 'line' => 42, 'data' => 'string(5) "hello"'],
                ['file' => '/app/src/Service.php', 'line' => 100, 'data' => 'int(42)'],
            ],
            'total_count' => 2,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Dumps (2)', $html);
        self::assertStringContainsString('Controller.php', $html);
        self::assertStringContainsString(':42', $html);
        self::assertStringContainsString('string(5) &quot;hello&quot;', $html);
    }

    #[Test]
    public function renderDumpPanelShowsEmptyMessage(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('dump', 'Dumps', '0', 'default', [
            'dumps' => [],
            'total_count' => 0,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('No dump() calls recorded.', $html);
    }

    #[Test]
    public function renderFeedPanelShowsFeeds(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('feed', 'Feed', '3', 'default', [
            'total_count' => 3,
            'custom_count' => 1,
            'feed_discovery' => true,
            'feeds' => [
                ['type' => 'rss2', 'url' => 'https://example.com/feed/', 'is_custom' => false],
                ['type' => 'atom', 'url' => 'https://example.com/feed/atom/', 'is_custom' => false],
                ['type' => 'rss2', 'url' => 'https://example.com/custom-feed/', 'is_custom' => true],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Total Feeds', $html);
        self::assertStringContainsString('Custom Feeds', $html);
        self::assertStringContainsString('Feed Discovery', $html);
        self::assertStringContainsString('rss2', $html);
        self::assertStringContainsString('atom', $html);
        self::assertStringContainsString('example.com/feed/', $html);
    }

    #[Test]
    public function renderHttpClientPanelShowsRequests(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('http_client', 'HTTP Client', '2', 'green', [
            'total_count' => 2,
            'total_time' => 350.5,
            'error_count' => 1,
            'slow_count' => 0,
            'requests' => [
                ['method' => 'GET', 'url' => 'https://api.example.com/data', 'status_code' => 200, 'duration' => 150.2, 'response_size' => 4096, 'start' => 0, 'error' => ''],
                ['method' => 'POST', 'url' => 'https://api.example.com/submit', 'status_code' => 500, 'duration' => 200.3, 'response_size' => 128, 'start' => 0, 'error' => 'Internal Server Error'],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Total Requests', $html);
        self::assertStringContainsString('Errors', $html);
        self::assertStringContainsString('Slow Requests', $html);
        self::assertStringContainsString('api.example.com/data', $html);
        self::assertStringContainsString('200', $html);
        self::assertStringContainsString('500', $html);
        self::assertStringContainsString('Internal Server Error', $html);
    }

    #[Test]
    public function renderMailPanelShowsEmails(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('mail', 'Mail', '2', 'default', [
            'total_count' => 2,
            'success_count' => 1,
            'failure_count' => 1,
            'emails' => [
                [
                    'to' => 'user@example.com',
                    'subject' => 'Welcome Email',
                    'from' => 'noreply@example.com',
                    'cc' => ['cc@example.com'],
                    'bcc' => [],
                    'reply_to' => '',
                    'content_type' => 'text/html',
                    'charset' => 'UTF-8',
                    'status' => 'sent',
                    'error' => '',
                    'message' => 'Hello World',
                    'attachment_details' => [
                        ['filename' => 'report.pdf', 'size' => 102400],
                    ],
                ],
                [
                    'to' => 'admin@example.com',
                    'subject' => 'Alert',
                    'from' => '',
                    'cc' => [],
                    'bcc' => [],
                    'reply_to' => '',
                    'content_type' => '',
                    'charset' => '',
                    'status' => 'failed',
                    'error' => 'SMTP connection failed',
                    'message' => '',
                    'attachment_details' => [],
                ],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Email #1', $html);
        self::assertStringContainsString('Email #2', $html);
        self::assertStringContainsString('user@example.com', $html);
        self::assertStringContainsString('Welcome Email', $html);
        self::assertStringContainsString('noreply@example.com', $html);
        self::assertStringContainsString('cc@example.com', $html);
        self::assertStringContainsString('SENT', $html);
        self::assertStringContainsString('FAILED', $html);
        self::assertStringContainsString('SMTP connection failed', $html);
        self::assertStringContainsString('Hello World', $html);
        self::assertStringContainsString('report.pdf', $html);
    }

    #[Test]
    public function renderRestPanelShowsRoutes(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('rest', 'REST', '25', 'default', [
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'GET',
                'route' => '/wp/v2/posts',
                'path' => '/wp-json/wp/v2/posts',
                'namespace' => 'wp/v2',
                'callback' => 'WP_REST_Posts_Controller::get_items',
                'status' => 200,
                'authentication' => 'nonce',
                'params' => ['per_page' => 10],
            ],
            'total_routes' => 25,
            'total_namespaces' => 3,
            'routes' => [
                'wp/v2' => [
                    ['route' => '/wp/v2/posts', 'methods' => ['GET', 'POST'], 'callback' => 'WP_REST_Posts_Controller'],
                ],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Current Request', $html);
        self::assertStringContainsString('/wp/v2/posts', $html);
        self::assertStringContainsString('WP_REST_Posts_Controller::get_items', $html);
        self::assertStringContainsString('Request Parameters', $html);
        self::assertStringContainsString('per_page', $html);
        self::assertStringContainsString('wp/v2', $html);
        self::assertStringContainsString('GET', $html);
        self::assertStringContainsString('POST', $html);
    }

    #[Test]
    public function renderRouterPanelShowsClassicTemplate(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('router', 'Router', '', 'default', [
            'is_block_theme' => false,
            'template' => 'single.php',
            'template_path' => '/var/www/html/wp-content/themes/flavor/single.php',
            'matched_rule' => '([^/]+)(?:/([0-9]+))?/?$',
            'matched_query' => 'name=hello-world&page=',
            'query_type' => 'single',
            'is_404' => false,
            'rewrite_rules_count' => 120,
            'query_vars' => ['name' => 'hello-world'],
            'is_singular' => true,
            'is_front_page' => false,
            'is_archive' => false,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Template (Classic)', $html);
        self::assertStringContainsString('single.php', $html);
        self::assertStringContainsString('Matched Rule', $html);
        self::assertStringContainsString('Query Variables', $html);
        self::assertStringContainsString('hello-world', $html);
        self::assertStringContainsString('Conditional Tags', $html);
        self::assertStringContainsString('is_singular', $html);
    }

    #[Test]
    public function renderRouterPanelShowsBlockTemplate(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('router', 'Router', '', 'default', [
            'is_block_theme' => true,
            'template' => '',
            'template_path' => '',
            'matched_rule' => '',
            'matched_query' => '',
            'query_type' => 'front_page',
            'is_404' => false,
            'rewrite_rules_count' => 120,
            'block_template' => [
                'slug' => 'front-page',
                'id' => 'flavor//front-page',
                'source' => 'theme',
                'has_theme_file' => true,
                'file_path' => '/var/www/html/wp-content/themes/flavor/templates/front-page.html',
                'parts' => [
                    ['slug' => 'header', 'area' => 'header', 'source' => 'theme'],
                    ['slug' => 'footer', 'area' => 'footer', 'source' => 'custom'],
                ],
            ],
            'is_front_page' => true,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Block Template (FSE)', $html);
        self::assertStringContainsString('front-page', $html);
        self::assertStringContainsString('flavor//front-page', $html);
        self::assertStringContainsString('Template Parts', $html);
        self::assertStringContainsString('header', $html);
        self::assertStringContainsString('footer', $html);
        self::assertStringContainsString('is_front_page', $html);
    }

    #[Test]
    public function renderSecurityPanelShowsUserAndNonces(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('security', 'Security', '', 'default', [
            'is_logged_in' => true,
            'username' => 'admin',
            'display_name' => 'Administrator',
            'email' => 'admin@example.com',
            'roles' => ['administrator', 'editor'],
            'is_super_admin' => false,
            'authentication' => 'cookie',
            'capabilities' => ['manage_options' => true, 'edit_posts' => true],
            'nonce_operations' => [
                ['action' => 'wp_rest', 'operation' => 'verify', 'result' => true, 'timestamp' => 0.0],
            ],
            'nonce_verify_count' => 1,
            'nonce_verify_failures' => 0,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('User', $html);
        self::assertStringContainsString('admin', $html);
        self::assertStringContainsString('Roles', $html);
        self::assertStringContainsString('administrator', $html);
        self::assertStringContainsString('editor', $html);
        self::assertStringContainsString('Capabilities', $html);
        self::assertStringContainsString('manage_options', $html);
        self::assertStringContainsString('Nonce Operations', $html);
        self::assertStringContainsString('wp_rest', $html);
    }

    #[Test]
    public function renderShortcodePanelShowsShortcodes(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('shortcode', 'Shortcode', '5/2', 'default', [
            'total_count' => 5,
            'used_count' => 2,
            'execution_time' => 15.3,
            'used_shortcodes' => ['gallery', 'contact-form'],
            'shortcodes' => [
                ['tag' => 'gallery', 'callback' => 'gallery_shortcode', 'used' => true],
                ['tag' => 'contact-form', 'callback' => 'cf_shortcode', 'used' => true],
                ['tag' => 'caption', 'callback' => 'img_caption_shortcode', 'used' => false],
            ],
            'executions' => [
                ['tag' => 'gallery', 'start' => 0.0, 'duration' => 10.2],
                ['tag' => 'contact-form', 'start' => 0.0, 'duration' => 5.1],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Execution Times', $html);
        self::assertStringContainsString('[gallery]', $html);
        self::assertStringContainsString('[contact-form]', $html);
        self::assertStringContainsString('Used in Current Page', $html);
        self::assertStringContainsString('All Shortcodes', $html);
        self::assertStringContainsString('gallery_shortcode', $html);
    }

    #[Test]
    public function renderTranslationPanelShowsTranslations(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('translation', 'Translation', '150', 'default', [
            'total_lookups' => 150,
            'missing_count' => 2,
            'loaded_domains' => ['default', 'woocommerce', 'my-plugin'],
            'domain_usage' => ['default' => 80, 'woocommerce' => 50, 'my-plugin' => 20],
            'missing_translations' => [
                ['original' => 'Untranslated text', 'domain' => 'my-plugin'],
                ['original' => 'Another missing', 'domain' => 'my-plugin'],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Total Lookups', $html);
        self::assertStringContainsString('Loaded Domains', $html);
        self::assertStringContainsString('woocommerce', $html);
        self::assertStringContainsString('Domain Usage', $html);
        self::assertStringContainsString('Missing Translations', $html);
        self::assertStringContainsString('Untranslated text', $html);
    }

    #[Test]
    public function renderWidgetPanelShowsWidgets(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('widget', 'Widget', '8', 'default', [
            'total_widgets' => 8,
            'total_sidebars' => 3,
            'active_widgets' => 6,
            'render_time' => 12.5,
            'sidebars' => [
                'sidebar-1' => ['name' => 'Main Sidebar', 'widgets' => ['recent-posts-2', 'search-1']],
                'sidebar-2' => ['name' => 'Footer', 'widgets' => []],
            ],
            'sidebar_timings' => [
                ['sidebar' => 'sidebar-1', 'name' => 'Main Sidebar', 'start' => 0.0, 'duration' => 8.3],
                ['sidebar' => 'sidebar-2', 'name' => 'Footer', 'start' => 0.0, 'duration' => 4.2],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Total Sidebars', $html);
        self::assertStringContainsString('Total Widgets', $html);
        self::assertStringContainsString('Sidebar Render Times', $html);
        self::assertStringContainsString('Main Sidebar', $html);
        self::assertStringContainsString('recent-posts-2', $html);
        self::assertStringContainsString('empty', $html);
    }

    #[Test]
    public function renderWordPressPanelShowsWpData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('wordpress', 'WordPress', '6.4', 'default', [
            'wp_version' => '6.4.2',
            'php_version' => '8.2.13',
            'environment_type' => 'local',
            'is_multisite' => false,
            'constants' => ['WP_DEBUG' => true, 'WP_DEBUG_LOG' => false, 'SCRIPT_DEBUG' => null],
            'theme' => 'Twenty Twenty-Four',
            'is_block_theme' => true,
            'is_child_theme' => false,
            'parent_theme' => '',
            'theme_version' => '1.2',
            'mu_plugins' => ['loader.php'],
            'active_plugins' => ['woocommerce/woocommerce.php', 'akismet/akismet.php'],
            'extensions' => ['curl', 'mbstring', 'openssl'],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('6.4.2', $html);
        self::assertStringContainsString('8.2.13', $html);
        self::assertStringContainsString('local', $html);
        self::assertStringContainsString('Debug Constants', $html);
        self::assertStringContainsString('WP_DEBUG', $html);
        self::assertStringContainsString('Active Theme', $html);
        self::assertStringContainsString('Twenty Twenty-Four', $html);
        self::assertStringContainsString('Must-Use Plugins', $html);
        self::assertStringContainsString('Active Plugins', $html);
        self::assertStringContainsString('PHP Extensions', $html);
        self::assertStringContainsString('curl', $html);
    }

    #[Test]
    public function renderMemoryPanelShowsSnapshots(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow', [
            'current' => 20971520,
            'peak' => 44564480,
            'limit' => 268435456,
            'usage_percentage' => 16.6,
            'snapshots' => [
                'plugins_loaded' => 10485760,
                'init' => 15728640,
                'template_redirect' => 20971520,
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Current Usage', $html);
        self::assertStringContainsString('Peak Usage', $html);
        self::assertStringContainsString('Memory Limit', $html);
        self::assertStringContainsString('Memory Snapshots', $html);
        self::assertStringContainsString('plugins_loaded', $html);
        self::assertStringContainsString('init', $html);
        self::assertStringContainsString('template_redirect', $html);
    }

    #[Test]
    public function renderRequestPanelShowsRequestData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('request', 'Request', 'GET 200', 'green', [
            'method' => 'GET',
            'url' => 'https://example.com/hello-world/',
            'status_code' => 200,
            'content_type' => 'text/html; charset=UTF-8',
            'request_headers' => ['Host' => 'example.com', 'Accept' => 'text/html'],
            'response_headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
            'get_params' => ['s' => 'search query'],
            'post_params' => [],
            'cookies' => ['wp_lang' => 'en_US'],
            'server_vars' => ['SCRIPT_FILENAME' => '/var/www/html/index.php', 'REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME_FLOAT' => microtime(true), 'SERVER_SOFTWARE' => 'Apache/2.4'],
            'http_api_calls' => [],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Request', $html);
        self::assertStringContainsString('GET', $html);
        self::assertStringContainsString('example.com/hello-world/', $html);
        self::assertStringContainsString('200', $html);
        self::assertStringContainsString('Response', $html);
        self::assertStringContainsString('Request Headers', $html);
        self::assertStringContainsString('Response Headers', $html);
        self::assertStringContainsString('GET Parameters', $html);
        self::assertStringContainsString('search query', $html);
        self::assertStringContainsString('Cookies', $html);
        self::assertStringContainsString('Server Variables', $html);
    }

    #[Test]
    public function renderRequestPanelShowsPostParamsAndHttpApiCalls(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('request', 'Request', 'POST 302', 'yellow', [
            'method' => 'POST',
            'url' => 'https://example.com/wp-admin/post.php',
            'status_code' => 302,
            'content_type' => '',
            'request_headers' => [],
            'response_headers' => [],
            'get_params' => [],
            'post_params' => ['action' => 'editpost', 'post_ID' => '42'],
            'cookies' => [],
            'server_vars' => [],
            'http_api_calls' => [
                ['url' => 'https://api.wordpress.org/plugins/info/', 'args' => [], 'response' => ''],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('POST', $html);
        self::assertStringContainsString('POST Parameters', $html);
        self::assertStringContainsString('editpost', $html);
        self::assertStringContainsString('HTTP API Calls', $html);
        self::assertStringContainsString('api.wordpress.org/plugins/info/', $html);
    }

    #[Test]
    public function renderRestPanelShowsNonRestSummary(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('rest', 'REST', '25', 'default', [
            'is_rest_request' => false,
            'current_request' => null,
            'total_routes' => 25,
            'total_namespaces' => 3,
            'routes' => [],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('REST Request', $html);
        self::assertStringContainsString('false', $html);
        self::assertStringNotContainsString('Current Request', $html);
    }

    #[Test]
    public function renderDatabasePanelShowsSlowAndDuplicateQueries(): void
    {
        $requestTimeFloat = microtime(true) - 0.198;
        $profile = new Profile('test-token');
        $queries = [
            ['sql' => 'SELECT * FROM wp_posts WHERE ID = 1', 'time' => 0.5, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.01, 'data' => []],
            ['sql' => 'SELECT * FROM wp_posts WHERE ID = 1', 'time' => 0.3, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.02, 'data' => []],
            ['sql' => 'SELECT * FROM wp_postmeta WHERE post_id = 1', 'time' => 150.0, 'caller' => 'get_post_meta, WP_Query::get_posts', 'start' => $requestTimeFloat + 0.03, 'data' => []],
        ];
        // Add 6 queries from same caller to trigger >5 warning
        for ($i = 0; $i < 4; $i++) {
            $queries[] = ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = "opt' . $i . '"', 'time' => 0.2, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.04 + ($i * 0.001), 'data' => []];
        }
        $profile->addCollector($this->createCollector('database', 'Database', '7', 'yellow', [
            'total_count' => 7,
            'total_time' => 152.0,
            'duplicate_count' => 1,
            'slow_count' => 1,
            'suggestions' => ['Consider adding an index on wp_postmeta.meta_key'],
            'queries' => $queries,
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Queries by Caller', $html);
        self::assertStringContainsString('wp_postmeta', $html);
        // Comma-separated caller shows only last entry
        self::assertStringContainsString('WP_Query::get_posts', $html);
        self::assertStringContainsString('Suggestions', $html);
        // Slow query has SLOW badge
        self::assertStringContainsString('SLOW', $html);
        // Duplicate query has DUP badge
        self::assertStringContainsString('DUP', $html);
        self::assertStringContainsString('wpd-row-slow', $html);
        self::assertStringContainsString('wpd-row-duplicate', $html);
    }

    #[Test]
    public function renderEventPanelShowsHookData(): void
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('event', 'Events', '250', 'default', [
            'total_firings' => 250,
            'unique_hooks' => 80,
            'registered_hooks' => 120,
            'orphan_hooks' => 5,
            'top_hooks' => ['init' => 30, 'wp_head' => 20, 'the_content' => 15],
            'hook_timings' => [
                'init' => ['count' => 30, 'total_time' => 45.5, 'start' => 50.0],
                'wp_head' => ['count' => 20, 'total_time' => 22.3, 'start' => 100.0],
            ],
            'listener_counts' => ['init' => 15, 'wp_head' => 25, 'the_content' => 8],
            'component_summary' => [
                'WooCommerce' => ['type' => 'plugin', 'hooks' => 12, 'listeners' => 30, 'total_time' => 25.0],
                'Twenty Twenty-Four' => ['type' => 'theme', 'hooks' => 5, 'listeners' => 10, 'total_time' => 8.0],
            ],
        ]));

        $html = $this->renderer->render($profile);

        self::assertStringContainsString('Total Firings', $html);
        self::assertStringContainsString('Unique Hooks', $html);
        self::assertStringContainsString('Orphan Hooks', $html);
        self::assertStringContainsString('Component Summary', $html);
        self::assertStringContainsString('WooCommerce', $html);
        self::assertStringContainsString('plugin', $html);
        self::assertStringContainsString('Top Hooks', $html);
        self::assertStringContainsString('init', $html);
        self::assertStringContainsString('wp_head', $html);
    }

    private function createProfileWithCollectors(): Profile
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '10 MB', 'green'));
        $profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '120 ms', 'default'));

        return $profile;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCollector(
        string $name,
        string $label,
        string $badgeValue,
        string $badgeColor,
        array $data = [],
    ): DataCollectorInterface {
        return new class ($name, $label, $badgeValue, $badgeColor, $data) implements DataCollectorInterface {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(
                private readonly string $name,
                private readonly string $label,
                private readonly string $badgeValue,
                private readonly string $badgeColor,
                private readonly array $data,
            ) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function collect(): void {}

            public function getData(): array
            {
                return $this->data;
            }

            public function getLabel(): string
            {
                return $this->label;
            }

            public function getBadgeValue(): string
            {
                return $this->badgeValue;
            }

            public function getBadgeColor(): string
            {
                return $this->badgeColor;
            }

            public function reset(): void {}
        };
    }
}
