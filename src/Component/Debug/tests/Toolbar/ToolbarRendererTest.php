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

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;
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
use WpPack\Component\Debug\Toolbar\Panel\GenericPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ToolbarRendererTest extends TestCase
{
    private ToolbarRenderer $renderer;
    private Profile $profile;

    protected function setUp(): void
    {
        $this->profile = new Profile();
        $this->renderer = new ToolbarRenderer($this->profile);
        $this->renderer->addPanelRenderer(new DatabasePanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new StopwatchPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new MemoryPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new RequestPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new CachePanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new WordPressPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new SecurityPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new MailPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new EventPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new LoggerPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new RouterPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new HttpClientPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new TranslationPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new DumpPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new PluginPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new ThemePanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new SchedulerPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new WidgetPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new ShortcodePanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new AssetPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new RestPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new AjaxPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new AdminPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new ContainerPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new FeedPanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new PerformancePanelRenderer($this->profile));
        $this->renderer->addPanelRenderer(new EnvironmentPanelRenderer($this->profile));
    }

    #[Test]
    public function renderOutputContainsWppackDebugDivId(): void
    {
        $this->createProfileWithCollectors();

        $html = $this->renderer->render();

        self::assertStringContainsString('id="wppack-debug"', $html);
    }

    #[Test]
    public function renderOutputContainsStyleTagWithCss(): void
    {
        $this->createProfileWithCollectors();

        $html = $this->renderer->render();

        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('</style>', $html);
        // Verify it contains actual CSS rules
        self::assertStringContainsString('#wppack-debug', $html);
    }

    #[Test]
    public function renderOutputContainsScriptTagWithJs(): void
    {
        $this->createProfileWithCollectors();

        $html = $this->renderer->render();

        self::assertStringContainsString('<script>', $html);
        self::assertStringContainsString('</script>', $html);
    }

    #[Test]
    public function renderOutputContainsIndicatorForEachCollector(): void
    {
        $this->profile->addCollector($this->createCollector('memory', 'Memory', '12.3 MB', 'green'));
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '150 ms', 'yellow'));
        $this->profile->addCollector($this->createCollector('database', 'Database', '25', 'default'));

        $html = $this->renderer->render();

        // Each collector should have an indicator button with data-panel attribute
        self::assertStringContainsString('data-panel="memory"', $html);
        self::assertStringContainsString('data-panel="stopwatch"', $html);
        self::assertStringContainsString('data-panel="database"', $html);
    }

    #[Test]
    public function renderPanelsContainCollectorLabels(): void
    {
        $this->profile->addCollector($this->createCollector('memory', 'Memory', '8 MB', 'green'));
        $this->profile->addCollector($this->createCollector('request', 'Request', 'GET 200', 'default'));

        $html = $this->renderer->render();

        // Panels should contain the collector labels
        self::assertStringContainsString('Memory', $html);
        self::assertStringContainsString('Request', $html);
    }

    #[Test]
    public function renderOutputIsProperlyEscaped(): void
    {
        $this->profile->addCollector($this->createCollector(
            'test',
            'Test <script>alert("xss")</script>',
            '<img onerror=alert(1)>',
            'default',
        ));

        $html = $this->renderer->render();

        // Raw HTML tags should not appear - they should be escaped
        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringNotContainsString('<img onerror=alert(1)>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function renderOutputContainsIndicatorValues(): void
    {
        $this->profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow'));

        $html = $this->renderer->render();

        self::assertStringContainsString('42.5 MB', $html);
    }

    #[Test]
    public function renderOutputContainsPanelIdsForEachCollector(): void
    {
        $this->profile->addCollector($this->createCollector('cache', 'Cache', '95%', 'green'));
        $this->profile->addCollector($this->createCollector('wordpress', 'WordPress', '6.4', 'default'));

        $html = $this->renderer->render();

        self::assertStringContainsString('id="wpd-pc-cache"', $html);
        self::assertStringContainsString('id="wpd-pc-wordpress"', $html);
    }

    #[Test]
    public function renderOutputContainsPerformanceIndicator(): void
    {
        $this->createProfileWithCollectors();

        $html = $this->renderer->render();

        self::assertStringContainsString('data-panel="performance"', $html);
    }

    #[Test]
    public function renderOutputContainsPerformancePanel(): void
    {
        $this->createProfileWithCollectors();

        $html = $this->renderer->render();

        self::assertStringContainsString('id="wpd-pc-performance"', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsOverviewCards(): void
    {
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'phases' => ['muplugins_loaded' => 20.0, 'plugins_loaded' => 45.0, 'init' => 80.0, 'wp_loaded' => 120.0, 'template_redirect' => 198.0],
            'events' => [],
        ]));
        $this->profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow', [
            'peak' => 44564480,
            'limit' => 268435456,
            'usage_percentage' => 16.6,
        ]));
        $this->profile->addCollector($this->createCollector('database', 'Database', '24', 'default', [
            'total_count' => 24,
            'total_time' => 35.0,
            'queries' => [],
        ]));

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'request_time_float' => microtime(true) - 0.198,
            'phases' => ['muplugins_loaded' => 20.0],
            'events' => [
                'muplugins_loaded' => ['name' => 'muplugins_loaded', 'category' => 'wordpress', 'duration' => 20.0, 'memory' => 0, 'start_time' => 0.0, 'end_time' => 20.0],
                'my_event' => ['name' => 'my_event', 'category' => 'default', 'duration' => 10.0, 'memory' => 1024, 'start_time' => 0.0, 'end_time' => 10.0],
            ],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Timeline', $html);
        // Lifecycle phases and custom events appear in unified timeline
        self::assertStringContainsString('muplugins_loaded', $html);
        self::assertStringContainsString('my_event', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsDbAndCacheInTimeline(): void
    {
        $requestTimeFloat = microtime(true) - 0.198;
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'request_time_float' => $requestTimeFloat,
            'phases' => ['muplugins_loaded' => 20.0],
            'events' => [],
        ]));
        $this->profile->addCollector($this->createCollector('database', 'Database', '2', 'green', [
            'total_count' => 2,
            'total_time' => 3.0,
            'queries' => [
                ['sql' => 'SELECT * FROM wp_posts', 'time' => 1.5, 'caller' => 'test', 'start' => $requestTimeFloat + 0.030, 'data' => []],
            ],
        ]));
        $this->profile->addCollector($this->createCollector('cache', 'Cache', '90%', 'green', [
            'hit_rate' => 90.0,
            'transient_operations' => [
                ['name' => 'my_key', 'operation' => 'set', 'expiration' => 3600, 'caller' => 'test', 'time' => 95.0],
            ],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Timeline', $html);
        // DB queries aggregated into single row
        self::assertStringContainsString('Database (1 queries)', $html);
        // Transient operations aggregated into single row
        self::assertStringContainsString('Cache (1 ops)', $html);
    }

    #[Test]
    public function renderDatabasePanelShowsCallerGrouping(): void
    {
        $this->profile->addCollector($this->createCollector('database', 'Database', '4', 'green', [
            'total_count' => 4,
            'total_time' => 10.0,
            'queries' => [
                ['sql' => 'SELECT 1', 'time' => 1.0, 'caller' => 'wp_load_alloptions'],
                ['sql' => 'SELECT 2', 'time' => 2.0, 'caller' => 'wp_load_alloptions'],
                ['sql' => 'SELECT 3', 'time' => 3.0, 'caller' => 'WP_Post::get_instance'],
                ['sql' => 'SELECT 4', 'time' => 4.0, 'caller' => 'get_terms'],
            ],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Queries by Caller', $html);
        self::assertStringContainsString('wp_load_alloptions', $html);
        self::assertStringContainsString('Avg Time', $html);
    }

    #[Test]
    public function renderCachePanelShowsTransientOperations(): void
    {
        $this->profile->addCollector($this->createCollector('cache', 'Cache', '90.0%', 'green', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('cache', 'Cache', '90.0%', 'green', [
            'hits' => 100,
            'misses' => 10,
            'hit_rate' => 90.0,
            'transient_sets' => 5,
            'transient_deletes' => 2,
            'transient_operations' => [],
            'cache_groups' => [],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Transient Sets', $html);
        self::assertStringContainsString('Transient Deletes', $html);
        self::assertStringNotContainsString('Cache Groups', $html);
    }

    #[Test]
    public function renderPerformancePanelHandlesMissingCollectors(): void
    {
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '50 ms', 'green', [
            'total_time' => 50.0,
            'phases' => [],
            'events' => [],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('id="wpd-pc-performance"', $html);
        self::assertStringContainsString('Total Time', $html);
        self::assertStringContainsString('N/A', $html);
    }

    #[Test]
    public function renderPluginPanelShowsPluginData(): void
    {
        $this->profile->addCollector($this->createCollector('plugin', 'Plugins', '3', 'green', [
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
        $this->profile->addCollector($this->createCollector('asset', 'Assets', '2', 'default', [
            'scripts' => [
                'wc-cart-fragments' => [
                    'handle' => 'wc-cart-fragments',
                    'src' => '/wp-content/plugins/woocommerce/assets/js/frontend/cart-fragments.min.js',
                    'version' => '8.5.0',
                    'in_footer' => true,
                    'deps' => ['jquery'],
                    'enqueued' => true,
                ],
            ],
            'styles' => [
                'woocommerce-layout' => [
                    'handle' => 'woocommerce-layout',
                    'src' => '/wp-content/plugins/woocommerce/assets/css/woocommerce-layout.css',
                    'version' => '8.5.0',
                    'media' => 'all',
                    'deps' => [],
                    'enqueued' => true,
                ],
            ],
        ]));

        $html = $this->renderer->render();

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
        self::assertStringContainsString('cart-fragments.min.js', $html);
        self::assertStringContainsString('woocommerce-layout.css', $html);
    }

    #[Test]
    public function renderThemePanelShowsThemeData(): void
    {
        $this->profile->addCollector($this->createCollector('theme', 'Theme', '', 'default', [
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
        $this->profile->addCollector($this->createCollector('asset', 'Assets', '2', 'default', [
            'scripts' => [
                'jquery' => [
                    'handle' => 'jquery',
                    'src' => '',
                    'version' => '3.7.1',
                    'in_footer' => false,
                    'deps' => ['jquery-core', 'jquery-migrate'],
                    'enqueued' => true,
                ],
            ],
            'styles' => [
                'theme-style' => [
                    'handle' => 'theme-style',
                    'src' => '/wp-content/themes/flavor/style.css',
                    'version' => '2.1.0',
                    'media' => 'all',
                    'deps' => [],
                    'enqueued' => true,
                ],
            ],
        ]));

        $html = $this->renderer->render();

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
        self::assertStringContainsString('style.css', $html);
    }

    #[Test]
    public function renderSchedulerPanelShowsCronData(): void
    {
        $this->profile->addCollector($this->createCollector('scheduler', 'Scheduler', '3', 'green', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '198 ms', 'green', [
            'total_time' => 198.0,
            'request_time_float' => microtime(true) - 0.198,
            'phases' => ['muplugins_loaded' => 20.0],
            'events' => [],
        ]));
        $this->profile->addCollector($this->createCollector('event', 'Event', '100', 'green', [
            'hook_timings' => [
                'init' => ['count' => 10, 'total_time' => 15.0, 'start' => 57.9],
            ],
        ]));
        $this->profile->addCollector($this->createCollector('plugin', 'Plugins', '1', 'green', [
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

        $html = $this->renderer->render();

        self::assertStringContainsString('Plugins', $html);
        self::assertStringContainsString('TestPlugin', $html);
    }

    #[Test]
    public function renderLoggerPanelShowsFilterTabs(): void
    {
        $this->profile->addCollector($this->createCollector('logger', 'Logs', '5', 'red', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('admin', 'Admin', '5', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('admin', 'Admin', '-', 'default', [
            'is_admin' => false,
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Not in admin context.', $html);
    }

    #[Test]
    public function renderAjaxPanelShowsActions(): void
    {
        $this->profile->addCollector($this->createCollector('ajax', 'Ajax', '3', 'default', [
            'total_actions' => 3,
            'nopriv_count' => 1,
            'registered_actions' => [
                'heartbeat' => ['callback' => 'wp_ajax_heartbeat', 'nopriv' => false],
                'my_action' => ['callback' => 'MyPlugin::handle', 'nopriv' => true],
            ],
        ]));

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('asset', 'Assets', '5/3', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('container', 'Container', '10', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('dump', 'Dumps', '2', 'yellow', [
            'dumps' => [
                ['file' => '/app/src/Controller.php', 'line' => 42, 'data' => 'string(5) "hello"'],
                ['file' => '/app/src/Service.php', 'line' => 100, 'data' => 'int(42)'],
            ],
            'total_count' => 2,
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Dumps (2)', $html);
        self::assertStringContainsString('Controller.php', $html);
        self::assertStringContainsString(':42', $html);
        self::assertStringContainsString('string(5) &quot;hello&quot;', $html);
    }

    #[Test]
    public function renderDumpPanelShowsEmptyMessage(): void
    {
        $this->profile->addCollector($this->createCollector('dump', 'Dumps', '0', 'default', [
            'dumps' => [],
            'total_count' => 0,
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('No dump() calls recorded.', $html);
    }

    #[Test]
    public function renderFeedPanelShowsFeeds(): void
    {
        $this->profile->addCollector($this->createCollector('feed', 'Feed', '3', 'default', [
            'total_count' => 3,
            'custom_count' => 1,
            'feed_discovery' => true,
            'feeds' => [
                ['type' => 'rss2', 'url' => 'https://example.com/feed/', 'is_custom' => false],
                ['type' => 'atom', 'url' => 'https://example.com/feed/atom/', 'is_custom' => false],
                ['type' => 'rss2', 'url' => 'https://example.com/custom-feed/', 'is_custom' => true],
            ],
        ]));

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('http_client', 'HTTP Client', '2', 'green', [
            'total_count' => 2,
            'total_time' => 350.5,
            'error_count' => 1,
            'slow_count' => 0,
            'requests' => [
                ['method' => 'GET', 'url' => 'https://api.example.com/data', 'status_code' => 200, 'duration' => 150.2, 'response_size' => 4096, 'start' => 0, 'error' => ''],
                ['method' => 'POST', 'url' => 'https://api.example.com/submit', 'status_code' => 500, 'duration' => 200.3, 'response_size' => 128, 'start' => 0, 'error' => 'Internal Server Error'],
            ],
        ]));

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('mail', 'Mail', '2', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('rest', 'REST', '25', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('router', 'Router', '', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('router', 'Router', '', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('security', 'Security', '', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('shortcode', 'Shortcode', '5/2', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('translation', 'Translation', '150', 'default', [
            'total_lookups' => 150,
            'missing_count' => 2,
            'loaded_domains' => ['default', 'woocommerce', 'my-plugin'],
            'domain_usage' => ['default' => 80, 'woocommerce' => 50, 'my-plugin' => 20],
            'missing_translations' => [
                ['original' => 'Untranslated text', 'domain' => 'my-plugin'],
                ['original' => 'Another missing', 'domain' => 'my-plugin'],
            ],
        ]));

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('widget', 'Widget', '8', 'default', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('wordpress', 'WordPress', '6.4', 'default', [
            'wp_version' => '6.4.2',
            'environment_type' => 'local',
            'is_multisite' => false,
            'constants' => ['WP_DEBUG' => true, 'WP_DEBUG_LOG' => false, 'SCRIPT_DEBUG' => null],
        ]));
        $this->profile->addCollector($this->createCollector('theme', 'Theme', '', 'default', [
            'name' => 'Twenty Twenty-Four',
            'version' => '1.2',
            'is_block_theme' => true,
            'is_child_theme' => false,
            'parent_theme' => '',
        ]));
        $this->profile->addCollector($this->createCollector('plugin', 'Plugins', '', 'default', [
            'plugins' => [
                'loader.php' => ['name' => 'MU Loader', 'is_mu' => true],
                'woocommerce/woocommerce.php' => ['name' => 'WooCommerce'],
                'akismet/akismet.php' => ['name' => 'Akismet'],
            ],
            'mu_plugins' => ['loader.php'],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('6.4.2', $html);
        self::assertStringContainsString('local', $html);
        self::assertStringContainsString('Debug Constants', $html);
        self::assertStringContainsString('WP_DEBUG', $html);
        self::assertStringContainsString('Active Theme', $html);
        self::assertStringContainsString('Twenty Twenty-Four', $html);
        self::assertStringContainsString('Must-Use Plugins', $html);
        self::assertStringContainsString('Active Plugins', $html);
        self::assertStringNotContainsString('PHP Extensions', $html);
    }

    #[Test]
    public function renderMemoryPanelShowsSnapshots(): void
    {
        $this->profile->addCollector($this->createCollector('memory', 'Memory', '42.5 MB', 'yellow', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('request', 'Request', 'GET 200', 'green', [
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

        $html = $this->renderer->render();

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
        $this->profile->addCollector($this->createCollector('request', 'Request', 'POST 302', 'yellow', [
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

        $html = $this->renderer->render();

        self::assertStringContainsString('POST', $html);
        self::assertStringContainsString('POST Parameters', $html);
        self::assertStringContainsString('editpost', $html);
        self::assertStringContainsString('HTTP API Calls', $html);
        self::assertStringContainsString('api.wordpress.org/plugins/info/', $html);
    }

    #[Test]
    public function renderRestPanelShowsNonRestSummary(): void
    {
        $this->profile->addCollector($this->createCollector('rest', 'REST', '25', 'default', [
            'is_rest_request' => false,
            'current_request' => null,
            'total_routes' => 25,
            'total_namespaces' => 3,
            'routes' => [],
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('REST Request', $html);
        self::assertStringContainsString('false', $html);
        self::assertStringNotContainsString('Current Request', $html);
    }

    #[Test]
    public function renderDatabasePanelShowsSlowAndDuplicateQueries(): void
    {
        $requestTimeFloat = microtime(true) - 0.198;
        $queries = [
            ['sql' => 'SELECT * FROM wp_posts WHERE ID = 1', 'time' => 0.5, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.01, 'data' => []],
            ['sql' => 'SELECT * FROM wp_posts WHERE ID = 1', 'time' => 0.3, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.02, 'data' => []],
            ['sql' => 'SELECT * FROM wp_postmeta WHERE post_id = 1', 'time' => 150.0, 'caller' => 'get_post_meta, WP_Query::get_posts', 'start' => $requestTimeFloat + 0.03, 'data' => []],
        ];
        // Add 6 queries from same caller to trigger >5 warning
        for ($i = 0; $i < 4; $i++) {
            $queries[] = ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = "opt' . $i . '"', 'time' => 0.2, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.04 + ($i * 0.001), 'data' => []];
        }
        $this->profile->addCollector($this->createCollector('database', 'Database', '7', 'yellow', [
            'total_count' => 7,
            'total_time' => 152.0,
            'duplicate_count' => 1,
            'slow_count' => 1,
            'suggestions' => ['Consider adding an index on wp_postmeta.meta_key'],
            'queries' => $queries,
        ]));

        $html = $this->renderer->render();

        self::assertStringContainsString('Queries by Caller', $html);
        self::assertStringContainsString('wp_postmeta', $html);
        // Comma-separated caller shows only last entry
        self::assertStringContainsString('WP_Query::get_posts', $html);
        self::assertStringContainsString('Suggestions', $html);
        // Slow query has SLOW indicator
        self::assertStringContainsString('SLOW', $html);
        // Duplicate query has DUP indicator
        self::assertStringContainsString('DUP', $html);
        self::assertStringContainsString('wpd-row-slow', $html);
        self::assertStringContainsString('wpd-row-duplicate', $html);
    }

    #[Test]
    public function renderEventPanelShowsHookData(): void
    {
        $this->profile->addCollector($this->createCollector('event', 'Events', '250', 'default', [
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

        $html = $this->renderer->render();

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

    private function createProfileWithCollectors(): void
    {
        $this->profile->addCollector($this->createCollector('memory', 'Memory', '10 MB', 'green'));
        $this->profile->addCollector($this->createCollector('stopwatch', 'Stopwatch', '120 ms', 'default'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCollector(
        string $name,
        string $label,
        string $indicatorValue,
        string $indicatorColor,
        array $data = [],
    ): DataCollectorInterface {
        return new class ($name, $label, $indicatorValue, $indicatorColor, $data) implements DataCollectorInterface {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(
                private readonly string $name,
                private readonly string $label,
                private readonly string $indicatorValue,
                private readonly string $indicatorColor,
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

            public function getIndicatorValue(): string
            {
                return $this->indicatorValue;
            }

            public function getIndicatorColor(): string
            {
                return $this->indicatorColor;
            }

            public function reset(): void {}
        };
    }

    #[Test]
    public function genericPanelRendererWithEmptyDataShowsNoDataMessage(): void
    {
        $profile = new Profile();
        $profile->addCollector($this->createCollector('test_empty', 'Test', '', 'default'));
        $renderer = new GenericPanelRenderer($profile, collectorName: 'test_empty');
        $html = $renderer->renderPanel();
        self::assertStringContainsString('No data collected.', $html);
    }

    #[Test]
    public function genericPanelRendererWithDataShowsTable(): void
    {
        $profile = new Profile();
        $profile->addCollector($this->createCollector('test_data', 'Test', '', 'default', ['key1' => 'value1', 'key2' => 42]));
        $renderer = new GenericPanelRenderer($profile, collectorName: 'test_data');
        $html = $renderer->renderPanel();
        self::assertStringContainsString('key1', $html);
        self::assertStringContainsString('value1', $html);
        self::assertStringContainsString('key2', $html);
    }

    #[Test]
    public function performancePanelRendersDbTimeline(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 100.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => [
                'total_count' => 2, 'total_time' => 5.0, 'slow_count' => 0,
                'queries' => [
                    ['sql' => 'SELECT * FROM wp_posts', 'time' => 2.5, 'caller' => 'test', 'start' => $requestTime + 0.01],
                    ['sql' => 'SELECT * FROM wp_options', 'time' => 2.5, 'caller' => 'test', 'start' => $requestTime + 0.02],
                ],
            ],
            'cache' => [],
            'http_client' => [],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Database (2 queries)', $html);
        self::assertStringContainsString('Timeline', $html);
    }

    #[Test]
    public function performancePanelRendersHttpTimeline(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 500.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => [
                'total_count' => 1, 'total_time' => 200.0,
                'requests' => [
                    ['url' => 'https://api.example.com/data', 'method' => 'GET', 'status_code' => 200, 'duration' => 200.0, 'start' => $requestTime + 0.05],
                ],
            ],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('HTTP Client (1 requests)', $html);
    }

    #[Test]
    public function performancePanelRendersMailTimeline(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 300.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [],
            'mail' => [
                'emails' => [
                    ['subject' => 'Test Email', 'status' => 'sent', 'start' => $requestTime + 0.1, 'duration' => 50.0],
                ],
            ],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Mail (1 emails)', $html);
    }

    #[Test]
    public function performancePanelRendersWidgetTimeline(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 200.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [
                'sidebar_timings' => [
                    ['sidebar' => 'sidebar-1', 'name' => 'Primary Sidebar', 'start' => $requestTime + 0.05, 'duration' => 10.0],
                ],
            ],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Widgets (1 sidebars)', $html);
    }

    #[Test]
    public function performancePanelRendersShortcodeTimeline(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 200.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [
                'executions' => [
                    ['tag' => 'gallery', 'start' => $requestTime + 0.03, 'duration' => 5.0],
                    ['tag' => 'caption', 'start' => $requestTime + 0.04, 'duration' => 2.0],
                ],
            ],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Shortcodes (2 executions)', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsPluginLoadTimeBars(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => [
                'total_time' => 300.0,
                'request_time_float' => $requestTime,
                'phases' => ['muplugins_loaded' => 15.0, 'plugins_loaded' => 50.0],
                'events' => [
                    'plugins_loaded' => [
                        'name' => 'plugins_loaded',
                        'category' => 'wordpress',
                        'duration' => 35.0,
                        'memory' => 4096,
                        'start_time' => 15.0,
                        'end_time' => 50.0,
                    ],
                ],
            ],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => ['hook_timings' => []],
            'mail' => [],
            'plugin' => [
                'plugins' => [
                    'my-plugin/my-plugin.php' => [
                        'name' => 'My Plugin',
                        'load_time' => 12.5,
                        'hook_time' => 12.5,
                        'hooks' => [],
                    ],
                ],
                'load_order' => ['my-plugin/my-plugin.php'],
            ],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Plugins', $html);
        self::assertStringContainsString('My Plugin', $html);
        // Plugin load time bar should have "load" in the tooltip
        self::assertStringContainsString('load', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsPluginHookTimeBars(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => [
                'total_time' => 300.0,
                'request_time_float' => $requestTime,
                'phases' => ['muplugins_loaded' => 15.0, 'plugins_loaded' => 50.0, 'init' => 120.0],
                'events' => [],
            ],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [
                'hook_timings' => [
                    'init' => ['count' => 5, 'total_time' => 20.0, 'start' => 55.0],
                    'wp_loaded' => ['count' => 3, 'total_time' => 8.0, 'start' => 80.0],
                ],
            ],
            'mail' => [],
            'plugin' => [
                'plugins' => [
                    'woo/woo.php' => [
                        'name' => 'WooCommerce',
                        'hook_time' => 18.0,
                        'hooks' => [
                            ['hook' => 'init', 'listeners' => 3, 'time' => 12.0],
                            ['hook' => 'wp_loaded', 'listeners' => 2, 'time' => 6.0],
                        ],
                    ],
                ],
                'load_order' => ['woo/woo.php'],
            ],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Plugins', $html);
        self::assertStringContainsString('WooCommerce', $html);
        // Hook names should appear in tooltips
        self::assertStringContainsString('init', $html);
        self::assertStringContainsString('wp_loaded', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsThemeHookBars(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => [
                'total_time' => 300.0,
                'request_time_float' => $requestTime,
                'phases' => ['muplugins_loaded' => 15.0, 'init' => 80.0],
                'events' => [],
            ],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [
                'hook_timings' => [
                    'wp_head' => ['count' => 8, 'total_time' => 15.0, 'start' => 100.0],
                    'wp_footer' => ['count' => 4, 'total_time' => 5.0, 'start' => 200.0],
                ],
            ],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => [
                'name' => 'Twenty Twenty-Four',
                'hook_time' => 20.0,
                'hooks' => [
                    ['hook' => 'wp_head', 'listeners' => 5, 'time' => 12.0],
                    ['hook' => 'wp_footer', 'listeners' => 3, 'time' => 8.0],
                ],
            ],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        // Theme section divider should appear
        self::assertStringContainsString('Theme', $html);
        self::assertStringContainsString('Twenty Twenty-Four', $html);
        // Hook names in tooltips
        self::assertStringContainsString('wp_head', $html);
        self::assertStringContainsString('wp_footer', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsWidgetSidebarBars(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => [
                'total_time' => 400.0,
                'request_time_float' => $requestTime,
                'phases' => ['muplugins_loaded' => 15.0],
                'events' => [],
            ],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [
                'sidebar_timings' => [
                    ['sidebar' => 'sidebar-1', 'name' => 'Main Sidebar', 'start' => $requestTime + 0.15, 'duration' => 8.3],
                    ['sidebar' => 'sidebar-2', 'name' => 'Footer Area', 'start' => $requestTime + 0.20, 'duration' => 4.2],
                ],
            ],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Widgets (2 sidebars)', $html);
        // Widget names in tooltips
        self::assertStringContainsString('Main Sidebar', $html);
        self::assertStringContainsString('Footer Area', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsShortcodeExecutionBars(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => [
                'total_time' => 400.0,
                'request_time_float' => $requestTime,
                'phases' => ['muplugins_loaded' => 15.0],
                'events' => [],
            ],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [
                'executions' => [
                    ['tag' => 'gallery', 'start' => $requestTime + 0.10, 'duration' => 10.2],
                    ['tag' => 'contact-form', 'start' => $requestTime + 0.12, 'duration' => 5.1],
                    ['tag' => 'video', 'start' => $requestTime + 0.15, 'duration' => 3.5],
                ],
            ],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Shortcodes (3 executions)', $html);
        // Shortcode tags in tooltips (wrapped in square brackets)
        self::assertStringContainsString('[gallery]', $html);
        self::assertStringContainsString('[contact-form]', $html);
        self::assertStringContainsString('[video]', $html);
    }

    #[Test]
    public function renderPerformancePanelShowsMailBars(): void
    {
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => [
                'total_time' => 500.0,
                'request_time_float' => $requestTime,
                'phases' => ['muplugins_loaded' => 15.0],
                'events' => [],
            ],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => ['total_count' => 0, 'total_time' => 0.0, 'requests' => []],
            'event' => [],
            'mail' => [
                'emails' => [
                    ['subject' => 'Welcome Email', 'status' => 'sent', 'start' => $requestTime + 0.10, 'duration' => 50.0],
                    ['subject' => 'Password Reset', 'status' => 'failed', 'start' => $requestTime + 0.20, 'duration' => 120.0],
                ],
            ],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        self::assertStringContainsString('Mail (2 emails)', $html);
        // Email subjects in tooltips
        self::assertStringContainsString('Welcome Email', $html);
        self::assertStringContainsString('Password Reset', $html);
        // Status labels in tooltips
        self::assertStringContainsString('sent', $html);
        self::assertStringContainsString('failed', $html);
    }

    #[Test]
    public function performancePanelRenderIndicatorRedWhenHighMemory(): void
    {
        // Cover lines 38-39: red indicator when usagePercentage >= 90
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 50.0, 'events' => [], 'phases' => [], 'request_time_float' => 0.0],
            'memory' => ['peak' => 1048576, 'limit' => 1100000, 'usage_percentage' => 95.0],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderIndicator();

        // Red indicator background should use CSS variable
        self::assertStringContainsString('style="background:var(--wpd-red-a12)"', $html);
    }

    #[Test]
    public function performancePanelRenderIndicatorRedWhenSlowQueries(): void
    {
        // Cover lines 38-39: red indicator when slowQueries > 0
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 50.0, 'events' => [], 'phases' => [], 'request_time_float' => 0.0],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 5, 'total_time' => 10.0, 'slow_count' => 2, 'queries' => []],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderIndicator();

        self::assertStringContainsString('style="background:var(--wpd-red-a12)"', $html);
    }

    #[Test]
    public function performancePanelRenderIndicatorRedWhenSlowTotalTime(): void
    {
        // Cover lines 38-39: red indicator when totalTime >= 1000
        $requestTime = microtime(true) - 2.0; // 2 seconds ago to ensure getTime() >= 1000ms
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 1500.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
        ]);

        // Ensure profile getTime() returns >= 1000ms by setting REQUEST_TIME_FLOAT
        $origRequestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_TIME_FLOAT'] = $requestTime;

        try {
            $renderer = new PerformancePanelRenderer($profile);
            $html = $renderer->renderIndicator();

            // With totalTime >= 1000ms, red indicator should appear
            self::assertStringContainsString('style="background:var(--wpd-red-a12)"', $html);
        } finally {
            if ($origRequestTime !== null) {
                $_SERVER['REQUEST_TIME_FLOAT'] = $origRequestTime;
            } else {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            }
        }
    }

    #[Test]
    public function performancePanelRenderPanelContainsContent(): void
    {
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 100.0, 'events' => [], 'phases' => [], 'request_time_float' => 0.0],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => [],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        // renderPanel() delegates to renderContent(), which produces Overview section
        self::assertStringContainsString('Overview', $html);
        self::assertStringContainsString('Total Time', $html);
        self::assertStringContainsString('Peak Memory', $html);
    }

    #[Test]
    public function performancePanelRendersHttpTimelineSkipsRequestsWithoutStart(): void
    {
        // Cover line 308: continue when !isset($req['start'])
        $requestTime = microtime(true);
        $profile = $this->createProfileWithMockCollectors([
            'stopwatch' => ['total_time' => 200.0, 'events' => [], 'phases' => [], 'request_time_float' => $requestTime],
            'memory' => ['peak' => 1048576, 'limit' => 134217728, 'usage_percentage' => 0.8],
            'database' => ['total_count' => 0, 'total_time' => 0.0, 'slow_count' => 0, 'queries' => []],
            'cache' => [],
            'http_client' => [
                'total_count' => 3,
                'total_time' => 100.0,
                'requests' => [
                    // Request with start -> should appear in timeline
                    ['url' => 'https://api.example.com/data', 'method' => 'GET', 'status_code' => 200, 'duration' => 50.0, 'start' => $requestTime + 0.01],
                    // Request without start -> should be skipped (line 308)
                    ['url' => 'https://api.example.com/no-start', 'method' => 'POST', 'status_code' => 201, 'duration' => 30.0],
                    // Another request with start
                    ['url' => 'https://api.example.com/other', 'method' => 'PUT', 'status_code' => 200, 'duration' => 20.0, 'start' => $requestTime + 0.05],
                ],
            ],
            'event' => [],
            'mail' => [],
            'plugin' => ['plugins' => [], 'load_order' => []],
            'theme' => ['hooks' => []],
            'widget' => [],
            'shortcode' => [],
        ]);

        $renderer = new PerformancePanelRenderer($profile);
        $html = $renderer->renderPanel();

        // Only 2 requests have 'start', so the timeline should say "2 requests"
        self::assertStringContainsString('HTTP Client (2 requests)', $html);
        // The request without start should NOT appear
        self::assertStringNotContainsString('no-start', $html);
        // Requests with start should appear
        self::assertStringContainsString('api.example.com/data', $html);
        self::assertStringContainsString('api.example.com/other', $html);
    }

    /**
     * @param array<string, array<string, mixed>> $collectorData
     */
    private function createProfileWithMockCollectors(array $collectorData): Profile
    {
        $profile = new Profile();

        foreach ($collectorData as $name => $data) {
            $profile->addCollector($this->createCollector($name, ucfirst($name), '', 'default', $data));
        }

        return $profile;
    }

    #[Test]
    public function renderLoggerPanelCoversAllLogLevels(): void
    {
        $this->profile->addCollector($this->createCollector('logger', 'Logs', '8', 'red', [
            'total_count' => 8,
            'error_count' => 4,
            'deprecation_count' => 1,
            'level_counts' => ['emergency' => 1, 'alert' => 1, 'critical' => 1, 'error' => 1, 'warning' => 1, 'notice' => 1, 'info' => 1, 'deprecation' => 1],
            'logs' => [
                ['level' => 'emergency', 'message' => 'System down', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'alert', 'message' => 'Alert msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'critical', 'message' => 'Critical msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'error', 'message' => 'Error msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'warning', 'message' => 'Warning msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'notice', 'message' => 'Notice msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'info', 'message' => 'Info msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
                ['level' => 'deprecation', 'message' => 'Deprecated msg', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0],
            ],
        ]));

        $html = $this->renderer->render();

        // Level-specific CSS classes
        self::assertStringContainsString('wpd-log-critical', $html);
        self::assertStringContainsString('wpd-log-error', $html);
        self::assertStringContainsString('wpd-log-warning', $html);
        self::assertStringContainsString('wpd-log-notice', $html);
        self::assertStringContainsString('wpd-log-info', $html);
        self::assertStringContainsString('wpd-log-debug', $html);
        self::assertStringContainsString('wpd-log-deprecation', $html);

        // Tab counts (emergency+alert+critical+error = 4 errors, warning = 1, notice = 1, info = 1)
        self::assertStringContainsString('Errors (4)', $html);
        self::assertStringContainsString('Warnings (1)', $html);
        self::assertStringContainsString('Notices (1)', $html);
        self::assertStringContainsString('Info (1)', $html);
    }
}
