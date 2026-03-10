<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use WpPack\Component\Debug\DataCollector\AbstractDataCollector;
use WpPack\Component\Debug\Profiler\Profile;
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

// --- Build sample collectors ---

$collectors = [];

// Request
$collectors[] = new FakeCollector('request', 'Request', 'GET 200', 'green', [
    'method' => 'GET',
    'url' => '/2024/03/hello-world/',
    'status_code' => 200,
    'headers' => [
        'Host' => 'example.local',
        'Accept' => 'text/html,application/xhtml+xml',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'Accept-Language' => 'ja,en;q=0.9',
        'Cookie' => '***',
    ],
    'get_params' => [],
    'post_params' => [],
    'cookies' => ['wordpress_logged_in_xxx' => '***'],
    'content_type' => 'text/html; charset=UTF-8',
]);

// Database
$requestTimeFloat = microtime(true) - 0.198; // 198ms ago
$collectors[] = new FakeCollector('database', 'Database', '24', 'green', [
    'total_queries' => 24,
    'total_time' => 12.45,
    'duplicate_count' => 2,
    'slow_count' => 0,
    'savequeries' => true,
    'queries' => [
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 1.23, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.015, 'data' => []],
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 0.98, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.025, 'data' => []],
        ['sql' => 'SELECT option_name, option_value FROM wp_options WHERE autoload = \'yes\'', 'time' => 0.13, 'caller' => 'wp_load_alloptions', 'start' => $requestTimeFloat + 0.035, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 42 LIMIT 1', 'time' => 0.45, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.060, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 15 LIMIT 1', 'time' => 0.38, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.070, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 7 LIMIT 1', 'time' => 0.29, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.085, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 3 LIMIT 1', 'time' => 0.35, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.100, 'data' => []],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = 99 LIMIT 1', 'time' => 0.33, 'caller' => 'WP_Post::get_instance', 'start' => $requestTimeFloat + 0.110, 'data' => []],
        ['sql' => 'SELECT * FROM wp_users WHERE ID = 1 LIMIT 1', 'time' => 0.32, 'caller' => 'WP_User::get_data_by', 'start' => $requestTimeFloat + 0.125, 'data' => []],
        ['sql' => 'SELECT t.*, tt.* FROM wp_terms INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = \'category\'', 'time' => 0.56, 'caller' => 'get_terms', 'start' => $requestTimeFloat + 0.140, 'data' => []],
        ['sql' => 'SELECT t.*, tt.* FROM wp_terms INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = \'post_tag\'', 'time' => 0.48, 'caller' => 'get_terms', 'start' => $requestTimeFloat + 0.155, 'data' => []],
    ],
    'suggestions' => ['2 duplicate queries detected — consider caching results'],
]);

// Memory
$collectors[] = new FakeCollector('memory', 'Memory', '42.5 MB', 'green', [
    'current' => 42 * 1024 * 1024,
    'peak' => 44.5 * 1024 * 1024,
    'limit' => 256 * 1024 * 1024,
    'usage_percentage' => 17.38,
    'snapshots' => [
        'wp_loaded' => 28 * 1024 * 1024,
        'template_redirect' => 36 * 1024 * 1024,
        'wp_footer' => 42 * 1024 * 1024,
    ],
]);

