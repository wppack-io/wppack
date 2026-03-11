<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use WpPack\Component\Debug\DataCollector\AbstractDataCollector;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EventPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\GenericPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\HttpClientPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MailPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PanelRendererInterface;
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PluginPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RouterPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SchedulerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ThemePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TimePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\UserPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ToolbarIcons;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;

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
        private readonly string $fakeBadgeValue,
        private readonly string $fakeBadgeColor,
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

    public function getBadgeValue(): string
    {
        return $this->fakeBadgeValue;
    }

    public function getBadgeColor(): string
    {
        return $this->fakeBadgeColor;
    }

    public function collect(): void
    {
        $this->data = $this->fakeData;
    }
}

// --- Build sample collectors (identical to toolbar.php) ---

$collectors = [];
$requestTimeFloat = microtime(true) - 0.198;

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

$collectors[] = new FakeCollector('time', 'Time', '198 ms', 'green', [
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

$collectors[] = new FakeCollector('memory', 'Memory', '44.0 MB', 'green', [
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

$collectors[] = new FakeCollector('database', 'Database', '24', 'green', [
    'total_count' => 24,
    'total_time' => 12.45,
    'duplicate_count' => 2,
    'slow_count' => 0,
    'savequeries' => true,
    'queries' => [
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 1.23, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.003, 'data' => []],
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 0.98, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.008, 'data' => []],
        ['sql' => 'SELECT * FROM wp_users WHERE ID = 1 LIMIT 1', 'time' => 0.32, 'caller' => 'WP_User::get_data_by', 'start' => $requestTimeFloat + 0.045, 'data' => []],
        ['sql' => 'SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = 1', 'time' => 0.28, 'caller' => 'get_user_meta', 'start' => $requestTimeFloat + 0.048, 'data' => []],
        ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = \'woocommerce_queue_flush_rewrite_rules\'', 'time' => 0.15, 'caller' => 'WC_Post_Types::register_post_types', 'start' => $requestTimeFloat + 0.075, 'data' => []],
        ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = \'woocommerce_db_version\'', 'time' => 0.12, 'caller' => 'WooCommerce::init', 'start' => $requestTimeFloat + 0.080, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE post_name = \'hello-world\' AND post_type = \'post\' LIMIT 1', 'time' => 0.45, 'caller' => 'WP_Query::get_posts', 'start' => $requestTimeFloat + 0.104, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 42 LIMIT 1', 'time' => 0.35, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.106, 'data' => []],
        ['sql' => 'SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = 42', 'time' => 0.25, 'caller' => 'get_post_meta', 'start' => $requestTimeFloat + 0.108, 'data' => []],
        ['sql' => 'SELECT t.*, tt.* FROM wp_terms INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = \'category\' AND t.term_id IN (5)', 'time' => 0.56, 'caller' => 'get_the_terms', 'start' => $requestTimeFloat + 0.135, 'data' => []],
        ['sql' => 'SELECT t.*, tt.* FROM wp_terms INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = \'post_tag\' AND t.term_id IN (8, 12)', 'time' => 0.48, 'caller' => 'get_the_terms', 'start' => $requestTimeFloat + 0.140, 'data' => []],
    ],
    'suggestions' => ['2 duplicate queries detected — consider caching results'],
]);

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

$collectors[] = new FakeCollector('http_client', 'HTTP Client', '2', 'green', [
    'total_count' => 2,
    'total_time' => 20.0,
    'error_count' => 0,
    'slow_count' => 0,
    'requests' => [
        ['method' => 'GET', 'url' => 'https://api.wordpress.org/plugins/update-check/1.1/', 'status_code' => 200, 'duration' => 12.0, 'start' => $requestTimeFloat + 0.073, 'response_size' => 4521, 'error' => ''],
        ['method' => 'POST', 'url' => 'https://wpseo-api.yoast.com/indexables/check', 'status_code' => 200, 'duration' => 8.0, 'start' => $requestTimeFloat + 0.128, 'response_size' => 1280, 'error' => ''],
    ],
]);

$collectors[] = new FakeCollector('router', 'Router', 'single', 'green', [
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

$collectors[] = new FakeCollector('plugin', 'Plugins', '4', 'green', [
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

$collectors[] = new FakeCollector('event', 'Event', '847', 'green', [
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

$logBaseTime = $requestTimeFloat + 0.005;
$collectors[] = new FakeCollector('logger', 'Logger', '12', 'red', [
    'total_count' => 12,
    'error_count' => 1,
    'deprecation_count' => 3,
    'logs' => [
        ['level' => 'debug', 'message' => 'Route matched: single.html', 'context' => [], 'channel' => 'routing', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime],
        ['level' => 'deprecation', 'message' => 'Function mysql_connect() is deprecated', 'context' => ['_error_type' => 'E_DEPRECATED'], 'channel' => 'php', 'file' => '/var/www/html/wp-content/plugins/legacy-plugin/legacy-db.php', 'line' => 15, 'timestamp' => $logBaseTime + 0.005],
        ['level' => 'deprecation', 'message' => 'get_bloginfo(\'url\') is deprecated since version 3.0. Use home_url() instead.', 'context' => ['type' => 'deprecation', 'function' => 'get_bloginfo', 'replacement' => 'home_url', 'version' => '3.0'], 'channel' => 'wordpress', 'file' => '/var/www/html/wp-includes/functions.php', 'line' => 42, 'timestamp' => $logBaseTime + 0.018],
        ['level' => 'deprecation', 'message' => 'Hook \'login_headertitle\' is deprecated since version 5.2. Use login_headertext instead.', 'context' => ['type' => 'deprecated_hook', 'hook' => 'login_headertitle', 'replacement' => 'login_headertext', 'version' => '5.2'], 'channel' => 'wordpress', 'file' => '/var/www/html/wp-includes/pluggable.php', 'line' => 128, 'timestamp' => $logBaseTime + 0.025],
        ['level' => 'info', 'message' => 'User "admin" logged in from 192.168.1.100', 'context' => ['user' => 'admin', 'ip' => '192.168.1.100'], 'channel' => 'security', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.042],
        ['level' => 'warning', 'message' => 'Undefined array key "thumbnail" in template', 'context' => ['_error_type' => 'E_WARNING'], 'channel' => 'php', 'file' => '/var/www/html/wp-content/themes/flavor/template.php', 'line' => 67, 'timestamp' => $logBaseTime + 0.068],
        ['level' => 'notice', 'message' => 'Undefined variable $sidebar in archive template', 'context' => ['_error_type' => 'E_NOTICE'], 'channel' => 'php', 'file' => '/var/www/html/wp-content/themes/flavor/archive.php', 'line' => 34, 'timestamp' => $logBaseTime + 0.075],
        ['level' => 'warning', 'message' => 'Rate limit approaching for API key: 95% of 1000 req/min quota used', 'context' => ['current_usage' => 950, 'limit' => 1000, 'window' => '1min'], 'channel' => 'app', 'file' => '/var/www/html/wp-content/plugins/wppack/src/Component/HttpClient/ApiClient.php', 'line' => 203, 'timestamp' => $logBaseTime + 0.082],
        ['level' => 'info', 'message' => 'Cache cleared for post_id=42', 'context' => ['post_id' => 42], 'channel' => 'app', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.105],
        ['level' => 'info', 'message' => 'Email sent to user@example.com (Welcome to Our Site!)', 'context' => [], 'channel' => 'mailer', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.130],
        ['level' => 'error', 'message' => 'Failed to process payment for order #1042: Gateway timeout', 'context' => ['order_id' => 1042, 'gateway' => 'stripe', 'error_code' => 'timeout'], 'channel' => 'app', 'file' => '/var/www/html/wp-content/plugins/wppack/src/Component/Payment/PaymentGateway.php', 'line' => 89, 'timestamp' => $logBaseTime + 0.145],
        ['level' => 'debug', 'message' => 'Template resolved: single-post.php', 'context' => [], 'channel' => 'app', 'file' => '', 'line' => 0, 'timestamp' => $logBaseTime + 0.160],
    ],
    'level_counts' => ['error' => 1, 'deprecation' => 3, 'warning' => 2, 'notice' => 1, 'info' => 3, 'debug' => 2],
]);

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

$collectors[] = new FakeCollector('mail', 'Mail', '2', 'green', [
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
        ],
    ],
]);

$collectors[] = new FakeCollector('user', 'User', 'admin', 'green', [
    'is_logged_in' => true,
    'user_id' => 1,
    'username' => 'admin',
    'display_name' => 'Site Administrator',
    'email' => '***@example.com',
    'roles' => ['administrator'],
    'capabilities' => ['manage_options' => true, 'edit_posts' => true, 'delete_posts' => true, 'publish_posts' => true, 'edit_pages' => true],
    'auth_method' => 'cookie',
    'is_super_admin' => false,
]);

$collectors[] = new FakeCollector('scheduler', 'Scheduler', '5', 'green', [
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
    'active_plugins' => [
        'woocommerce/woocommerce.php',
        'wordpress-seo/wp-seo.php',
        'akismet/akismet.php',
        'contact-form-7/wp-contact-form-7.php',
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

// --- Build profile ---

$profile = new Profile(token: 'demo-' . bin2hex(random_bytes(4)));
$profile->setUrl('/2024/03/hello-world/');
$profile->setMethod('GET');
$profile->setStatusCode(200);

foreach ($collectors as $collector) {
    $collector->collect();
    $profile->addCollector($collector);
}

// --- Panel renderers ---

$panelRenderers = [
    'database' => new DatabasePanelRenderer(),
    'time' => new TimePanelRenderer(),
    'memory' => new MemoryPanelRenderer(),
    'request' => new RequestPanelRenderer(),
    'cache' => new CachePanelRenderer(),
    'wordpress' => new WordPressPanelRenderer(),
    'user' => new UserPanelRenderer(),
    'mail' => new MailPanelRenderer(),
    'event' => new EventPanelRenderer(),
    'logger' => new LoggerPanelRenderer(),
    'router' => new RouterPanelRenderer(),
    'http_client' => new HttpClientPanelRenderer(),
    'translation' => new TranslationPanelRenderer(),
    'dump' => new DumpPanelRenderer(),
    'plugin' => new PluginPanelRenderer(),
    'theme' => new ThemePanelRenderer(),
    'scheduler' => new SchedulerPanelRenderer(),
];

$genericRenderer = new GenericPanelRenderer();
$performanceRenderer = new PerformancePanelRenderer();

// Extract request_time_float and propagate
$requestTimeFloat = 0.0;
if (isset($profile->getCollectors()['time'])) {
    $timeData = $profile->getCollectors()['time']->getData();
    $requestTimeFloat = (float) ($timeData['request_time_float'] ?? 0.0);
}
foreach ($panelRenderers as $r) {
    $r->setRequestTimeFloat($requestTimeFloat);
}
$performanceRenderer->setRequestTimeFloat($requestTimeFloat);
$genericRenderer->setRequestTimeFloat($requestTimeFloat);

// --- Render panel contents ---

// Performance panel content (special: uses Profile)
$perfContent = $performanceRenderer->renderPanel($profile);
// Extract just the inner body content
if (preg_match('/<div class="wpd-panel-body">(.*)<\/div>\s*<\/div>\s*$/s', $perfContent, $m)) {
    $perfContent = $m[1];
} else {
    $perfContent = '';
}

$panelContents = ['performance' => $perfContent];

foreach ($profile->getCollectors() as $name => $collector) {
    $renderer = $panelRenderers[$name] ?? $genericRenderer;
    $panelContents[$name] = $renderer->render($collector->getData());
}

// --- Panel definitions for sidebar ---

$labels = [
    'performance' => 'Performance',
    'request' => 'Request',
    'time' => 'Time',
    'memory' => 'Memory',
    'database' => 'Database',
    'cache' => 'Cache',
    'http_client' => 'HTTP Client',
    'router' => 'Router',
    'plugin' => 'Plugins',
    'theme' => 'Theme',
    'wordpress' => 'WordPress',
    'event' => 'Events',
    'logger' => 'Logger',
    'dump' => 'Dump',
    'mail' => 'Mail',
    'scheduler' => 'Scheduler',
    'translation' => 'Translation',
    'user' => 'User',
];

$badgeColors = [
    'green' => '#1f2937',
    'yellow' => '#996800',
    'red' => '#cc1818',
    'default' => '#50575e',
];

// Sidebar groups
$sidebarGroups = [
    ['performance'],
    ['request', 'time', 'memory', 'database', 'cache', 'http_client'],
    ['wordpress', 'plugin', 'theme', 'router'],
    ['event', 'logger', 'dump', 'mail', 'scheduler', 'translation', 'user'],
];

// All panel keys in sidebar order
$sidebarOrder = array_merge(...$sidebarGroups);

// --- Helper ---
function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMs(float $ms): string {
    if ($ms >= 1000) {
        return esc(sprintf('%.2f s', $ms / 1000));
    }
    return esc(sprintf('%.1f ms', $ms));
}

// --- Build sidebar HTML ---
$sidebarHtml = '';
$groupIndex = 0;
foreach ($sidebarGroups as $group) {
    if ($groupIndex > 0) {
        $sidebarHtml .= '<div class="wpd-sidebar-divider"></div>';
    }
    foreach ($group as $key) {
        $icon = ToolbarIcons::svg($key, 18);
        $label = esc($labels[$key] ?? $key);
        $sidebarHtml .= '<button class="wpd-sidebar-item" data-panel="' . esc($key) . '">'
            . '<span class="wpd-sidebar-icon">' . $icon . '</span>'
            . '<span class="wpd-sidebar-label">' . $label . '</span>'
            . '</button>';
    }
    $groupIndex++;
}

// --- Build panel content divs ---
$contentDivs = '';
foreach ($sidebarOrder as $key) {
    $content = $panelContents[$key] ?? '<p>No data</p>';
    $display = ($key === 'performance') ? '' : ' style="display:none"';
    $contentDivs .= '<div class="wpd-panel-content" id="wpd-pc-' . esc($key) . '"' . $display . '>' . $content . '</div>';
}

// --- Build badge bar ---
$badgesHtml = '';

// Performance badge first
$totalTime = $profile->getTime();
$perfColor = $totalTime >= 1000 ? $badgeColors['red'] : ($totalTime >= 200 ? $badgeColors['yellow'] : $badgeColors['green']);
$badgesHtml .= '<button class="wpd-badge" data-panel="performance" title="Performance">'
    . '<span class="wpd-badge-icon">' . ToolbarIcons::svg('performance') . '</span>'
    . '<span class="wpd-badge-value" style="color:' . $perfColor . '">' . formatMs($totalTime) . '</span>'
    . '</button>';

foreach ($profile->getCollectors() as $name => $collector) {
    $icon = ToolbarIcons::svg($name);
    $colorKey = $collector->getBadgeColor();
    $color = $badgeColors[$colorKey] ?? $badgeColors['default'];
    $badgesHtml .= '<button class="wpd-badge" data-panel="' . esc($name) . '" title="' . esc($collector->getLabel()) . '">'
        . '<span class="wpd-badge-icon">' . $icon . '</span>'
        . '<span class="wpd-badge-value" style="color:' . $color . '">' . esc($collector->getBadgeValue()) . '</span>'
        . '</button>';
}

$requestInfo = esc($profile->getMethod()) . ' ' . esc((string) $profile->getStatusCode());
$totalTimeFormatted = formatMs($profile->getTime());

// Initial active panel title
$firstName = $labels['performance'] ?? 'Performance';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WpPack Debug Toolbar — L-Shape Demo</title>
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
    <h1>WpPack Debug Toolbar — L-Shape Demo</h1>
    <div class="card">
        <h2>Hello World</h2>
        <p>This is a demo showing the L-shaped layout with left sidebar navigation.
        Click any badge in the toolbar below, or use the sidebar to switch panels.</p>
    </div>
    <div class="card">
        <h3>Sample Content</h3>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor
        incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
        exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
    </div>
</div>

<div id="wppack-debug">
<style>
/* ================================================================
   WpPack Debug Toolbar — L-Shape Layout
   ================================================================ */

/* --- Reset --- */
#wppack-debug *, #wppack-debug *::before, #wppack-debug *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
#wppack-debug {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    font-size: 13px;
    line-height: 1.5;
    color: #1f2937;
    direction: ltr;
    text-align: left;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    z-index: 99999;
}

/* --- Bottom bar --- */
#wppack-debug .wpd-bar {
    display: flex;
    align-items: center;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    height: 40px;
    width: 100%;
    position: relative;
    z-index: 2;
}

/* --- Logo --- */
#wppack-debug .wpd-bar-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #3858e9;
    flex-shrink: 0;
    cursor: default;
}
#wppack-debug .wpd-logo-text {
    font-size: 11px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
}

/* --- Badges container --- */
#wppack-debug .wpd-bar-badges {
    display: flex;
    align-items: center;
    height: 100%;
    flex: 1 1 auto;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
}
#wppack-debug .wpd-bar-badges::-webkit-scrollbar {
    display: none;
}

/* --- Badges --- */
#wppack-debug .wpd-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 0 12px;
    background: transparent;
    border: none;
    border-right: 1px solid #e5e7eb;
    color: #1f2937;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    white-space: nowrap;
    flex-shrink: 0;
    height: 100%;
    transition: background 0.15s ease;
}
#wppack-debug .wpd-badge:last-child {
    border-right: none;
}
#wppack-debug .wpd-badge:hover {
    background: #f3f4f6;
}
#wppack-debug .wpd-badge.wpd-active {
    background: transparent;
    box-shadow: inset 0 -2px 0 #3858e9;
}
#wppack-debug .wpd-badge-icon {
    display: flex;
    align-items: center;
    line-height: 1;
}
#wppack-debug .wpd-icon {
    display: inline-block;
    vertical-align: middle;
    flex-shrink: 0;
}
#wppack-debug .wpd-badge-value {
    font-size: 12px;
    font-weight: 400;
}

