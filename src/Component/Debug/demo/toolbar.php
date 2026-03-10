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

// Event
$collectors[] = new FakeCollector('event', 'Event', '847', 'green', [
    'total_firings' => 847,
    'unique_hooks' => 312,
    'total_listeners' => 524,
    'orphan_hooks' => 5,
    'top_hooks' => [
        'init' => 45,
        'wp_head' => 28,
        'the_content' => 12,
        'wp_enqueue_scripts' => 8,
        'template_redirect' => 6,
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

// Mail
$collectors[] = new FakeCollector('mail', 'Mail', '0', 'green', [
    'total_count' => 0,
    'success_count' => 0,
    'failure_count' => 0,
    'pending_count' => 0,
    'emails' => [],
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
