<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use WpPack\Component\Debug\DataCollector\AbstractDataCollector;
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
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
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

/**
 * Fake collector that injects pre-built data for demo purposes.
 */
final class FakeCollector extends AbstractDataCollector
{
    /**
     * @param array<string, mixed> $fakeData
     */
    public function __construct(
        private readonly string $fakeName,
        private readonly string $fakeLabel,
        private readonly string $fakeIndicatorValue,
        private readonly string $fakeIndicatorColor,
        private readonly array $fakeData,
    ) {}

    public function getName(): string
    {
        return $this->fakeName;
    }

    public function getLabel(): string
    {
        return $this->fakeLabel;
    }

    public function getIndicatorValue(): string
    {
        return $this->fakeIndicatorValue;
    }

    public function getIndicatorColor(): string
    {
        return $this->fakeIndicatorColor;
    }

    public function collect(): void
    {
        $this->data = $this->fakeData;
    }
}

// --- Build sample collectors ---
//
// Realistic WordPress lifecycle timeline (single post view, WooCommerce site):
//
//   0.0ms   Request start
//   4.5ms   muplugins_loaded      (MU plugin loading)
//  62.0ms   plugins_loaded        (4 plugins loaded — WooCommerce is heavy)
//  63.5ms   setup_theme           (theme file located)
//  70.0ms   after_setup_theme     (theme features, menus, image sizes)
//  98.0ms   init                  (post types, taxonomies, shortcodes)
// 101.0ms   wp_loaded             (all loaded, pre-query)
// 112.0ms   wp                    (parse_request → main query → send_headers)
// 118.0ms   template_redirect     (template resolution)
// 155.0ms   wp_head               (scripts, styles, meta output)
// 198.0ms   wp_footer             (footer scripts, deferred output)
//

$collectors = [];

$requestTimeFloat = microtime(true) - 0.198; // 198ms ago

// --- Group 1: Core Performance (255–245) ---

// Request
$collectors[] = new FakeCollector('request', 'Request', 'GET 200', 'green', [
    'method' => 'GET',
    'url' => 'https://example.local/2024/03/hello-world/',
    'status_code' => 200,
    'content_type' => 'text/html; charset=UTF-8',
    'request_headers' => [
        'Host' => 'example.local',
        'Accept' => 'text/html,application/xhtml+xml',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'Accept-Language' => 'ja,en;q=0.9',
        'Cookie' => '********',
    ],
    'response_headers' => [
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Powered-By' => 'WpPack',
        'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
    ],
    'get_params' => [],
    'post_params' => [],
    'cookies' => [
        'wordpress_logged_in_xxx' => '********',
        'wp-settings-1' => 'libraryContent=browse',
        'wp-settings-time-1' => '1710500400',
    ],
    'server_vars' => [
        'SERVER_NAME' => 'example.local',
        'SERVER_ADDR' => '127.0.0.1',
        'SERVER_PORT' => '443',
        'SERVER_SOFTWARE' => 'nginx/1.25.3',
        'SERVER_PROTOCOL' => 'HTTP/2.0',
        'DOCUMENT_ROOT' => '/var/www/html',
        'REMOTE_ADDR' => '192.168.1.100',
        'REQUEST_URI' => '/2024/03/hello-world/',
        'REQUEST_METHOD' => 'GET',
        'REQUEST_TIME_FLOAT' => $requestTimeFloat,
        'QUERY_STRING' => '',
        'HTTPS' => 'on',
        'SCRIPT_FILENAME' => '/var/www/html/index.php',
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'SCRIPT_NAME' => '/index.php',
    ],
    'http_api_calls' => [],
]);

// Stopwatch — lifecycle phases consistent with the timeline above
$collectors[] = new FakeCollector('stopwatch', 'Stopwatch', '', 'default', [
    'total_time' => 198.0,
    'request_time_float' => $requestTimeFloat,
    'events' => [
        'muplugins_loaded'  => ['name' => 'muplugins_loaded',  'category' => 'wordpress', 'duration' =>  4.5, 'memory' => (int) (8.0 * 1024 * 1024), 'start_time' =>   0.0, 'end_time' =>   4.5],
        'plugins_loaded'    => ['name' => 'plugins_loaded',    'category' => 'wordpress', 'duration' => 57.5, 'memory' => (int) (22.0 * 1024 * 1024), 'start_time' =>   4.5, 'end_time' =>  62.0],
        'setup_theme'       => ['name' => 'setup_theme',       'category' => 'wordpress', 'duration' =>  1.5, 'memory' => (int) (22.5 * 1024 * 1024), 'start_time' =>  62.0, 'end_time' =>  63.5],
        'after_setup_theme' => ['name' => 'after_setup_theme', 'category' => 'wordpress', 'duration' =>  6.5, 'memory' => (int) (24.0 * 1024 * 1024), 'start_time' =>  63.5, 'end_time' =>  70.0],
        'init'              => ['name' => 'init',              'category' => 'wordpress', 'duration' => 28.0, 'memory' => (int) (28.0 * 1024 * 1024), 'start_time' =>  70.0, 'end_time' =>  98.0],
        'wp_loaded'         => ['name' => 'wp_loaded',         'category' => 'wordpress', 'duration' =>  3.0, 'memory' => (int) (30.0 * 1024 * 1024), 'start_time' =>  98.0, 'end_time' => 101.0],
        'wp'                => ['name' => 'wp',                'category' => 'wordpress', 'duration' => 11.0, 'memory' => (int) (32.0 * 1024 * 1024), 'start_time' => 101.0, 'end_time' => 112.0],
        'template_redirect' => ['name' => 'template_redirect', 'category' => 'wordpress', 'duration' =>  6.0, 'memory' => (int) (33.5 * 1024 * 1024), 'start_time' => 112.0, 'end_time' => 118.0],
        'wp_head'           => ['name' => 'wp_head',           'category' => 'wordpress', 'duration' => 37.0, 'memory' => (int) (38.0 * 1024 * 1024), 'start_time' => 118.0, 'end_time' => 155.0],
        'wp_footer'         => ['name' => 'wp_footer',         'category' => 'wordpress', 'duration' => 43.0, 'memory' => (int) (44.0 * 1024 * 1024), 'start_time' => 155.0, 'end_time' => 198.0],
    ],
    'phases' => [
        'muplugins_loaded'  =>   4.5,
        'plugins_loaded'    =>  62.0,
        'setup_theme'       =>  63.5,
        'after_setup_theme' =>  70.0,
        'init'              =>  98.0,
        'wp_loaded'         => 101.0,
        'wp'                => 112.0,
        'template_redirect' => 118.0,
        'wp_head'           => 155.0,
        'wp_footer'         => 198.0,
    ],
]);

// Memory
$collectors[] = new FakeCollector('memory', 'Memory', '44.0 MB', 'default', [
    'current' => (int) (44.0 * 1024 * 1024),
    'peak' => (int) (46.5 * 1024 * 1024),
    'limit' => 256 * 1024 * 1024,
    'usage_percentage' => 17.19,
    'snapshots' => [
        'wp_loaded' => (int) (30.0 * 1024 * 1024),
        'template_redirect' => (int) (36.0 * 1024 * 1024),
        'wp_footer' => (int) (44.0 * 1024 * 1024),
    ],
]);

// --- Group 2: Data & I/O (200–190) ---