/* --- Bar meta --- */
#wppack-debug .wpd-bar-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 0 12px;
    height: 100%;
    border-left: 1px solid #e5e7eb;
}
#wppack-debug .wpd-meta-item {
    font-size: 11px;
    color: #9ca3af;
}
#wppack-debug .wpd-meta-sep {
    color: #d1d5db;
    font-size: 11px;
}

/* --- Close button --- */
#wppack-debug .wpd-close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-left: 1px solid #e5e7eb;
    color: #9ca3af;
    cursor: pointer;
    padding: 0 12px;
    height: 100%;
    flex-shrink: 0;
    line-height: 1;
    transition: color 0.15s ease, background 0.15s ease;
}
#wppack-debug .wpd-close-btn:hover {
    color: #cc1818;
    background: transparent;
}

/* --- Mini button --- */
#wppack-debug .wpd-mini {
    display: none;
    position: fixed;
    bottom: 6px;
    right: 6px;
    z-index: 99999;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    background: #3858e9;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
#wppack-debug .wpd-mini:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}
#wppack-debug .wpd-mini-logo {
    font-size: 11px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
}

/* --- Minimized state --- */
#wppack-debug.wpd-minimized .wpd-bar {
    display: none;
}
#wppack-debug.wpd-minimized .wpd-overlay {
    display: none !important;
}
#wppack-debug.wpd-minimized .wpd-mini {
    display: flex;
}

