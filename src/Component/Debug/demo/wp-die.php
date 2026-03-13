<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use WpPack\Component\Debug\DataCollector\AbstractDataCollector;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

// Minimal hook system for demo (supports add_action/add_filter/do_action/apply_filters)
/** @var array<string, list<array{callback: callable, priority: int, accepted_args: int}>> */
$_wp_demo_filters = [];

if (!function_exists('add_filter')) {
    function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true
    {
        global $_wp_demo_filters;
        $_wp_demo_filters[$hook_name][] = ['callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args];
        usort($_wp_demo_filters[$hook_name], fn($a, $b) => $a['priority'] <=> $b['priority']);

        return true;
    }
}
if (!function_exists('add_action')) {
    function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true
    {
        return add_filter($hook_name, $callback, $priority, $accepted_args);
    }
}
if (!function_exists('do_action')) {
    function do_action(string $hook_name, mixed ...$args): void
    {
        global $_wp_demo_filters;
        foreach ($_wp_demo_filters[$hook_name] ?? [] as $hook) {
            ($hook['callback'])(...\array_slice($args, 0, $hook['accepted_args']));
        }
    }
}
if (!function_exists('has_filter')) {
    function has_filter(string $hook_name, mixed $callback = false): bool|int
    {
        global $_wp_demo_filters;

        return isset($_wp_demo_filters[$hook_name]) && $_wp_demo_filters[$hook_name] !== [] ? 10 : false;
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed
    {
        global $_wp_demo_filters;
        foreach ($_wp_demo_filters[$hook_name] ?? [] as $hook) {
            $value = ($hook['callback'])($value, ...\array_slice($args, 0, $hook['accepted_args'] - 1));
        }

        return $value;
    }
}
if (!function_exists('remove_filter')) {
    function remove_filter(string $hook_name, callable $callback, int $priority = 10): bool
    {
        return true;
    }
}
if (!class_exists('WP_Error')) {
    require_once __DIR__ . '/../../../../vendor/roots/wordpress-no-content/wp-includes/class-wp-error.php';
}

/**
 * Fake collector that injects pre-built data for demo purposes.
 */
if (!class_exists('FakeCollector')) {
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
}

// --- Build toolbar ---

$collectors = [];

$collectors[] = new FakeCollector('request', 'Request', 'GET 500', 'red', [
    'method' => 'GET',
    'status_code' => 500,
    'url' => '/wp-admin/options-general.php',
    'route' => 'admin',
    'content_type' => 'text/html; charset=UTF-8',
    'headers' => [
        'request' => ['Host' => 'example.com', 'Accept' => 'text/html'],
        'response' => ['Content-Type' => 'text/html; charset=UTF-8'],
    ],
]);

$collectors[] = new FakeCollector('database', 'Database', '3', 'default', [
    'queries' => [
        ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = %s', 'time' => 0.45, 'params' => ['siteurl'], 'caller' => 'get_option()'],
    ],
    'total_time' => 0.45,
    'query_count' => 3,
]);

$collectors[] = new FakeCollector('memory', 'Memory', '12.0 MB', 'default', [
    'usage' => memory_get_usage(true),
    'peak' => memory_get_peak_usage(true),
    'limit' => 268435456,
]);

$collectors[] = new FakeCollector('logger', 'Logs', '1', 'red', [
    'logs' => [
        ['level' => 'error', 'message' => 'wp_die() called', 'context' => [], 'channel' => 'wordpress', 'timestamp' => time()],
    ],
    'count_by_level' => ['error' => 1],
    'deprecation_count' => 0,
    'deprecation_logs' => [],
]);

$collectors[] = new FakeCollector('wordpress', 'WordPress', '6.7.2', 'default', [
    'wp_version' => '6.7.2',
    'php_version' => PHP_VERSION,
    'environment_type' => 'development',
    'is_multisite' => false,
    'theme' => 'flavor',
    'is_block_theme' => false,
    'is_child_theme' => false,
    'theme_version' => '1.0.0',
    'active_plugins' => ['wppack/debug' => 'WpPack Debug'],
    'constants' => [
        'WP_DEBUG' => true,
        'SAVEQUERIES' => true,
        'SCRIPT_DEBUG' => false,
        'WP_DEBUG_LOG' => false,
        'WP_DEBUG_DISPLAY' => true,
        'WP_CACHE' => false,
    ],
    'extensions' => get_loaded_extensions(),
]);

$collectors[] = new FakeCollector('environment', 'Environment', '', 'default', [
    'php_version' => PHP_VERSION,
    'php_sapi' => PHP_SAPI,
    'php_extensions' => get_loaded_extensions(),
    'wp_version' => '6.7.2',
    'wp_debug' => true,
    'wp_debug_log' => false,
    'wp_debug_display' => true,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP ' . PHP_VERSION . ' Development Server',
    'os' => PHP_OS . ' (' . php_uname('r') . ')',
]);

$profile = new Profile(token: 'wp-die-demo-' . bin2hex(random_bytes(4)));
$profile->setUrl('/wp-admin/options-general.php');
$profile->setMethod('GET');
$profile->setStatusCode(500);

foreach ($collectors as $collector) {
    $collector->collect();
    $profile->addCollector($collector);
}

$toolbarRenderer = new ToolbarRenderer();
$toolbarRenderer->addPanelRenderer(new DatabasePanelRenderer());
$toolbarRenderer->addPanelRenderer(new MemoryPanelRenderer());
$toolbarRenderer->addPanelRenderer(new RequestPanelRenderer());
$toolbarRenderer->addPanelRenderer(new WordPressPanelRenderer());
$toolbarRenderer->addPanelRenderer(new LoggerPanelRenderer());
$toolbarRenderer->addPanelRenderer(new EnvironmentPanelRenderer());

// --- Simulate wp_die() scenarios ---

$renderer = new ErrorRenderer();
$handler = new WpDieHandler($renderer, new DebugConfig(enabled: true), $toolbarRenderer, $profile);
$handler->register();

// Stub wp_die() so the backtrace includes a wp_die frame (matching real WP behavior)
if (!function_exists('wp_die')) {
    /** @param array<string, mixed>|int|string $args */
    function wp_die(string|\WP_Error $message = '', string $title = '', array|int|string $args = []): void
    {
        global $handler;
        if (is_int($args) || is_string($args)) {
            $args = ['response' => (int) $args];
        }
        $handler->handleHtml($message, $title, $args);
    }
}

$scenario = $_GET['scenario'] ?? 'permission';

match ($scenario) {
    'permission' => simulateAdminPageLoad($handler),
    'db' => simulateDatabaseBootstrap($handler),
    'nonce' => simulateFormSubmission($handler),
    default => simulateGenericError($handler),
};

// ── Permission denied scenario ──────────────────────────────────

function simulateAdminPageLoad(WpDieHandler $handler): void
{
    loadAdminPage($handler);
}

function loadAdminPage(WpDieHandler $handler): void
{
    checkUserCapability($handler, 'manage_options');
}

function checkUserCapability(WpDieHandler $handler, string $capability): void
{
    // In real WP: current_user_can($capability) returns false → wp_die()
    $error = new \WP_Error('forbidden', 'Sorry, you are not allowed to access this page.');
    $error->add_data(['required_capability' => $capability], 'forbidden');
    wp_die($error, 'WordPress › Forbidden', ['response' => 403, 'exit' => false]);
}

// ── Database connection error scenario ──────────────────────────

function simulateDatabaseBootstrap(WpDieHandler $handler): void
{
    initializeDatabase($handler);
}

function initializeDatabase(WpDieHandler $handler): void
{
    connectToDatabase($handler, '127.0.0.1', 'wp_user', 'wppack_db');
}

function connectToDatabase(WpDieHandler $handler, string $host, string $user, string $dbName): void
{
    // In real WP: $wpdb->db_connect() fails → wp_die()
    wp_die(new \WP_Error('db_connect_fail', 'Error establishing a database connection.'), 'WordPress › Database Error', ['response' => 500, 'exit' => false]);
}

// ── Nonce failure scenario ──────────────────────────────────────

function simulateFormSubmission(WpDieHandler $handler): void
{
    processPostForm($handler);
}

function processPostForm(WpDieHandler $handler): void
{
    verifyNonce($handler, 'update-post_42', '_wpnonce');
}

function verifyNonce(WpDieHandler $handler, string $action, string $field): void
{
    // In real WP: check_admin_referer() fails → wp_die()
    wp_die('The link you followed has expired. Please try again.', 'WordPress › Nonce Failure', ['response' => 403, 'exit' => false]);
}

// ── Generic error scenario ──────────────────────────────────────

function simulateGenericError(WpDieHandler $handler): void
{
    wp_die('Something went wrong.', 'WordPress › Error', ['response' => 500, 'exit' => false]);
}