// Database — queries spread across lifecycle phases
$collectors[] = new FakeCollector('database', 'Database', '24', 'default', [
    'total_count' => 24,
    'total_time' => 12.45,
    'duplicate_count' => 2,
    'slow_count' => 0,
    'savequeries' => true,
    'queries' => [
        // Early bootstrap — autoload options (during muplugins_loaded → plugins_loaded)
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 1.23, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.003, 'data' => []],
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 0.98, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.008, 'data' => []],
        // User auth check (during plugins_loaded)
        ['sql' => 'SELECT * FROM wp_users WHERE ID = 1 LIMIT 1', 'time' => 0.32, 'caller' => 'WP_User::get_data_by', 'start' => $requestTimeFloat + 0.045, 'data' => []],
        ['sql' => 'SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = 1', 'time' => 0.28, 'caller' => 'get_user_meta', 'start' => $requestTimeFloat + 0.048, 'data' => []],
        // WooCommerce init queries
        ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = \'woocommerce_queue_flush_rewrite_rules\'', 'time' => 0.15, 'caller' => 'WC_Post_Types::register_post_types', 'start' => $requestTimeFloat + 0.075, 'data' => []],
        ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = \'woocommerce_db_version\'', 'time' => 0.12, 'caller' => 'WooCommerce::init', 'start' => $requestTimeFloat + 0.080, 'data' => []],
        // Main query (during wp phase — parse_request → WP_Query)
        ['sql' => 'SELECT * FROM wp_posts WHERE post_name = \'hello-world\' AND post_type = \'post\' LIMIT 1', 'time' => 0.45, 'caller' => 'WP_Query::get_posts', 'start' => $requestTimeFloat + 0.104, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 42 LIMIT 1', 'time' => 0.35, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.106, 'data' => []],
        ['sql' => 'SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = 42', 'time' => 0.25, 'caller' => 'get_post_meta', 'start' => $requestTimeFloat + 0.108, 'data' => []],
        // Taxonomy queries (during template rendering)
        ['sql' => 'SELECT t.*, tt.* FROM wp_terms INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = \'category\' AND t.term_id IN (5)', 'time' => 0.56, 'caller' => 'get_the_terms', 'start' => $requestTimeFloat + 0.135, 'data' => []],
        ['sql' => 'SELECT t.*, tt.* FROM wp_terms INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = \'post_tag\' AND t.term_id IN (8, 12)', 'time' => 0.48, 'caller' => 'get_the_terms', 'start' => $requestTimeFloat + 0.140, 'data' => []],
    ],
    'suggestions' => ['2 duplicate queries detected — consider caching results'],
]);

// Cache
$collectors[] = new FakeCollector('cache', 'Cache', '92.4%', 'green', [
    'hits' => 245,
    'misses' => 20,
    'hit_rate' => 92.45,
    'transient_sets' => 3,
    'transient_deletes' => 1,
    'object_cache_dropin' => 'Redis',
    'transient_operations' => [
        ['name' => 'wc_session_data', 'operation' => 'set', 'expiration' => 3600, 'caller' => 'WC_Session_Handler::save_data', 'time' => 95.0],
        ['name' => 'external_api_response', 'operation' => 'set', 'expiration' => 86400, 'caller' => 'fetch_api_data', 'time' => 130.0],
        ['name' => 'theme_update_check', 'operation' => 'set', 'expiration' => 43200, 'caller' => 'wp_update_themes', 'time' => 145.0],
        ['name' => 'wc_expired_transient', 'operation' => 'delete', 'expiration' => 0, 'caller' => 'WC_Cache_Helper::delete_expired', 'time' => 170.0],
    ],
    'cache_groups' => [
        'options' => 156,
        'posts' => 42,
        'terms' => 28,
        'post_meta' => 24,
        'users' => 8,
        'site-options' => 5,
    ],
]);

// HttpClient — requests happen WITHIN plugin hook callbacks (blocking I/O)
//   Request 1: WooCommerce update check during init → within WC's init bar (70→85ms)
//   Request 2: Yoast SEO indexing API during wp_head → within Yoast's wp_head bar (126.5→136.5ms)
$collectors[] = new FakeCollector('http_client', 'HTTP Client', '2', 'default', [
    'total_count' => 2,
    'total_time' => 20.0,
    'error_count' => 0,
    'slow_count' => 0,
    'requests' => [
        ['method' => 'GET', 'url' => 'https://api.wordpress.org/plugins/update-check/1.1/', 'status_code' => 200, 'duration' => 12.0, 'start' => $requestTimeFloat + 0.073, 'response_size' => 4521, 'error' => ''],
        ['method' => 'POST', 'url' => 'https://wpseo-api.yoast.com/indexables/check', 'status_code' => 200, 'duration' => 8.0, 'start' => $requestTimeFloat + 0.128, 'response_size' => 1280, 'error' => ''],
    ],
]);

// --- Group 3: WordPress Context (150–135) ---

// Router — FSE block theme scenario
$collectors[] = new FakeCollector('router', 'Router', 'single', 'default', [
    'matched_rule' => '([^/]+)(?:/([0-9]+))?/?$',
    'matched_query' => 'name=hello-world&page=',
    'query_vars' => ['name' => 'hello-world', 'page' => ''],
    'template' => 'template-canvas.php',
    'template_path' => '/var/www/html/wp-content/themes/flavor/template-canvas.php',
    'is_404' => false,
    'rewrite_rules_count' => 142,
    'is_front_page' => false,
    'is_singular' => true,
    'is_archive' => false,
    'is_search' => false,
    'query_type' => 'singular',
    'is_block_theme' => true,
    'block_template' => [
        'slug' => 'single',
        'source' => 'theme',
        'theme' => 'flavor',
        'type' => 'wp_template',
        'has_theme_file' => true,
        'file_path' => '/var/www/html/wp-content/themes/flavor/templates/single.html',
        'id' => 'flavor//single',
        'parts' => [
            ['slug' => 'header', 'source' => 'theme', 'area' => 'header'],
            ['slug' => 'footer', 'source' => 'custom', 'area' => 'footer'],
            ['slug' => 'sidebar', 'source' => 'theme', 'area' => 'uncategorized'],
        ],
    ],
]);