/* ================================================================
   L-Shape Overlay
   ================================================================ */
#wppack-debug .wpd-overlay {
    position: absolute;
    bottom: 40px;
    left: 0;
    right: 0;
    height: min(75vh, calc(100vh - 40px));
    display: flex;
    z-index: 1;
    border-top: 1px solid #e5e7eb;
}

/* --- Sidebar --- */
#wppack-debug .wpd-sidebar {
    width: 220px;
    flex-shrink: 0;
    background: #fafafa;
    border-right: 1px solid #e5e7eb;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
    display: flex;
    flex-direction: column;
    padding: 4px 0;
}
#wppack-debug .wpd-sidebar::-webkit-scrollbar {
    width: 4px;
}
#wppack-debug .wpd-sidebar::-webkit-scrollbar-track {
    background: transparent;
}
#wppack-debug .wpd-sidebar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 2px;
}

#wppack-debug .wpd-sidebar-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px 8px 16px;
    background: transparent;
    border: none;
    border-left: 3px solid transparent;
    color: #6b7280;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    text-align: left;
    transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
}
#wppack-debug .wpd-sidebar-item:hover {
    background: #ffffff;
    color: #1f2937;
}
#wppack-debug .wpd-sidebar-item.wpd-active {
    background: #ffffff;
    color: #1f2937;
    border-left-color: #3858e9;
    font-weight: 600;
}
#wppack-debug .wpd-sidebar-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    flex-shrink: 0;
}
#wppack-debug .wpd-sidebar-label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#wppack-debug .wpd-sidebar-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 4px 16px;
}

