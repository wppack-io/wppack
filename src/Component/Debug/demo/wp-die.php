<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;

// Stub WordPress functions required by WP_Error
if (!function_exists('do_action')) {
    function do_action(string $hook_name, mixed ...$args): void {}
}
if (!function_exists('has_filter')) {
    function has_filter(string $hook_name, mixed $callback = false): bool { return false; }
}
if (!class_exists('WP_Error')) {
    require_once __DIR__ . '/../../../../vendor/roots/wordpress-no-content/wp-includes/class-wp-error.php';
}

// --- Simulate realistic wp_die() call chains ---
// WpDieHandler.handleHtml() captures debug_backtrace() internally,
// so the code snippet and stack trace reflect the actual call site.

$handler = new WpDieHandler(new ErrorRenderer(), new DebugConfig(enabled: true));
$handler->registerHtmlHandler('_default_wp_die_handler');

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
    $handler->handleHtml(new \WP_Error('forbidden', 'Sorry, you are not allowed to access this page.'), 'WordPress › Forbidden', ['response' => 403, 'exit' => false]);
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
    $handler->handleHtml(new \WP_Error('db_connect_fail', 'Error establishing a database connection.'), 'WordPress › Database Error', ['response' => 500, 'exit' => false]);
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
    $handler->handleHtml('The link you followed has expired. Please try again.', 'WordPress › Nonce Failure', ['response' => 403, 'exit' => false]);
}

// ── Generic error scenario ──────────────────────────────────────

function simulateGenericError(WpDieHandler $handler): void
{
    $handler->handleHtml('Something went wrong.', 'WordPress › Error', ['response' => 500, 'exit' => false]);
}