// Plugin — wp_enqueue_scripts merged into wp_head; load_time shown on plugins_loaded
//
// Sequential within each hook (renderer applies offsets):
//   plugins_loaded:    WC 28.5 → Yoast 10.8 → Akismet 3.2 → CF7 2.1  (load bars)
//   init (24.0ms):     WC 15.0(+12ms HTTP) → Yoast 2.5 → Akismet 2.5 → CF7 1.2
//   wp_loaded:         WC 1.5
//   template_redirect: Akismet 1.8
//   wp_head (34.5ms):  WC 8.5 → Yoast 10.0(+8ms HTTP) → Akismet 0.8 → CF7 1.5 → Theme 4.5
//   the_content:       WC 1.5 → Yoast 2.0 → Theme 1.5
//   wp_footer:         WC 3.5 → Yoast 1.5 → CF7 0.5 → Theme 1.5
//
$collectors[] = new FakeCollector('plugin', 'Plugins', '4', 'default', [
    'plugins' => [
        'woocommerce/woocommerce.php' => [
            'name' => 'WooCommerce',
            'version' => '8.5.0',
            'load_time' => 28.5,
            'hook_count' => 5,
            'listener_count' => 13,
            'hook_time' => 30.0,
            'query_count' => 8,
            'query_time' => 5.3,
            'enqueued_styles' => ['woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general'],
            'enqueued_scripts' => ['wc-cart-fragments', 'woocommerce', 'wc-add-to-cart'],
            'hooks' => [
                ['hook' => 'init', 'listeners' => 3, 'time' => 15.0, 'start' => 0.0],
                ['hook' => 'wp_loaded', 'listeners' => 1, 'time' => 1.5, 'start' => 0.0],
                ['hook' => 'wp_head', 'listeners' => 6, 'time' => 8.5, 'start' => 0.0],
                ['hook' => 'the_content', 'listeners' => 1, 'time' => 1.5, 'start' => 0.0],
                ['hook' => 'wp_footer', 'listeners' => 2, 'time' => 3.5, 'start' => 0.0],
            ],
        ],
        'wordpress-seo/wp-seo.php' => [
            'name' => 'Yoast SEO',
            'version' => '22.0',
            'load_time' => 10.8,
            'hook_count' => 4,
            'listener_count' => 7,
            'hook_time' => 16.0,
            'query_count' => 3,
            'query_time' => 2.1,
            'enqueued_styles' => ['yoast-seo-adminbar'],
            'enqueued_scripts' => ['yoast-seo-adminbar'],
            'hooks' => [
                ['hook' => 'init', 'listeners' => 2, 'time' => 2.5, 'start' => 0.0],
                ['hook' => 'wp_head', 'listeners' => 3, 'time' => 10.0, 'start' => 0.0],
                ['hook' => 'the_content', 'listeners' => 1, 'time' => 2.0, 'start' => 0.0],
                ['hook' => 'wp_footer', 'listeners' => 1, 'time' => 1.5, 'start' => 0.0],
            ],
        ],
        'akismet/akismet.php' => [
            'name' => 'Akismet Anti-spam',
            'version' => '5.3',
            'load_time' => 3.2,
            'hook_count' => 3,
            'listener_count' => 3,
            'hook_time' => 5.1,
            'query_count' => 2,
            'query_time' => 1.0,
            'enqueued_styles' => [],
            'enqueued_scripts' => ['akismet-form'],
            'hooks' => [
                ['hook' => 'init', 'listeners' => 1, 'time' => 2.5, 'start' => 0.0],
                ['hook' => 'template_redirect', 'listeners' => 1, 'time' => 1.8, 'start' => 0.0],
                ['hook' => 'wp_head', 'listeners' => 1, 'time' => 0.8, 'start' => 0.0],
            ],
        ],
        'contact-form-7/wp-contact-form-7.php' => [
            'name' => 'Contact Form 7',
            'version' => '5.9',
            'load_time' => 2.1,
            'hook_count' => 3,
            'listener_count' => 3,
            'hook_time' => 3.2,
            'query_count' => 0,
            'query_time' => 0.0,
            'enqueued_styles' => ['contact-form-7'],
            'enqueued_scripts' => ['contact-form-7', 'wpcf7-recaptcha'],
            'hooks' => [
                ['hook' => 'init', 'listeners' => 1, 'time' => 1.2, 'start' => 0.0],
                ['hook' => 'wp_head', 'listeners' => 1, 'time' => 1.5, 'start' => 0.0],
                ['hook' => 'wp_footer', 'listeners' => 1, 'time' => 0.5, 'start' => 0.0],
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
                ['hook' => 'muplugins_loaded', 'listeners' => 2, 'time' => 0.8, 'start' => 0.0],
            ],
        ],
    ],
    'total_plugins' => 4,
    'mu_plugins' => ['loader.php'],
    'dropins' => ['advanced-cache.php', 'object-cache.php'],
    'load_order' => ['akismet/akismet.php', 'contact-form-7/wp-contact-form-7.php', 'woocommerce/woocommerce.php', 'wordpress-seo/wp-seo.php'],
    'slowest_plugin' => 'woocommerce/woocommerce.php',
    'total_hook_time' => 54.3,
]);

// Theme — hook_time totals continue sequentially after plugin bars within each hook
$collectors[] = new FakeCollector('theme', 'Theme', '', 'default', [
    'name' => 'Flavor',
    'version' => '2.1.0',
    'is_child_theme' => true,
    'child_theme' => 'flavor-child',
    'parent_theme' => 'flavor',
    'is_block_theme' => true,
    'template_file' => '/var/www/html/wp-content/themes/flavor/templates/single.html',
    'template_parts' => ['header', 'footer', 'sidebar'],
    'body_classes' => ['single', 'single-post', 'postid-42', 'logged-in', 'admin-bar', 'wp-embed-responsive'],
    'conditional_tags' => [
        'is_single' => true,
        'is_page' => false,
        'is_archive' => false,
        'is_home' => false,
        'is_front_page' => false,
        'is_admin' => false,
        'is_search' => false,
        'is_404' => false,
    ],
    'enqueued_styles' => ['flavor-style', 'flavor-child-style', 'wp-block-library'],
    'enqueued_scripts' => ['jquery', 'flavor-main', 'wp-embed'],
    'setup_time' => 6.5,
    'render_time' => 43.0,
    'hook_count' => 4,
    'listener_count' => 10,
    'hook_time' => 12.0,
    'hooks' => [
        ['hook' => 'after_setup_theme', 'listeners' => 2, 'time' => 4.5],
        ['hook' => 'wp_head', 'listeners' => 5, 'time' => 4.5],
        ['hook' => 'the_content', 'listeners' => 1, 'time' => 1.5],
        ['hook' => 'wp_footer', 'listeners' => 2, 'time' => 1.5],
    ],
]);

// Event — hook_timings start values must match lifecycle phase start times
//
// wp_enqueue_scripts fires inside wp_head (priority 1), so it is merged into
// wp_head for timeline consistency. Plugin load times shown on plugins_loaded.
//
// Per-hook total_time breakdown (plugin+theme+core must fit within total_time):
//   plugins_loaded:    57.5ms phase → individual plugin load times shown as bars
//   after_setup_theme:  5.5ms total → Theme 4.5 + Core 1.0
//   init:              24.0ms total → WC 15.0(+12ms HTTP) + Yoast 2.5 + Akismet 2.5 + CF7 1.2 + Core 2.8
//   wp_loaded:          2.5ms total → WC 1.5 + Core 1.0
//   template_redirect:  4.0ms total → Akismet 1.8 + Core 2.2
//   wp_head:           34.5ms total → WC 8.5 + Yoast 10.0(+8ms HTTP) + Akismet 0.8 + CF7 1.5 + Theme 4.5 + Core 9.2
//   the_content:        5.5ms total → WC 1.5 + Yoast 2.0 + Theme 1.5 + Core 0.5
//   wp_footer:         22.0ms total → WC 3.5 + Yoast 1.5 + CF7 0.5 + Theme 1.5 + Core 15.0
//
$collectors[] = new FakeCollector('event', 'Event', '847', 'default', [
    'total_firings' => 847,
    'unique_hooks' => 312,
    'registered_hooks' => 320,
    'orphan_hooks' => 8,
    'listener_counts' => [
        'init' => 42,
        'wp_head' => 36,
        'wp_footer' => 15,
        'the_content' => 8,
        'template_redirect' => 6,
        'after_setup_theme' => 5,
        'wp_loaded' => 4,
        'wp' => 3,
        'plugins_loaded' => 3,
        'setup_theme' => 1,
    ],
    'top_hooks' => [
        'esc_html' => 186,
        'esc_attr' => 142,
        'get_post_metadata' => 48,
        'sanitize_title' => 35,
        'the_title' => 28,
    ],
    'hooks' => [
        'esc_html' => 186,
        'esc_attr' => 142,
        'get_post_metadata' => 48,
        'sanitize_title' => 35,
        'the_title' => 28,
        'map_meta_cap' => 22,
        'option_siteurl' => 18,
        'option_home' => 15,
        'the_content' => 1,
        'wp_head' => 1,
        'wp_footer' => 1,
        'init' => 1,
        'wp_loaded' => 1,
        'template_redirect' => 1,
        'after_setup_theme' => 1,
        'setup_theme' => 1,
        'plugins_loaded' => 1,
        'wp' => 1,
        'muplugins_loaded' => 1,
    ],
    'hook_timings' => [
        'muplugins_loaded'  => ['count' =>  1, 'total_time' =>  1.2, 'start' =>   4.5],
        'plugins_loaded'    => ['count' =>  3, 'total_time' =>  2.0, 'start' =>  62.0],
        'setup_theme'       => ['count' =>  1, 'total_time' =>  0.8, 'start' =>  62.0],
        'after_setup_theme' => ['count' =>  5, 'total_time' =>  5.5, 'start' =>  63.5],
        'init'              => ['count' => 42, 'total_time' => 24.0, 'start' =>  70.0],
        'wp_loaded'         => ['count' =>  4, 'total_time' =>  2.5, 'start' =>  98.0],
        'wp'                => ['count' =>  3, 'total_time' =>  1.5, 'start' => 112.0],
        'template_redirect' => ['count' =>  6, 'total_time' =>  4.0, 'start' => 112.0],
        'wp_head'           => ['count' => 36, 'total_time' => 34.5, 'start' => 118.0],
        'the_content'       => ['count' =>  8, 'total_time' =>  5.5, 'start' => 145.0],
        'wp_footer'         => ['count' => 15, 'total_time' => 22.0, 'start' => 155.0],
    ],
    'component_hooks' => [
        'woocommerce' => ['init' => 3, 'wp_loaded' => 1, 'wp_head' => 6, 'the_content' => 1, 'wp_footer' => 2],
        'wordpress-seo' => ['init' => 2, 'wp_head' => 3, 'the_content' => 1, 'wp_footer' => 1],
        'akismet' => ['init' => 1, 'template_redirect' => 1, 'wp_head' => 1],
        'contact-form-7' => ['init' => 1, 'wp_head' => 1, 'wp_footer' => 1],
        'theme:flavor' => ['after_setup_theme' => 2, 'wp_head' => 5, 'the_content' => 1, 'wp_footer' => 2],
        'core' => ['init' => 35, 'wp_head' => 20, 'wp_footer' => 9, 'the_content' => 5, 'wp_loaded' => 3, 'template_redirect' => 5, 'after_setup_theme' => 3],
    ],
    'component_summary' => [
        'woocommerce' => ['type' => 'plugin', 'hooks' => 5, 'listeners' => 13, 'total_time' => 30.0],
        'wordpress-seo' => ['type' => 'plugin', 'hooks' => 4, 'listeners' => 7, 'total_time' => 16.0],
        'akismet' => ['type' => 'plugin', 'hooks' => 3, 'listeners' => 3, 'total_time' => 5.1],
        'contact-form-7' => ['type' => 'plugin', 'hooks' => 3, 'listeners' => 3, 'total_time' => 3.2],
        'theme:flavor' => ['type' => 'theme', 'hooks' => 4, 'listeners' => 10, 'total_time' => 12.0],
        'core' => ['type' => 'core', 'hooks' => 7, 'listeners' => 80, 'total_time' => 32.0],
    ],
]);