/* --- Content area --- */
#wppack-debug .wpd-content {
    flex: 1;
    min-width: 0;
    background: #ffffff;
    display: flex;
    flex-direction: column;
}

#wppack-debug .wpd-content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}
#wppack-debug .wpd-panel-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}
#wppack-debug .wpd-panel-close {
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 2px 8px;
    border-radius: 4px;
    line-height: 1;
    transition: color 0.15s ease, background 0.15s ease;
}
#wppack-debug .wpd-panel-close:hover {
    color: #cc1818;
    background: transparent;
}

#wppack-debug .wpd-content-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px 16px;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}
#wppack-debug .wpd-content-body::-webkit-scrollbar {
    width: 6px;
}
#wppack-debug .wpd-content-body::-webkit-scrollbar-track {
    background: transparent;
}
#wppack-debug .wpd-content-body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

/* ================================================================
   Panel content styles (reused from existing toolbar)
   ================================================================ */

/* --- Sections --- */
#wppack-debug .wpd-section {
    margin-bottom: 20px;
}
#wppack-debug .wpd-section:last-child {
    margin-bottom: 0;
}
#wppack-debug .wpd-section + .wpd-section {
    border-top: 1px solid #e5e7eb;
    margin-left: -16px;
    margin-right: -16px;
    padding: 20px 16px 0;
}
#wppack-debug .wpd-section-title {
    font-size: 11px;
    font-weight: 500;
    color: #1f2937;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 14px;
}