// Time
$collectors[] = new FakeCollector('time', 'Time', '198 ms', 'green', [
    'total_time' => 198.3,
    'request_time_float' => microtime(true) - 0.198,
    'events' => [
        'muplugins_loaded' => ['name' => 'muplugins_loaded', 'category' => 'wp_lifecycle', 'duration' => 12.3, 'memory' => 8 * 1024 * 1024, 'start_time' => 0, 'end_time' => 12.3],
        'plugins_loaded' => ['name' => 'plugins_loaded', 'category' => 'wp_lifecycle', 'duration' => 45.6, 'memory' => 18 * 1024 * 1024, 'start_time' => 12.3, 'end_time' => 57.9],
        'init' => ['name' => 'init', 'category' => 'wp_lifecycle', 'duration' => 23.4, 'memory' => 22 * 1024 * 1024, 'start_time' => 57.9, 'end_time' => 81.3],
        'wp_loaded' => ['name' => 'wp_loaded', 'category' => 'wp_lifecycle', 'duration' => 3.9, 'memory' => 28 * 1024 * 1024, 'start_time' => 81.3, 'end_time' => 85.2],
        'wp' => ['name' => 'wp', 'category' => 'wp_lifecycle', 'duration' => 15.2, 'memory' => 30 * 1024 * 1024, 'start_time' => 85.2, 'end_time' => 100.4],
        'template_redirect' => ['name' => 'template_redirect', 'category' => 'wp_lifecycle', 'duration' => 22.1, 'memory' => 33 * 1024 * 1024, 'start_time' => 100.4, 'end_time' => 122.5],
        'wp_head' => ['name' => 'wp_head', 'category' => 'wp_lifecycle', 'duration' => 35.8, 'memory' => 38 * 1024 * 1024, 'start_time' => 122.5, 'end_time' => 158.3],
        'wp_footer' => ['name' => 'wp_footer', 'category' => 'wp_lifecycle', 'duration' => 40.0, 'memory' => 42 * 1024 * 1024, 'start_time' => 158.3, 'end_time' => 198.3],
    ],
    'phases' => [
        'muplugins_loaded' => 12.3,
        'plugins_loaded' => 57.9,
        'init' => 81.3,
        'wp_loaded' => 85.2,
        'wp' => 100.4,
        'template_redirect' => 122.5,
        'wp_head' => 158.3,
        'wp_footer' => 198.3,
    ],
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
        ['name' => 'my_plugin_data', 'operation' => 'set', 'expiration' => 3600, 'caller' => 'MyPlugin::refresh_cache', 'time' => 95.0],
        ['name' => 'external_api_response', 'operation' => 'set', 'expiration' => 86400, 'caller' => 'fetch_api_data', 'time' => 130.0],
        ['name' => 'theme_update_check', 'operation' => 'set', 'expiration' => 43200, 'caller' => 'wp_update_themes', 'time' => 145.0],
        ['name' => 'old_cache_key', 'operation' => 'delete', 'expiration' => 0, 'caller' => 'MyPlugin::clear_cache', 'time' => 170.0],
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

// Router — FSE block theme scenario
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
    'active_plugins' => [
        'wppack/wppack.php',
        'advanced-custom-fields/acf.php',
        'wp-graphql/wp-graphql.php',
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

// User
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

// Event (enhanced with hook_timings, component_hooks, component_summary)
$collectors[] = new FakeCollector('event', 'Event', '847', 'green', [
    'total_firings' => 847,
    'unique_hooks' => 312,
    'registered_hooks' => 280,
    'orphan_hooks' => 5,
    'listener_counts' => [
        'init' => 45,
        'wp_head' => 28,
        'the_content' => 12,
        'wp_enqueue_scripts' => 8,
        'template_redirect' => 6,
        'wp_loaded' => 4,
        'wp_footer' => 15,
        'after_setup_theme' => 5,
    ],
    'top_hooks' => [
        'init' => 45,
        'wp_head' => 28,
        'wp_footer' => 15,
        'the_content' => 12,
        'wp_enqueue_scripts' => 8,
    ],
    'hook_timings' => [
        'init' => ['count' => 45, 'total_time' => 15.0, 'start' => 57.9],
        'wp_head' => ['count' => 28, 'total_time' => 25.0, 'start' => 122.5],
        'wp_footer' => ['count' => 15, 'total_time' => 18.0, 'start' => 158.3],
        'the_content' => ['count' => 12, 'total_time' => 8.5, 'start' => 130.0],
        'wp_enqueue_scripts' => ['count' => 8, 'total_time' => 5.2, 'start' => 110.0],
        'wp_loaded' => ['count' => 4, 'total_time' => 2.1, 'start' => 81.3],
        'template_redirect' => ['count' => 6, 'total_time' => 3.0, 'start' => 100.4],
        'after_setup_theme' => ['count' => 5, 'total_time' => 4.5, 'start' => 20.0],
        'plugins_loaded' => ['count' => 3, 'total_time' => 2.0, 'start' => 12.3],
    ],
    'component_hooks' => [
        'woocommerce' => ['init' => 3, 'wp_loaded' => 1, 'wp_head' => 5, 'wp_footer' => 2, 'wp_enqueue_scripts' => 2],
        'akismet' => ['init' => 1, 'template_redirect' => 1],
        'yoast-seo' => ['init' => 2, 'wp_head' => 3, 'the_content' => 1],
        'theme:flavor' => ['after_setup_theme' => 2, 'wp_head' => 5, 'wp_footer' => 3, 'wp_enqueue_scripts' => 2],
        'core' => ['init' => 12, 'wp_head' => 8, 'wp_footer' => 5],
    ],
    'component_summary' => [
        'woocommerce' => ['type' => 'plugin', 'hooks' => 5, 'listeners' => 13, 'total_time' => 23.5],
        'yoast-seo' => ['type' => 'plugin', 'hooks' => 3, 'listeners' => 6, 'total_time' => 12.0],
        'theme:flavor' => ['type' => 'theme', 'hooks' => 4, 'listeners' => 12, 'total_time' => 12.0],
        'akismet' => ['type' => 'plugin', 'hooks' => 2, 'listeners' => 2, 'total_time' => 5.1],
        'core' => ['type' => 'core', 'hooks' => 3, 'listeners' => 25, 'total_time' => 45.0],
    ],
]);

// Logger
$collectors[] = new FakeCollector('logger', 'Logger', '3', 'yellow', [
    'total_count' => 3,
    'error_count' => 0,
    'logs' => [
        ['level' => 'warning', 'message' => 'Function get_bloginfo(\'url\') is deprecated since version 3.0.0. Use home_url() instead.', 'context' => [], 'channel' => 'deprecation'],
        ['level' => 'info', 'message' => 'Cache cleared for post_id=42', 'context' => ['post_id' => 42], 'channel' => 'app'],
        ['level' => 'debug', 'message' => 'Template resolved: single.html', 'context' => [], 'channel' => 'routing'],
    ],
    'level_counts' => ['warning' => 1, 'info' => 1, 'debug' => 1],
]);

// HttpClient
$collectors[] = new FakeCollector('http_client', 'HTTP Client', '2', 'green', [
    'total_count' => 2,
    'total_time' => 34.3,
    'error_count' => 0,
    'slow_count' => 0,
    'requests' => [
        ['method' => 'GET', 'url' => 'https://api.wordpress.org/plugins/update-check/1.1/', 'status_code' => 200, 'duration' => 23.5, 'start' => $requestTimeFloat + 0.050, 'response_size' => 4521, 'error' => ''],
        ['method' => 'POST', 'url' => 'https://api.wordpress.org/themes/update-check/1.1/', 'status_code' => 200, 'duration' => 10.8, 'start' => $requestTimeFloat + 0.120, 'response_size' => 1280, 'error' => ''],
    ],
]);

// Translation
$collectors[] = new FakeCollector('translation', 'Translation', '2', 'yellow', [
    'missing_count' => 2,
    'total_lookups' => 156,
    'loaded_domains' => ['default', 'flavor', 'acf'],
    'domain_usage' => ['default' => 98, 'flavor' => 45, 'acf' => 13],
    'missing_translations' => [
        ['original' => 'Read more...', 'domain' => 'flavor'],
        ['original' => 'Share this post', 'domain' => 'flavor'],
    ],
]);

// Mail (enhanced with structured headers and attachment details)
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

// Plugin
$collectors[] = new FakeCollector('plugin', 'Plugins', '4', 'green', [
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
            'hooks' => [
                ['hook' => 'init', 'listeners' => 3, 'time' => 8.2, 'start' => 57.9],
                ['hook' => 'wp_head', 'listeners' => 5, 'time' => 6.8, 'start' => 122.5],
                ['hook' => 'wp_footer', 'listeners' => 2, 'time' => 4.3, 'start' => 158.3],
                ['hook' => 'wp_loaded', 'listeners' => 1, 'time' => 2.1, 'start' => 81.3],
                ['hook' => 'wp_enqueue_scripts', 'listeners' => 2, 'time' => 2.1, 'start' => 110.0],
            ],
        ],
        'wordpress-seo/wp-seo.php' => [
            'name' => 'Yoast SEO',
            'version' => '22.0',
            'load_time' => 8.3,
            'hook_count' => 3,
            'listener_count' => 6,
            'hook_time' => 12.0,
            'query_count' => 3,
            'query_time' => 2.1,
            'hooks' => [
                ['hook' => 'wp_head', 'listeners' => 3, 'time' => 7.5, 'start' => 122.5],
                ['hook' => 'init', 'listeners' => 2, 'time' => 3.0, 'start' => 57.9],
                ['hook' => 'the_content', 'listeners' => 1, 'time' => 1.5, 'start' => 130.0],
            ],
        ],
        'akismet/akismet.php' => [
            'name' => 'Akismet Anti-spam',
            'version' => '5.3',
            'load_time' => 3.2,
            'hook_count' => 2,
            'listener_count' => 2,
            'hook_time' => 5.1,
            'query_count' => 2,
            'query_time' => 1.0,
            'hooks' => [
                ['hook' => 'init', 'listeners' => 1, 'time' => 3.0, 'start' => 57.9],
                ['hook' => 'template_redirect', 'listeners' => 1, 'time' => 2.1, 'start' => 100.4],
            ],
        ],
        'contact-form-7/wp-contact-form-7.php' => [
            'name' => 'Contact Form 7',
            'version' => '5.9',
            'load_time' => 2.1,
            'hook_count' => 2,
            'listener_count' => 3,
            'hook_time' => 3.2,
            'query_count' => 0,
            'query_time' => 0.0,
            'hooks' => [
                ['hook' => 'init', 'listeners' => 2, 'time' => 2.0, 'start' => 57.9],
                ['hook' => 'wp_enqueue_scripts', 'listeners' => 1, 'time' => 1.2, 'start' => 110.0],
            ],
        ],
    ],
    'total_plugins' => 4,
    'mu_plugins' => ['loader.php'],
    'dropins' => ['advanced-cache.php', 'object-cache.php'],
    'load_order' => ['akismet/akismet.php', 'contact-form-7/wp-contact-form-7.php', 'woocommerce/woocommerce.php', 'wordpress-seo/wp-seo.php'],
    'slowest_plugin' => 'woocommerce/woocommerce.php',
    'total_hook_time' => 43.8,
]);

// Theme
$collectors[] = new FakeCollector('theme', 'Theme', 'Flavor', 'default', [
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
    'setup_time' => 5.2,
    'render_time' => 35.0,
    'hook_count' => 4,
    'listener_count' => 12,
    'hook_time' => 12.0,
    'hooks' => [
        ['hook' => 'wp_head', 'listeners' => 5, 'time' => 6.5],
        ['hook' => 'wp_footer', 'listeners' => 3, 'time' => 3.0],
        ['hook' => 'after_setup_theme', 'listeners' => 2, 'time' => 1.5],
        ['hook' => 'wp_enqueue_scripts', 'listeners' => 2, 'time' => 1.0],
    ],
]);

// Scheduler
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

// --- Build profile and render ---

$profile = new Profile(token: 'demo-' . bin2hex(random_bytes(4)));
$profile->setUrl('/2024/03/hello-world/');
$profile->setMethod('GET');
$profile->setStatusCode(200);

foreach ($collectors as $collector) {
    $collector->collect();
    $profile->addCollector($collector);
}

$renderer = new ToolbarRenderer();
$html = $renderer->render($profile);
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
        Click any badge in the toolbar below to expand the corresponding panel.</p>
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