// --- Group 4: Diagnostics (100–85) ---

// Logger — Symfony Profiler style with multiple channels, levels, file/line info
//
// Timestamps spread across the request lifecycle (198ms total):
//   +0ms    debug   — Route matched (during template_redirect)
//   +5ms    deprecation — mysql_connect (during plugins_loaded)
//   +18ms   deprecation — get_bloginfo (during init)
//   +25ms   deprecation — login_headertitle hook (during init)
//   +42ms   info    — User login (during init)
//   +68ms   warning — Undefined array key (during template rendering)
//   +75ms   notice  — Undefined variable (during template rendering)
//   +82ms   warning — Rate limit (during wp_head HTTP call)
//   +105ms  info    — Cache cleared (during template rendering)
//   +130ms  info    — Email sent (during wp_footer)
//   +145ms  error   — Payment failure (during wp_footer)
//   +160ms  debug   — Template resolved (during wp_footer)
//
$logBaseTime = $requestTimeFloat + 0.005;
$collectors[] = new FakeCollector('logger', 'Logger', '15', 'red', [
    'total_count' => 15,
    'error_count' => 1,
    'deprecation_count' => 3,
    'logs' => [
        ['level' => 'debug', 'message' => 'Route matched: single.html', 'context' => [], 'channel' => 'routing', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime],
        ['level' => 'deprecation', 'message' => 'Function mysql_connect() is deprecated', 'context' => ['_type' => 'deprecation', '_error_type' => 'E_DEPRECATED'], 'channel' => 'plugin:legacy-plugin', 'file' => '/var/www/html/wp-content/plugins/legacy-plugin/legacy-db.php', 'line' => 15, 'timestamp' => $logBaseTime + 0.005],
        ['level' => 'deprecation', 'message' => 'get_bloginfo(\'url\') is deprecated since version 3.0. Use home_url() instead.', 'context' => ['_type' => 'deprecation', 'type' => 'deprecation', 'function' => 'get_bloginfo', 'replacement' => 'home_url', 'version' => '3.0'], 'channel' => 'wordpress', 'file' => '/var/www/html/wp-includes/functions.php', 'line' => 42, 'timestamp' => $logBaseTime + 0.018],
        ['level' => 'deprecation', 'message' => 'Hook \'login_headertitle\' is deprecated since version 5.2. Use login_headertext instead.', 'context' => ['_type' => 'deprecation', 'type' => 'deprecated_hook', 'hook' => 'login_headertitle', 'replacement' => 'login_headertext', 'version' => '5.2'], 'channel' => 'wordpress', 'file' => '/var/www/html/wp-includes/pluggable.php', 'line' => 128, 'timestamp' => $logBaseTime + 0.025],
        ['level' => 'info', 'message' => 'User "admin" logged in from 192.168.1.100', 'context' => ['user' => 'admin', 'ip' => '192.168.1.100'], 'channel' => 'security', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.042],
        ['level' => 'warning', 'message' => 'Undefined array key "thumbnail" in template', 'context' => ['_error_type' => 'E_WARNING'], 'channel' => 'theme:flavor', 'file' => '/var/www/html/wp-content/themes/flavor/template.php', 'line' => 67, 'timestamp' => $logBaseTime + 0.068],
        ['level' => 'notice', 'message' => 'Undefined variable $sidebar in archive template', 'context' => ['_error_type' => 'E_NOTICE'], 'channel' => 'theme:flavor', 'file' => '/var/www/html/wp-content/themes/flavor/archive.php', 'line' => 34, 'timestamp' => $logBaseTime + 0.075],
        ['level' => 'warning', 'message' => 'Rate limit approaching for API key: 95% of 1000 req/min quota used', 'context' => ['current_usage' => 950, 'limit' => 1000, 'window' => '1min'], 'channel' => 'plugin:wppack', 'file' => '/var/www/html/wp-content/plugins/wppack/src/Component/HttpClient/ApiClient.php', 'line' => 203, 'timestamp' => $logBaseTime + 0.082],
        ['level' => 'info', 'message' => 'Cache cleared for post_id=42', 'context' => ['post_id' => 42], 'channel' => 'app', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.105],
        ['level' => 'notice', 'message' => 'Widget "recent_posts" rendered with empty data', 'context' => ['widget_id' => 'recent_posts'], 'channel' => 'theme:flavor', 'file' => '/var/www/html/wp-content/themes/flavor/widgets/recent-posts.php', 'line' => 18, 'timestamp' => $logBaseTime + 0.115],
        ['level' => 'info', 'message' => 'Email sent to user@example.com (Welcome to Our Site!)', 'context' => [], 'channel' => 'mailer', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.130],
        ['level' => 'warning', 'message' => 'WooCommerce checkout field "billing_phone" validation skipped', 'context' => ['field' => 'billing_phone'], 'channel' => 'plugin:woocommerce', 'file' => '/var/www/html/wp-content/plugins/woocommerce/includes/class-wc-checkout.php', 'line' => 312, 'timestamp' => $logBaseTime + 0.138],
        ['level' => 'error', 'message' => 'Failed to process payment for order #1042: Gateway timeout', 'context' => ['order_id' => 1042, 'gateway' => 'stripe', 'error_code' => 'timeout'], 'channel' => 'plugin:woocommerce', 'file' => '/var/www/html/wp-content/plugins/woocommerce/includes/gateways/stripe/class-wc-stripe.php', 'line' => 89, 'timestamp' => $logBaseTime + 0.145],
        ['level' => 'debug', 'message' => 'Akismet spam check: comment #587 is ham', 'context' => ['comment_id' => 587, 'result' => 'ham'], 'channel' => 'plugin:akismet', 'file' => '/var/www/html/wp-content/plugins/akismet/class.akismet.php', 'line' => 156, 'timestamp' => $logBaseTime + 0.155],
        ['level' => 'debug', 'message' => 'Template resolved: single-post.php', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.160],
    ],
    'level_counts' => ['error' => 1, 'deprecation' => 3, 'warning' => 3, 'notice' => 2, 'info' => 3, 'debug' => 3],
    'channel_counts' => ['plugin:woocommerce' => 2, 'theme:flavor' => 3, 'wordpress' => 2, 'app' => 2, 'plugin:wppack' => 1, 'plugin:legacy-plugin' => 1, 'plugin:akismet' => 1, 'security' => 1, 'routing' => 1, 'mailer' => 1],
]);