/* --- Tables --- */
#wppack-debug .wpd-table {
    width: 100%;
    border-collapse: collapse;
}
#wppack-debug .wpd-table th,
#wppack-debug .wpd-table td {
    padding: 6px 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: top;
}
#wppack-debug .wpd-table th:first-child,
#wppack-debug .wpd-table td:first-child {
    border-left: 1px solid #e5e7eb;
}
#wppack-debug .wpd-table th:last-child,
#wppack-debug .wpd-table td:last-child {
    border-right: 1px solid #e5e7eb;
}
#wppack-debug .wpd-table thead tr:first-child th,
#wppack-debug .wpd-table tbody:first-child tr:first-child td {
    border-top: 1px solid #e5e7eb;
}
#wppack-debug .wpd-table thead th {
    font-size: 11px;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: #ffffff;
    position: sticky;
    top: 0;
    z-index: 1;
}
#wppack-debug .wpd-table tbody tr:hover {
    background: #f3f4f6;
}

/* Key-value table */
#wppack-debug .wpd-table-kv .wpd-kv-key {
    width: 200px;
    font-weight: 400;
    color: #6b7280;
    white-space: nowrap;
}
#wppack-debug .wpd-table-kv .wpd-kv-val {
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
    word-break: break-all;
}

/* Right-aligned numeric columns */
#wppack-debug .wpd-table .wpd-col-right {
    text-align: right;
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
    white-space: nowrap;
}

/* Full-width table columns */
#wppack-debug .wpd-table .wpd-col-reltime {
    width: 70px;
    white-space: nowrap;
    text-align: right;
    font-size: 12px;
}
#wppack-debug .wpd-table .wpd-col-num {
    width: 40px;
    text-align: center;
    color: #9ca3af;
    font-size: 11px;
}
#wppack-debug .wpd-table .wpd-col-sql {
    max-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}
#wppack-debug .wpd-table .wpd-col-sql code {
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
    color: #1f2937;
    white-space: pre-wrap;
    word-break: break-all;
}
#wppack-debug .wpd-table .wpd-col-time {
    width: 90px;
    white-space: nowrap;
    text-align: right;
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
}
#wppack-debug .wpd-table .wpd-col-caller {
    width: 260px;
}
#wppack-debug .wpd-caller {
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 11px;
    color: #6b7280;
    word-break: break-all;
}

/* Query row highlighting */
#wppack-debug .wpd-row-slow {
    background: rgba(204, 24, 24, 0.04);
}
#wppack-debug .wpd-row-slow:hover {
    background: rgba(204, 24, 24, 0.08);
}
#wppack-debug .wpd-row-duplicate {
    background: rgba(153, 104, 0, 0.04);
}
#wppack-debug .wpd-row-duplicate:hover {
    background: rgba(153, 104, 0, 0.08);
}

/* Query tags */
#wppack-debug .wpd-query-tag {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1px 5px;
    border-radius: 4px;
    vertical-align: middle;
}
#wppack-debug .wpd-tag-slow {
    background: rgba(204, 24, 24, 0.08);
    color: #cc1818;
}
#wppack-debug .wpd-tag-dup {
    background: rgba(153, 104, 0, 0.08);
    color: #996800;
}

/* --- Timeline --- */
#wppack-debug .wpd-timeline {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
#wppack-debug .wpd-timeline-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
#wppack-debug .wpd-timeline-label {
    width: 150px;
    font-size: 12px;
    color: #6b7280;
    text-align: right;
    flex-shrink: 0;
}
#wppack-debug .wpd-timeline-bar-wrap {
    flex: 1;
    height: 14px;
    background: #f3f4f6;
    border-radius: 4px;
    overflow: hidden;
}
#wppack-debug .wpd-timeline-bar {
    height: 100%;
    background: #3858e9;
    border-radius: 4px;
    min-width: 2px;
    transition: width 0.3s ease;
}
#wppack-debug .wpd-timeline-value {
    width: 160px;
    font-size: 11px;
    color: #9ca3af;
    white-space: nowrap;
    flex-shrink: 0;
}

/* --- Memory bar --- */
#wppack-debug .wpd-memory-bar-wrap {
    height: 8px;
    background: #f3f4f6;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
}
#wppack-debug .wpd-memory-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* --- Tags --- */
#wppack-debug .wpd-tag {
    display: inline-block;
    font-size: 11px;
    padding: 1px 7px;
    background: #f3f4f6;
    color: #6b7280;
    border-radius: 4px;
}
#wppack-debug .wpd-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

/* --- Lists --- */
#wppack-debug .wpd-list {
    list-style: none;
    padding: 0;
}
#wppack-debug .wpd-list li {
    padding: 4px 0;
    border-bottom: 1px solid #e5e7eb;
    font-size: 12px;
}
#wppack-debug .wpd-list li:last-child {
    border-bottom: none;
}
#wppack-debug .wpd-list code {
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
}

