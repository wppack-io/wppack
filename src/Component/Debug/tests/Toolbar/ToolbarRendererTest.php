<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ToolbarRendererTest extends TestCase
{
    private ToolbarRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ToolbarRenderer();
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

        self::assertStringContainsString('id="wpd-panel-cache"', $html);
        self::assertStringContainsString('id="wpd-panel-wordpress"', $html);
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

        self::assertStringContainsString('id="wpd-panel-performance"', $html);
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
        self::assertStringContainsString('24 queries', $html);
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
        self::assertStringContainsString('3600s', $html);
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

        self::assertStringContainsString('id="wpd-panel-performance"', $html);
        self::assertStringContainsString('Total Time', $html);
        self::assertStringContainsString('N/A', $html);
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