// Environment
$collectors[] = new FakeCollector('environment', 'Environment', '', 'default', [
    'php' => [
        'version' => '8.3.15',
        'major_minor' => '8.3',
        'zts' => false,
        'debug' => false,
        'gc_enabled' => true,
        'zend_version' => '4.3.15',
    ],
    'extensions' => [
        'apcu', 'bcmath', 'calendar', 'Core', 'ctype', 'curl', 'date', 'dom',
        'exif', 'fileinfo', 'filter', 'gd', 'hash', 'iconv', 'igbinary',
        'intl', 'json', 'libxml', 'mbstring', 'memcached', 'msgpack', 'mysqli',
        'mysqlnd', 'openssl', 'pcntl', 'pcre', 'PDO', 'pdo_mysql', 'pdo_sqlite',
        'Phar', 'posix', 'readline', 'redis', 'Reflection', 'session', 'SimpleXML',
        'sockets', 'sodium', 'SPL', 'sqlite3', 'standard', 'tokenizer', 'xml',
        'xmlreader', 'xmlwriter', 'xsl', 'Zend OPcache', 'zip', 'zlib',
    ],
    'ini' => [
        'memory_limit' => '256M',
        'max_execution_time' => '30',
        'max_input_time' => '60',
        'post_max_size' => '64M',
        'upload_max_filesize' => '64M',
        'max_file_uploads' => '20',
        'max_input_vars' => '1000',
        'default_charset' => 'UTF-8',
        'date.timezone' => 'Asia/Tokyo',
        'display_errors' => '1',
        'error_reporting' => '32767',
        'log_errors' => '1',
        'error_log' => '/var/log/php/error.log',
        'session.gc_maxlifetime' => '1440',
        'session.cookie_lifetime' => '0',
        'session.cookie_secure' => '1',
        'session.cookie_httponly' => '1',
        'realpath_cache_size' => '4096K',
        'realpath_cache_ttl' => '120',
        'allow_url_fopen' => '1',
        'disable_functions' => 'exec,passthru,shell_exec,system,proc_open,popen',
    ],
    'opcache' => [
        'enabled' => true,
        'jit' => true,
        'used_memory' => 78643200,
        'free_memory' => 55574528,
        'wasted_percentage' => 1.2,
        'cached_scripts' => 423,
        'hit_rate' => 98.7,
        'oom_restarts' => 0,
    ],
    'server' => [
        'software' => 'nginx/1.24.0',
        'web_server' => ['name' => 'Nginx', 'version' => '1.24.0', 'raw' => 'nginx/1.24.0'],
        'name' => 'example.com',
        'addr' => '10.0.0.1',
        'port' => '443',
        'protocol' => 'HTTP/2.0',
        'document_root' => '/var/www/html',
    ],
    'runtime' => [
        'type' => 'ecs',
        'details' => [
            'Launch Type' => 'Fargate',
            'Region' => 'ap-northeast-1',
        ],
    ],
    'sapi' => 'fpm-fcgi',
    'os' => 'Linux',
    'architecture' => 64,
    'hostname' => 'web-prod-01',
]);

// Dump
$collectors[] = new FakeCollector('dump', 'Dump', '3', 'yellow', [
    'total_count' => 3,
    'dumps' => [
        [
            'data' => "array(2) {\n  [\"title\"] => string(11) \"Hello World\"\n  [\"status\"] => string(7) \"publish\"\n}",
            'file' => '/var/www/html/wp-content/themes/flavor/functions.php',
            'line' => 42,
        ],
        [
            'data' => "WP_Post Object\n(\n    [ID] => 42\n    [post_author] => \"1\"\n    [post_date] => \"2024-03-15 10:30:00\"\n    [post_title] => \"Hello World\"\n    [post_status] => \"publish\"\n    [post_type] => \"post\"\n    [comment_count] => \"3\"\n)",
            'file' => '/var/www/html/wp-content/themes/flavor/single.php',
            'line' => 18,
        ],
        [
            'data' => "string(45) \"SELECT * FROM wp_posts WHERE post_status = 'publish' ORDER BY post_date DESC LIMIT 10\"",
            'file' => '/var/www/html/wp-content/plugins/wppack/src/Component/Query/QueryBuilder.php',
            'line' => 156,
        ],
    ],
]);

// Mail (enhanced with structured headers and attachment details)
$collectors[] = new FakeCollector('mail', 'Mail', '2', 'red', [
    'total_count' => 2,
    'success_count' => 1,
    'failure_count' => 1,
    'emails' => [
        [
            'to' => 'a***@example.com',
            'subject' => 'Welcome to Our Site!',
            'headers' => 'From: noreply@example.com',
            'message' => "Hello Alice,\n\nWelcome to our site! We're glad to have you.\n\nBest regards,\nThe Team",
            'attachments' => ['/var/www/html/wp-content/uploads/welcome.pdf'],
            'status' => 'sent',
            'error' => '',
            'from' => 'noreply@example.com',
            'cc' => [],
            'bcc' => ['a***@internal.com'],
            'reply_to' => 'support@example.com',
            'content_type' => 'text/html',
            'charset' => 'UTF-8',
            'attachment_details' => [
                ['filename' => 'welcome.pdf', 'size' => 245760],
            ],
            'start' => $requestTimeFloat + 0.105,
            'duration' => 8.4,
        ],
        [
            'to' => 'b***@example.com',
            'subject' => 'Password Reset Request',
            'headers' => '',
            'message' => "A password reset was requested for your account.\n\nIf you did not request this, please ignore this email.",
            'attachments' => [],
            'status' => 'failed',
            'error' => 'SMTP connection failed: Connection timed out',
            'from' => 'noreply@example.com',
            'cc' => [],
            'bcc' => [],
            'reply_to' => '',
            'content_type' => 'text/plain',
            'charset' => 'UTF-8',
            'attachment_details' => [],
            'start' => $requestTimeFloat + 0.148,
            'duration' => 12.1,
        ],
    ],
]);

// Security (replaces User — adds nonce tracking)
$collectors[] = new FakeCollector('security', 'Security', 'admin', 'default', [
    'is_logged_in' => true,
    'user_id' => 1,
    'username' => 'admin',
    'display_name' => 'Site Administrator',
    'email' => '***@example.com',
    'roles' => ['administrator'],
    'capabilities' => ['manage_options' => true, 'edit_posts' => true, 'delete_posts' => true, 'publish_posts' => true, 'edit_pages' => true],
    'authentication' => 'cookie',
    'is_super_admin' => false,
    'nonce_operations' => [
        ['action' => 'wp_rest', 'operation' => 'verify', 'result' => true, 'timestamp' => $requestTimeFloat + 0.050],
        ['action' => 'save-post_42', 'operation' => 'verify', 'result' => true, 'timestamp' => $requestTimeFloat + 0.085],
    ],
    'nonce_verify_count' => 2,
    'nonce_verify_failures' => 0,
]);