/* --- Suggestions --- */
#wppack-debug .wpd-suggestions {
    list-style: none;
    padding: 0;
}
#wppack-debug .wpd-suggestion-item {
    padding: 8px 12px;
    background: rgba(153, 104, 0, 0.06);
    border-left: 3px solid #996800;
    border-radius: 0 4px 4px 0;
    margin-bottom: 4px;
    font-size: 12px;
    color: #996800;
}

/* --- Utility text colors --- */
#wppack-debug .wpd-text-green { color: #008a20; }
#wppack-debug .wpd-text-yellow { color: #996800; }
#wppack-debug .wpd-text-red { color: #cc1818; }
#wppack-debug .wpd-text-orange { color: #b32d2e; }
#wppack-debug .wpd-text-dim { color: #9ca3af; font-style: italic; }

/* --- Code blocks --- */
#wppack-debug code {
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
}

/* --- Performance cards --- */
#wppack-debug .wpd-perf-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
#wppack-debug .wpd-perf-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
#wppack-debug .wpd-perf-card-value {
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 18px;
    font-weight: 500;
    color: #374151;
}
#wppack-debug .wpd-perf-card-unit {
    font-size: 12px;
    font-weight: 400;
    color: #9ca3af;
    margin-left: 2px;
}
#wppack-debug .wpd-perf-card-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #6b7280;
    letter-spacing: 0.3px;
    margin-top: 4px;
}
#wppack-debug .wpd-perf-card-sub {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 2px;
}

/* --- Time Distribution --- */
#wppack-debug .wpd-perf-dist-bar {
    display: flex;
    height: 20px;
    background: #f3f4f6;
    border-radius: 4px;
    overflow: hidden;
}
#wppack-debug .wpd-perf-dist-segment {
    min-width: 2px;
}
#wppack-debug .wpd-perf-dist-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 12px;
}
#wppack-debug .wpd-perf-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #6b7280;
}
#wppack-debug .wpd-perf-legend-color {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
}

/* --- Waterfall --- */
#wppack-debug .wpd-perf-waterfall {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
#wppack-debug .wpd-perf-wf-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
#wppack-debug .wpd-perf-wf-label {
    width: 180px;
    flex-shrink: 0;
    text-align: right;
    font-size: 12px;
    color: #6b7280;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#wppack-debug .wpd-perf-wf-track {
    flex: 1;
    height: 16px;
    position: relative;
    background: #f3f4f6;
    border-radius: 4px;
}
#wppack-debug .wpd-perf-wf-bar {
    position: absolute;
    top: 0;
    height: 100%;
    background: #3858e9;
    border-radius: 4px;
    min-width: 2px;
}
#wppack-debug .wpd-perf-wf-bar[data-tooltip] {
    cursor: pointer;
}
#wppack-debug .wpd-tooltip {
    position: fixed;
    z-index: 100001;
    background: #1f2937;
    color: #e5e7eb;
    font-size: 11px;
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    line-height: 1.5;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #4b5563;
    white-space: pre;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
}
#wppack-debug .wpd-perf-wf-value {
    width: 80px;
    flex-shrink: 0;
    text-align: right;
    font-size: 11px;
    color: #9ca3af;
    white-space: nowrap;
}

/* --- Timeline dividers --- */
#wppack-debug .wpd-perf-wf-divider {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 6px 0 3px;
    color: #9ca3af;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
#wppack-debug .wpd-perf-wf-divider::before,
#wppack-debug .wpd-perf-wf-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

/* --- Log filter tabs --- */
#wppack-debug .wpd-log-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
}
#wppack-debug .wpd-log-tab {
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: #9ca3af;
    padding: 8px 16px;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
}
#wppack-debug .wpd-log-tab:hover {
    color: #1f2937;
}
#wppack-debug .wpd-log-tab.wpd-active {
    color: #3858e9;
    border-bottom-color: #3858e9;
}
#wppack-debug .wpd-log-context td {
    background: #fafafa;
}
#wppack-debug .wpd-log-context pre {
    font-size: 11px;
    color: #6b7280;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
}
#wppack-debug .wpd-log-toggle {
    cursor: pointer;
}

/* --- Plugin detail navigation --- */
#wppack-debug .wpd-plugin-detail-link {
    color: #3858e9;
    cursor: pointer;
    font-weight: 600;
}
#wppack-debug .wpd-plugin-detail-link:hover {
    text-decoration: underline;
}
#wppack-debug .wpd-plugin-back {
    background: transparent;
    border: 1px solid #3858e9;
    border-radius: 4px;
    color: #3858e9;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    padding: 4px 12px;
    transition: background 0.15s ease;
}
#wppack-debug .wpd-plugin-back:hover {
    background: #f3f4f6;
}

/* --- Dump / code preview --- */
#wppack-debug .wpd-dump-item {
    margin-bottom: 12px;
}
#wppack-debug .wpd-dump-item:last-child {
    margin-bottom: 0;
}
#wppack-debug .wpd-dump-file {
    font-size: 11px;
    color: #9ca3af;
    font-style: italic;
    margin-bottom: 4px;
}
#wppack-debug .wpd-dump-code {
    background: #fafafa;
    padding: 8px 12px;
    border-radius: 4px;
    overflow-x: auto;
    font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
    font-size: 12px;
    color: #1f2937;
    margin: 0;
}

/* --- Mail body / attachments --- */
#wppack-debug .wpd-mail-body {
    margin-top: 8px;
}
#wppack-debug .wpd-mail-body .wpd-dump-code {
    max-height: 200px;
    overflow-y: auto;
}
#wppack-debug .wpd-mail-attachments {
    margin-top: 8px;
}

