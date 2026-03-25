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

require_once __DIR__ . '/bootstrap.php';

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
    'total_count' => 1,
    'error_count' => 1,
    'deprecation_count' => 0,
    'logs' => [
        ['level' => 'error', 'message' => 'wp_die() called', 'context' => [], 'channel' => 'wordpress', 'file' => '/var/www/html/wp-content/plugins/my-plugin/admin.php', 'line' => 42, 'timestamp' => time()],
    ],
    'level_counts' => ['error' => 1],
    'channel_counts' => ['wordpress' => 1],
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
    'php' => [
        'version' => PHP_VERSION,
        'zend_version' => zend_version(),
        'zts' => PHP_ZTS,
        'debug' => PHP_DEBUG,
        'gc_enabled' => gc_enabled(),
    ],
    'sapi' => PHP_SAPI,
    'extensions' => get_loaded_extensions(),
    'ini' => [
        'memory_limit' => ini_get('memory_limit') ?: '',
        'max_execution_time' => ini_get('max_execution_time') ?: '',
        'display_errors' => ini_get('display_errors') ?: '',
    ],
    'opcache' => ['enabled' => false],
    'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
        'web_server' => PHP_SAPI === 'cli-server'
            ? ['name' => 'PHP Built-in', 'version' => PHP_VERSION, 'raw' => 'PHP Built-in Server']
            : ['name' => '', 'version' => '', 'raw' => $_SERVER['SERVER_SOFTWARE'] ?? ''],
        'name' => $_SERVER['SERVER_NAME'] ?? '',
        'port' => $_SERVER['SERVER_PORT'] ?? '',
        'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    ],
    'runtime' => ['type' => '', 'details' => []],
    'os' => PHP_OS . ' (' . php_uname('r') . ')',
    'architecture' => PHP_INT_SIZE * 8,
    'hostname' => gethostname() ?: '',
]);

$profile = new Profile(token: 'wp-die-demo-' . bin2hex(random_bytes(4)));
$profile->setUrl('/wp-admin/options-general.php');
$profile->setMethod('GET');
$profile->setStatusCode(500);

foreach ($collectors as $collector) {
    $collector->collect();
    $profile->addCollector($collector);
}

$toolbarRenderer = new ToolbarRenderer($profile);
$toolbarRenderer->addPanelRenderer(new DatabasePanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new MemoryPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new RequestPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new WordPressPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new LoggerPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new EnvironmentPanelRenderer($profile));

// --- Simulate wp_die() scenarios ---

$renderer = new ErrorRenderer();
$handler = new WpDieHandler($renderer, new DebugConfig(enabled: true, roleWhitelist: []), $toolbarRenderer, $profile);
$handler->register();

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