// Widget
$collectors[] = new FakeCollector('widget', 'Widget', '8', 'default', [
    'sidebars' => [
        'sidebar-1' => [
            'name' => 'Main Sidebar',
            'widgets' => ['Search', 'Recent Posts', 'Categories', 'Archives'],
        ],
        'footer-1' => [
            'name' => 'Footer 1',
            'widgets' => ['Text', 'Custom HTML'],
        ],
        'footer-2' => [
            'name' => 'Footer 2',
            'widgets' => ['Navigation Menu', 'Meta'],
        ],
    ],
    'total_widgets' => 8,
    'total_sidebars' => 3,
    'active_widgets' => 8,
    'render_time' => 12.45,
    'sidebar_timings' => [
        ['sidebar' => 'sidebar-1', 'name' => 'Main Sidebar', 'start' => $requestTimeFloat + 0.155, 'duration' => 6.82],
        ['sidebar' => 'footer-1', 'name' => 'Footer 1', 'start' => $requestTimeFloat + 0.170, 'duration' => 3.21],
        ['sidebar' => 'footer-2', 'name' => 'Footer 2', 'start' => $requestTimeFloat + 0.178, 'duration' => 2.42],
    ],
]);

// Shortcode
$collectors[] = new FakeCollector('shortcode', 'Shortcode', '12', 'default', [
    'shortcodes' => [
        'gallery' => ['tag' => 'gallery', 'callback' => 'gallery_shortcode', 'used' => true],
        'caption' => ['tag' => 'caption', 'callback' => 'img_caption_shortcode', 'used' => false],
        'embed' => ['tag' => 'embed', 'callback' => 'WP_Embed::run_shortcode', 'used' => false],
        'audio' => ['tag' => 'audio', 'callback' => 'wp_audio_shortcode', 'used' => false],
        'video' => ['tag' => 'video', 'callback' => 'wp_video_shortcode', 'used' => false],
        'playlist' => ['tag' => 'playlist', 'callback' => 'wp_playlist_shortcode', 'used' => false],
        'woocommerce_cart' => ['tag' => 'woocommerce_cart', 'callback' => 'WC_Shortcodes::cart', 'used' => false],
        'woocommerce_checkout' => ['tag' => 'woocommerce_checkout', 'callback' => 'WC_Shortcodes::checkout', 'used' => false],
        'contact-form-7' => ['tag' => 'contact-form-7', 'callback' => 'WPCF7_ContactForm::shortcode', 'used' => true],
        'wpcf7' => ['tag' => 'wpcf7', 'callback' => 'WPCF7_ContactForm::shortcode', 'used' => false],
        'product' => ['tag' => 'product', 'callback' => 'WC_Shortcodes::product', 'used' => false],
        'products' => ['tag' => 'products', 'callback' => 'WC_Shortcodes::products', 'used' => false],
    ],
    'total_count' => 12,
    'used_count' => 2,
    'used_shortcodes' => ['gallery', 'contact-form-7'],
    'execution_time' => 8.24,
    'executions' => [
        ['tag' => 'gallery', 'start' => $requestTimeFloat + 0.130, 'duration' => 5.12],
        ['tag' => 'contact-form-7', 'start' => $requestTimeFloat + 0.142, 'duration' => 3.12],
    ],
]);