/* --- Status tags (no margin-left, for use in titles) --- */
#wppack-debug .wpd-status-tag {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1px 5px;
    border-radius: 4px;
    vertical-align: middle;
}
#wppack-debug .wpd-status-sent {
    background: rgba(0, 163, 42, 0.08);
    color: #008a20;
}
#wppack-debug .wpd-status-failed {
    background: rgba(204, 24, 24, 0.08);
    color: #cc1818;
}
#wppack-debug .wpd-status-pending {
    background: rgba(153, 104, 0, 0.08);
    color: #996800;
}

/* ================================================================
   Responsive
   ================================================================ */

/* Tablet: icon-only sidebar */
@media (max-width: 1024px) {
    #wppack-debug .wpd-sidebar {
        width: 52px;
    }
    #wppack-debug .wpd-sidebar-label {
        display: none;
    }
    #wppack-debug .wpd-sidebar-item {
        justify-content: center;
        padding: 8px;
    }
}

/* Mobile: no sidebar */
@media (max-width: 768px) {
    #wppack-debug .wpd-sidebar {
        display: none;
    }
    #wppack-debug .wpd-overlay {
        height: min(60vh, calc(100vh - 40px));
    }
    #wppack-debug .wpd-perf-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    #wppack-debug .wpd-perf-wf-label {
        width: 100px;
    }
    #wppack-debug .wpd-timeline-label {
        width: 100px;
    }
    #wppack-debug .wpd-table .wpd-col-caller {
        width: 160px;
    }
}

/* Small mobile */
@media (max-width: 480px) {
    #wppack-debug .wpd-badge {
        padding: 0 8px;
    }
    #wppack-debug .wpd-badge-value {
        display: none;
    }
    #wppack-debug .wpd-bar-meta {
        display: none;
    }
    #wppack-debug .wpd-perf-cards {
        grid-template-columns: 1fr;
    }
    #wppack-debug .wpd-perf-wf-label {
        width: 70px;
        font-size: 11px;
    }
    #wppack-debug .wpd-perf-wf-value {
        width: 60px;
    }
}
</style>

<!-- L-shape overlay (initially hidden) -->
<div class="wpd-overlay" style="display:none">
    <div class="wpd-sidebar">
        <?= $sidebarHtml ?>
    </div>
    <div class="wpd-content">
        <div class="wpd-content-header">
            <span class="wpd-panel-title"><?= esc($firstName) ?></span>
            <button class="wpd-panel-close" data-action="close-panel" title="Close"><?= ToolbarIcons::svg('close', 14) ?></button>
        </div>
        <div class="wpd-content-body">
            <?= $contentDivs ?>
        </div>
    </div>
</div>

<!-- Mini button -->
<div class="wpd-mini" title="Show WpPack Debug Toolbar">
    <span class="wpd-mini-logo">WP</span>
</div>

<!-- Bottom bar -->
<div class="wpd-bar">
    <div class="wpd-bar-logo" title="WpPack Debug">
        <span class="wpd-logo-text">WP</span>
    </div>
    <div class="wpd-bar-badges">
        <?= $badgesHtml ?>
    </div>
    <div class="wpd-bar-meta">
        <span class="wpd-meta-item"><?= $requestInfo ?></span>
        <span class="wpd-meta-sep">|</span>
        <span class="wpd-meta-item"><?= $totalTimeFormatted ?></span>
    </div>
    <button class="wpd-close-btn" data-action="minimize" title="Close toolbar"><?= ToolbarIcons::svg('close', 14) ?></button>
</div>

