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
use WpPack\Component\Debug\Toolbar\Panel\TimePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\UserPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ToolbarRendererTest extends TestCase
{
    private ToolbarRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ToolbarRenderer();
        $this->renderer->addPanelRenderer(new DatabasePanelRenderer());
        $this->renderer->addPanelRenderer(new TimePanelRenderer());
        $this->renderer->addPanelRenderer(new MemoryPanelRenderer());
        $this->renderer->addPanelRenderer(new RequestPanelRenderer());
        $this->renderer->addPanelRenderer(new CachePanelRenderer());
        $this->renderer->addPanelRenderer(new WordPressPanelRenderer());
        $this->renderer->addPanelRenderer(new UserPanelRenderer());
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
        $profile->addCollector($this->createCollector('time', 'Time', '150 ms', 'yellow'));
        $profile->addCollector($this->createCollector('database', 'Database', '25', 'default'));

        $html = $this->renderer->render($profile);

        // Each collector should have a badge button with data-panel attribute
        self::assertStringContainsString('data-panel="memory"', $html);
        self::assertStringContainsString('data-panel="time"', $html);
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
        $profile->addCollector($this->createCollector('time', 'Time', '198 ms', 'green', [
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
        $profile->addCollector($this->createCollector('time', 'Time', '198 ms', 'green', [
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
        $profile->addCollector($this->createCollector('time', 'Time', '198 ms', 'green', [
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
        $profile->addCollector($this->createCollector('time', 'Time', '50 ms', 'green', [
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
        // MU plugin in table with MU tag
        self::assertStringContainsString('Custom Loader', $html);
        self::assertStringContainsString('>MU</span>', $html);
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
        $profile->addCollector($this->createCollector('time', 'Time', '198 ms', 'green', [
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

    private function createProfileWithCollectors(): Profile
    {
        $profile = new Profile('test-token');
        $profile->addCollector($this->createCollector('memory', 'Memory', '10 MB', 'green'));
        $profile->addCollector($this->createCollector('time', 'Time', '120 ms', 'default'));

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