// Asset (Scripts & Styles)
$collectors[] = new FakeCollector('asset', 'Assets', '18', 'default', [
    'scripts' => [
        'jquery-core' => ['handle' => 'jquery-core', 'src' => '/wp-includes/js/jquery/jquery.min.js', 'version' => '3.7.1', 'in_footer' => false, 'deps' => [], 'enqueued' => true],
        'jquery-migrate' => ['handle' => 'jquery-migrate', 'src' => '/wp-includes/js/jquery/jquery-migrate.min.js', 'version' => '3.4.1', 'in_footer' => false, 'deps' => ['jquery-core'], 'enqueued' => true],
        'wp-embed' => ['handle' => 'wp-embed', 'src' => '/wp-includes/js/wp-embed.min.js', 'version' => '6.7.1', 'in_footer' => true, 'deps' => [], 'enqueued' => true],
        'wc-cart-fragments' => ['handle' => 'wc-cart-fragments', 'src' => '/wp-content/plugins/woocommerce/assets/js/frontend/cart-fragments.min.js', 'version' => '8.5.0', 'in_footer' => true, 'deps' => ['jquery'], 'enqueued' => true],
        'contact-form-7' => ['handle' => 'contact-form-7', 'src' => '/wp-content/plugins/contact-form-7/includes/js/index.js', 'version' => '5.9', 'in_footer' => true, 'deps' => [], 'enqueued' => true],
        'flavor-main' => ['handle' => 'flavor-main', 'src' => '/wp-content/themes/flavor/assets/js/main.js', 'version' => '2.1.0', 'in_footer' => true, 'deps' => ['jquery'], 'enqueued' => true],
        'akismet-form' => ['handle' => 'akismet-form', 'src' => '/wp-content/plugins/akismet/akismet-frontend.js', 'version' => '5.3', 'in_footer' => true, 'deps' => [], 'enqueued' => true],
        'jquery' => ['handle' => 'jquery', 'src' => '', 'version' => '3.7.1', 'in_footer' => false, 'deps' => ['jquery-core', 'jquery-migrate'], 'enqueued' => true],
        'woocommerce' => ['handle' => 'woocommerce', 'src' => '/wp-content/plugins/woocommerce/assets/js/frontend/woocommerce.min.js', 'version' => '8.5.0', 'in_footer' => true, 'deps' => ['jquery'], 'enqueued' => true],
        'wc-add-to-cart' => ['handle' => 'wc-add-to-cart', 'src' => '/wp-content/plugins/woocommerce/assets/js/frontend/add-to-cart.min.js', 'version' => '8.5.0', 'in_footer' => true, 'deps' => ['jquery'], 'enqueued' => true],
        'yoast-seo-adminbar' => ['handle' => 'yoast-seo-adminbar', 'src' => '/wp-content/plugins/wordpress-seo/js/dist/adminbar.js', 'version' => '22.0', 'in_footer' => true, 'deps' => [], 'enqueued' => true],
        'wpcf7-recaptcha' => ['handle' => 'wpcf7-recaptcha', 'src' => '/wp-content/plugins/contact-form-7/modules/recaptcha/index.js', 'version' => '5.9', 'in_footer' => true, 'deps' => [], 'enqueued' => true],
    ],
    'styles' => [
        'wp-block-library' => ['handle' => 'wp-block-library', 'src' => '/wp-includes/css/dist/block-library/style.min.css', 'version' => '6.7.1', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'woocommerce-layout' => ['handle' => 'woocommerce-layout', 'src' => '/wp-content/plugins/woocommerce/assets/css/woocommerce-layout.css', 'version' => '8.5.0', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'woocommerce-general' => ['handle' => 'woocommerce-general', 'src' => '/wp-content/plugins/woocommerce/assets/css/woocommerce.css', 'version' => '8.5.0', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'contact-form-7' => ['handle' => 'contact-form-7', 'src' => '/wp-content/plugins/contact-form-7/includes/css/styles.css', 'version' => '5.9', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'flavor-style' => ['handle' => 'flavor-style', 'src' => '/wp-content/themes/flavor/style.css', 'version' => '2.1.0', 'media' => 'all', 'deps' => ['wp-block-library'], 'enqueued' => true],
        'flavor-child-style' => ['handle' => 'flavor-child-style', 'src' => '/wp-content/themes/flavor-child/style.css', 'version' => '1.0.0', 'media' => 'all', 'deps' => ['flavor-style'], 'enqueued' => true],
        'yoast-seo-adminbar' => ['handle' => 'yoast-seo-adminbar', 'src' => '/wp-content/plugins/wordpress-seo/css/dist/adminbar.css', 'version' => '22.0', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'dashicons' => ['handle' => 'dashicons', 'src' => '/wp-includes/css/dashicons.min.css', 'version' => '6.7.1', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'admin-bar' => ['handle' => 'admin-bar', 'src' => '/wp-includes/css/admin-bar.min.css', 'version' => '6.7.1', 'media' => 'all', 'deps' => ['dashicons'], 'enqueued' => true],
        'wp-block-library-theme' => ['handle' => 'wp-block-library-theme', 'src' => '/wp-includes/css/dist/block-library/theme.min.css', 'version' => '6.7.1', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'global-styles' => ['handle' => 'global-styles', 'src' => '', 'version' => '6.7.1', 'media' => 'all', 'deps' => [], 'enqueued' => true],
        'woocommerce-smallscreen' => ['handle' => 'woocommerce-smallscreen', 'src' => '/wp-content/plugins/woocommerce/assets/css/woocommerce-smallscreen.css', 'version' => '8.5.0', 'media' => 'only screen and (max-width: 768px)', 'deps' => ['woocommerce-general'], 'enqueued' => true],
    ],
    'enqueued_scripts' => 12,
    'enqueued_styles' => 12,
    'registered_scripts' => 47,
    'registered_styles' => 36,
]);

// REST API — simulating a REST request to GET /wp/v2/posts/42
$collectors[] = new FakeCollector('rest', 'REST API', '', 'green', [
    'is_rest_request' => true,
    'current_request' => [
        'method' => 'GET',
        'route' => '/wp/v2/posts/(?P<id>[\\d]+)',
        'path' => '/wp/v2/posts/42',
        'namespace' => 'wp/v2',
        'callback' => 'WP_REST_Posts_Controller::get_item',
        'params' => ['id' => '42', '_embed' => 'true', 'context' => 'view'],
        'status' => 200,
        'authentication' => 'nonce',
    ],
    'routes' => [
        'wp/v2' => [
            ['route' => '/wp/v2/posts', 'methods' => ['GET', 'POST'], 'callback' => 'WP_REST_Posts_Controller::get_items'],
            ['route' => '/wp/v2/posts/(?P<id>[\\d]+)', 'methods' => ['GET', 'PUT', 'PATCH', 'DELETE'], 'callback' => 'WP_REST_Posts_Controller::get_item'],
            ['route' => '/wp/v2/pages', 'methods' => ['GET', 'POST'], 'callback' => 'WP_REST_Posts_Controller::get_items'],
            ['route' => '/wp/v2/media', 'methods' => ['GET', 'POST'], 'callback' => 'WP_REST_Attachments_Controller::get_items'],
            ['route' => '/wp/v2/users', 'methods' => ['GET'], 'callback' => 'WP_REST_Users_Controller::get_items'],
        ],
        'wc/v3' => [
            ['route' => '/wc/v3/products', 'methods' => ['GET', 'POST'], 'callback' => 'WC_REST_Products_Controller::get_items'],
            ['route' => '/wc/v3/orders', 'methods' => ['GET', 'POST'], 'callback' => 'WC_REST_Orders_Controller::get_items'],
            ['route' => '/wc/v3/customers', 'methods' => ['GET'], 'callback' => 'WC_REST_Customers_Controller::get_items'],
        ],
    ],
    'namespaces' => ['wp/v2', 'wc/v3', 'wp-site-health/v1', 'wp/v2/block-renderer'],
    'total_routes' => 68,
    'total_namespaces' => 4,
]);

// Ajax
$collectors[] = new FakeCollector('ajax', 'Ajax', '0', 'default', [
    'registered_actions' => [
        'heartbeat' => ['callback' => 'wp_ajax_heartbeat', 'nopriv' => false],
        'query-attachments' => ['callback' => 'wp_ajax_query_attachments', 'nopriv' => false],
        'save-attachment' => ['callback' => 'wp_ajax_save_attachment', 'nopriv' => false],
        'woocommerce_apply_coupon' => ['callback' => 'WC_AJAX::apply_coupon', 'nopriv' => true],
        'woocommerce_get_refreshed_fragments' => ['callback' => 'WC_AJAX::get_refreshed_fragments', 'nopriv' => true],
        'cf7_submit' => ['callback' => 'WPCF7_ContactForm::ajax_submit', 'nopriv' => true],
    ],
    'total_actions' => 6,
    'nopriv_count' => 3,
]);

// Admin
$collectors[] = new FakeCollector('admin', 'Admin', 'edit-post', 'default', [
    'is_admin' => true,
    'page_hook' => 'edit.php',
    'screen' => ['id' => 'edit-post', 'base' => 'edit', 'post_type' => 'post', 'taxonomy' => ''],
    'admin_menus' => [
        ['title' => 'Dashboard', 'slug' => 'index.php', 'capability' => 'read'],
        ['title' => 'Posts', 'slug' => 'edit.php', 'capability' => 'edit_posts', 'submenu' => [
            ['title' => 'All Posts', 'slug' => 'edit.php'],
            ['title' => 'Add New', 'slug' => 'post-new.php'],
            ['title' => 'Categories', 'slug' => 'edit-tags.php?taxonomy=category'],
            ['title' => 'Tags', 'slug' => 'edit-tags.php?taxonomy=post_tag'],
        ]],
        ['title' => 'Media', 'slug' => 'upload.php', 'capability' => 'upload_files'],
        ['title' => 'Pages', 'slug' => 'edit.php?post_type=page', 'capability' => 'edit_pages'],
        ['title' => 'Appearance', 'slug' => 'themes.php', 'capability' => 'switch_themes', 'submenu' => [
            ['title' => 'Themes', 'slug' => 'themes.php'],
            ['title' => 'Editor', 'slug' => 'site-editor.php'],
        ]],
        ['title' => 'Plugins', 'slug' => 'plugins.php', 'capability' => 'activate_plugins'],
        ['title' => 'Settings', 'slug' => 'options-general.php', 'capability' => 'manage_options', 'submenu' => [
            ['title' => 'General', 'slug' => 'options-general.php'],
            ['title' => 'Writing', 'slug' => 'options-writing.php'],
            ['title' => 'Reading', 'slug' => 'options-reading.php'],
        ]],
    ],
    'admin_bar_nodes' => [
        ['id' => 'wp-logo', 'title' => 'WordPress'],
        ['id' => 'site-name', 'title' => 'Example Site'],
        ['id' => 'new-content', 'title' => 'New'],
        ['id' => 'my-account', 'title' => 'Howdy, admin'],
    ],
    'total_menus' => 7,
    'total_submenus' => 9,
]);

// Container (DI)
$collectors[] = new FakeCollector('container', 'Container', '24', 'default', [
    'service_count' => 24,
    'public_count' => 8,
    'private_count' => 16,
    'autowired_count' => 20,
    'lazy_count' => 3,
    'services' => [
        'app.mailer' => ['class' => 'WpPack\\Component\\Mailer\\Mailer', 'public' => true, 'autowired' => true, 'lazy' => false, 'tags' => []],
        'app.cache' => ['class' => 'WpPack\\Component\\Cache\\CachePool', 'public' => true, 'autowired' => true, 'lazy' => true, 'tags' => ['cache.pool']],
        'app.event_dispatcher' => ['class' => 'WpPack\\Component\\EventDispatcher\\EventDispatcher', 'public' => true, 'autowired' => true, 'lazy' => false, 'tags' => []],
        'app.http_client' => ['class' => 'WpPack\\Component\\HttpClient\\HttpClient', 'public' => true, 'autowired' => true, 'lazy' => true, 'tags' => ['http_client']],
        'app.logger' => ['class' => 'WpPack\\Component\\Logger\\Logger', 'public' => true, 'autowired' => true, 'lazy' => false, 'tags' => ['monolog.logger']],
    ],
    'compiler_passes' => [
        'WpPack\\Component\\DependencyInjection\\CompilerPass\\RegisterHooksPass',
        'WpPack\\Component\\DependencyInjection\\CompilerPass\\AutowirePass',
        'WpPack\\Component\\DependencyInjection\\CompilerPass\\ResolveTaggedPass',
    ],
    'tagged_services' => [
        'cache.pool' => ['app.cache'],
        'http_client' => ['app.http_client'],
        'monolog.logger' => ['app.logger'],
        'event_subscriber' => ['app.wc_subscriber', 'app.seo_subscriber'],
    ],
    'parameters' => [
        'kernel.environment' => 'development',
        'kernel.debug' => true,
        'mailer.dsn' => 'ses+api://default',
    ],
]);

// Feed
$collectors[] = new FakeCollector('feed', 'Feed', '5', 'default', [
    'feeds' => [
        ['type' => 'rss2', 'url' => 'https://example.local/feed/', 'is_custom' => false],
        ['type' => 'atom', 'url' => 'https://example.local/feed/atom/', 'is_custom' => false],
        ['type' => 'rdf', 'url' => 'https://example.local/feed/rdf/', 'is_custom' => false],
        ['type' => 'comments-rss2', 'url' => 'https://example.local/comments/feed/', 'is_custom' => false],
        ['type' => 'product-feed', 'url' => 'https://example.local/feed/product-feed/', 'is_custom' => true],
    ],
    'total_count' => 5,
    'custom_count' => 1,
    'feed_discovery' => true,
]);

// --- Group 5: Environment (50–40) ---

// Scheduler
$collectors[] = new FakeCollector('scheduler', 'Scheduler', '5', 'default', [
    'cron_events' => [
        ['hook' => 'wp_scheduled_delete', 'schedule' => 'daily', 'next_run' => time() + 7200, 'next_run_relative' => 'in 2 hours', 'is_overdue' => false, 'callbacks' => 1],
        ['hook' => 'wp_update_plugins', 'schedule' => 'twicedaily', 'next_run' => time() + 3600, 'next_run_relative' => 'in 1 hour', 'is_overdue' => false, 'callbacks' => 1],
        ['hook' => 'wp_update_themes', 'schedule' => 'twicedaily', 'next_run' => time() + 3600, 'next_run_relative' => 'in 1 hour', 'is_overdue' => false, 'callbacks' => 1],
        ['hook' => 'wc_cleanup_sessions', 'schedule' => 'twicedaily', 'next_run' => time() - 600, 'next_run_relative' => '10 minutes ago', 'is_overdue' => true, 'callbacks' => 1],
        ['hook' => 'wp_privacy_delete_old_export_files', 'schedule' => 'hourly', 'next_run' => time() + 1800, 'next_run_relative' => 'in 30 minutes', 'is_overdue' => false, 'callbacks' => 1],
    ],
    'cron_total' => 5,
    'cron_overdue' => 1,
    'action_scheduler_available' => true,
    'action_scheduler_version' => '3.7.0',
    'as_pending' => 5,
    'as_failed' => 1,
    'as_complete' => 120,
    'as_recent_actions' => [],
    'cron_disabled' => false,
    'alternate_cron' => false,
]);

// Translation
$collectors[] = new FakeCollector('translation', 'Translation', '2', 'yellow', [
    'missing_count' => 2,
    'total_lookups' => 156,
    'loaded_domains' => ['default', 'flavor', 'woocommerce'],
    'domain_usage' => ['default' => 98, 'flavor' => 32, 'woocommerce' => 26],
    'missing_translations' => [
        ['original' => 'Read more...', 'domain' => 'flavor'],
        ['original' => 'Share this post', 'domain' => 'flavor'],
    ],
]);

// WordPress
$collectors[] = new FakeCollector('wordpress', 'WordPress', '6.7.1', 'default', [
    'wp_version' => '6.7.1',
    'php_version' => PHP_VERSION,
    'theme' => 'Flavor',
    'is_block_theme' => true,
    'is_child_theme' => true,
    'child_theme' => 'flavor-child',
    'parent_theme' => 'flavor',
    'theme_version' => '2.1.0',
    'environment_type' => 'development',
    'is_multisite' => false,
    'mu_plugins' => [
        'wppack-loader.php' => 'WpPack Loader',
        'redis-cache-dropin.php' => 'Redis Object Cache Drop-in',
    ],
    'active_plugins' => [
        'woocommerce/woocommerce.php' => 'WooCommerce',
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'akismet/akismet.php' => 'Akismet Anti-spam',
        'contact-form-7/wp-contact-form-7.php' => 'Contact Form 7',
    ],
    'extensions' => get_loaded_extensions(),
    'constants' => [
        'WP_DEBUG' => true,
        'SAVEQUERIES' => true,
        'SCRIPT_DEBUG' => true,
        'WP_DEBUG_LOG' => true,
        'WP_DEBUG_DISPLAY' => false,
        'WP_CACHE' => false,
    ],
]);

// --- Build profile and render ---

$profile = new Profile(token: 'demo-' . bin2hex(random_bytes(4)));
$profile->setUrl('/2024/03/hello-world/');
$profile->setMethod('GET');
$profile->setStatusCode(200);

foreach ($collectors as $collector) {
    $collector->collect();
    $profile->addCollector($collector);
}

$renderer = new ToolbarRenderer($profile);
$renderer->addPanelRenderer(new DatabasePanelRenderer($profile));
$renderer->addPanelRenderer(new StopwatchPanelRenderer($profile));
$renderer->addPanelRenderer(new PerformancePanelRenderer($profile));
$renderer->addPanelRenderer(new MemoryPanelRenderer($profile));
$renderer->addPanelRenderer(new RequestPanelRenderer($profile));
$renderer->addPanelRenderer(new CachePanelRenderer($profile));
$renderer->addPanelRenderer(new WordPressPanelRenderer($profile));
$renderer->addPanelRenderer(new SecurityPanelRenderer($profile));
$renderer->addPanelRenderer(new MailPanelRenderer($profile));
$renderer->addPanelRenderer(new EventPanelRenderer($profile));
$renderer->addPanelRenderer(new LoggerPanelRenderer($profile));
$renderer->addPanelRenderer(new RouterPanelRenderer($profile));
$renderer->addPanelRenderer(new HttpClientPanelRenderer($profile));
$renderer->addPanelRenderer(new TranslationPanelRenderer($profile));
$renderer->addPanelRenderer(new DumpPanelRenderer($profile));
$renderer->addPanelRenderer(new PluginPanelRenderer($profile));
$renderer->addPanelRenderer(new ThemePanelRenderer($profile));
$renderer->addPanelRenderer(new SchedulerPanelRenderer($profile));
$renderer->addPanelRenderer(new WidgetPanelRenderer($profile));
$renderer->addPanelRenderer(new ShortcodePanelRenderer($profile));
$renderer->addPanelRenderer(new AssetPanelRenderer($profile));
$renderer->addPanelRenderer(new RestPanelRenderer($profile));
$renderer->addPanelRenderer(new AjaxPanelRenderer($profile));
$renderer->addPanelRenderer(new AdminPanelRenderer($profile));
$renderer->addPanelRenderer(new ContainerPanelRenderer($profile));
$renderer->addPanelRenderer(new FeedPanelRenderer($profile));
$renderer->addPanelRenderer(new EnvironmentPanelRenderer($profile));
$html = $renderer->render();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WpPack Debug Toolbar Demo</title>
<style>
body {
    margin: 0;
    padding: 40px 20px 120px;
    background: #f5f5f5;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #333;
}
.demo-content {
    max-width: 800px;
    margin: 0 auto;
}
h1 { font-size: 24px; }
p { line-height: 1.8; color: #666; }
.card {
    background: #fff;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
</style>
</head>
<body>
<div class="demo-content">
    <h1>WpPack Debug Toolbar Demo</h1>
    <div class="card">
        <h2>Hello World</h2>
        <p>This is a demo page showing the WpPack Debug toolbar with sample data.
        Click any indicator in the toolbar below to expand the corresponding panel.</p>
        <p>This demo simulates a FSE (block theme) environment with the "Flavor" theme,
        showing block template detection, template parts, and all data collectors.</p>
    </div>
    <div class="card">
        <h3>Sample Content</h3>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor
        incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
        exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
    </div>
</div>
<?= $html ?>
</body>
</html>