<script>
(function() {
    var root = document.getElementById('wppack-debug');
    if (!root) return;

    var STORAGE_KEY = 'wppack-debug-minimized';
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        root.classList.add('wpd-minimized');
    }

    var overlay = root.querySelector('.wpd-overlay');
    var contentHeader = root.querySelector('.wpd-content-header .wpd-panel-title');
    var activePanel = null;

    var panelInfo = <?= json_encode(array_map(function($key) use ($labels) {
        return ['label' => $labels[$key] ?? $key];
    }, array_combine($sidebarOrder, $sidebarOrder)), JSON_UNESCAPED_UNICODE) ?>;

    function closeOverlay() {
        overlay.style.display = 'none';
        var badges = root.querySelectorAll('.wpd-badge');
        for (var i = 0; i < badges.length; i++) {
            badges[i].classList.remove('wpd-active');
        }
        var items = root.querySelectorAll('.wpd-sidebar-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.remove('wpd-active');
        }
        activePanel = null;
        resetPluginDetailView();
    }

    function resetPluginDetailView() {
        var lists = root.querySelectorAll('.wpd-plugin-list');
        for (var i = 0; i < lists.length; i++) {
            lists[i].style.display = '';
        }
        var details = root.querySelectorAll('.wpd-plugin-detail');
        for (var i = 0; i < details.length; i++) {
            details[i].style.display = 'none';
        }
    }

    function openPanel(name) {
        // Show overlay
        overlay.style.display = 'flex';

        // Update content
        var contents = root.querySelectorAll('.wpd-panel-content');
        for (var i = 0; i < contents.length; i++) {
            contents[i].style.display = 'none';
        }
        var target = root.querySelector('#wpd-pc-' + name);
        if (target) target.style.display = '';

        // Scroll content to top
        var body = root.querySelector('.wpd-content-body');
        if (body) body.scrollTop = 0;

        // Update header title
        var info = panelInfo[name];
        if (info && contentHeader) {
            contentHeader.textContent = info.label;
        }

        // Highlight sidebar item
        var items = root.querySelectorAll('.wpd-sidebar-item');
        for (var i = 0; i < items.length; i++) {
            if (items[i].getAttribute('data-panel') === name) {
                items[i].classList.add('wpd-active');
            } else {
                items[i].classList.remove('wpd-active');
            }
        }

        // Highlight badge
        var badges = root.querySelectorAll('.wpd-badge');
        for (var i = 0; i < badges.length; i++) {
            if (badges[i].getAttribute('data-panel') === name) {
                badges[i].classList.add('wpd-active');
            } else {
                badges[i].classList.remove('wpd-active');
            }
        }

        activePanel = name;
        resetPluginDetailView();
    }

    root.addEventListener('click', function(e) {
        // Mini button — restore toolbar
        var miniBtn = e.target.closest('.wpd-mini');
        if (miniBtn) {
            root.classList.remove('wpd-minimized');
            localStorage.removeItem(STORAGE_KEY);
            return;
        }

        // Sidebar item click — always switch, never close
        var sidebarItem = e.target.closest('.wpd-sidebar-item');
        if (sidebarItem) {
            var panel = sidebarItem.getAttribute('data-panel');
            if (activePanel !== panel) {
                openPanel(panel);
            }
            return;
        }

        // Badge click
        var badge = e.target.closest('.wpd-badge');
        if (badge) {
            var panel = badge.getAttribute('data-panel');
            if (activePanel === panel) {
                closeOverlay();
            } else {
                openPanel(panel);
            }
            return;
        }

        // Close button in content header
        var closeBtn = e.target.closest('[data-action="close-panel"]');
        if (closeBtn) {
            closeOverlay();
            return;
        }

        // Plugin detail link
        var pluginLink = e.target.closest('.wpd-plugin-detail-link');
        if (pluginLink) {
            var pluginSlug = pluginLink.getAttribute('data-plugin');
            var panelContent = pluginLink.closest('.wpd-panel-content');
            if (panelContent) {
                var list = panelContent.querySelector('.wpd-plugin-list');
                if (list) list.style.display = 'none';
                var detail = panelContent.querySelector('.wpd-plugin-detail[data-plugin="' + pluginSlug + '"]');
                if (detail) detail.style.display = '';
            }
            return;
        }

        // Plugin back button
        var backBtn = e.target.closest('[data-action="plugin-back"]');
        if (backBtn) {
            var panelContent = backBtn.closest('.wpd-panel-content');
            if (panelContent) {
                var details = panelContent.querySelectorAll('.wpd-plugin-detail');
                for (var i = 0; i < details.length; i++) {
                    details[i].style.display = 'none';
                }
                var list = panelContent.querySelector('.wpd-plugin-list');
                if (list) list.style.display = '';
            }
            return;
        }

        // Minimize toolbar
        var minimizeBtn = e.target.closest('[data-action="minimize"]');
        if (minimizeBtn) {
            closeOverlay();
            root.classList.add('wpd-minimized');
            localStorage.setItem(STORAGE_KEY, '1');
        }
    });

    // Log filter tabs
    root.addEventListener('click', function(e) {
        var tab = e.target.closest('.wpd-log-tab');
        if (tab) {
            var tabs = tab.closest('.wpd-log-tabs');
            tabs.querySelectorAll('.wpd-log-tab').forEach(function(t) { t.classList.remove('wpd-active'); });
            tab.classList.add('wpd-active');
            var filter = tab.getAttribute('data-log-filter');
            var section = tabs.closest('.wpd-section');
            section.querySelectorAll('tr[data-log-level]').forEach(function(row) {
                var level = row.getAttribute('data-log-level');
                var show = false;
                if (filter === 'all') { show = true; }
                else if (filter === 'error') { show = (['emergency','alert','critical','error'].indexOf(level) !== -1); }
                else if (filter === 'deprecation') { show = level === 'deprecation'; }
                else if (filter === 'warning') { show = (['warning','notice'].indexOf(level) !== -1); }
                else if (filter === 'info') { show = level === 'info'; }
                else if (filter === 'debug') { show = level === 'debug'; }
                row.style.display = show ? '' : 'none';
            });
            return;
        }
        // Context toggle
        var toggle = e.target.closest('.wpd-log-toggle');
        if (toggle) {
            var ctx = toggle.nextElementSibling;
            if (ctx && ctx.classList.contains('wpd-log-context')) {
                ctx.style.display = ctx.style.display === 'none' ? '' : 'none';
            }
        }
    });

    // Escape key closes overlay
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && activePanel !== null) {
            closeOverlay();
        }
    });

    // Timeline bar tooltips
    var tooltip = document.createElement('div');
    tooltip.className = 'wpd-tooltip';
    tooltip.style.display = 'none';
    root.appendChild(tooltip);

    root.addEventListener('mouseover', function(e) {
        var bar = e.target.closest('.wpd-perf-wf-bar[data-tooltip]');
        if (!bar) return;
        tooltip.textContent = bar.getAttribute('data-tooltip');
        tooltip.style.display = '';
        var rect = bar.getBoundingClientRect();
        var tipRect = tooltip.getBoundingClientRect();
        var left = rect.left + rect.width / 2 - tipRect.width / 2;
        if (left < 4) left = 4;
        if (left + tipRect.width > window.innerWidth - 4) left = window.innerWidth - 4 - tipRect.width;
        tooltip.style.left = left + 'px';
        tooltip.style.top = (rect.top - tipRect.height - 6) + 'px';
    });

    root.addEventListener('mouseout', function(e) {
        var bar = e.target.closest('.wpd-perf-wf-bar[data-tooltip]');
        if (bar) tooltip.style.display = 'none';
    });
})();
</script>
</div>

</body>
</html>
